import ExcelJS from "exceljs";
import { Readable } from "node:stream";
import { badRequest } from "./errors";

export interface SheetInfo {
  name: string;
  columns: { index: number; header: string; nonBlank: number }[];
}

const MAX_ROWS = 20000;

function cellValue(v: ExcelJS.CellValue): unknown {
  if (v === null || v === undefined) return null;
  if (typeof v === "object") {
    if (v instanceof Date) return null;
    if ("result" in v) return cellValue((v as ExcelJS.CellFormulaValue).result as ExcelJS.CellValue);
    if ("richText" in v) return (v as ExcelJS.CellRichTextValue).richText.map((t) => t.text).join("");
    if ("text" in v) return String((v as ExcelJS.CellHyperlinkValue).text);
    return null; // errors, etc.
  }
  return v;
}

async function load(buf: Buffer, filename: string): Promise<ExcelJS.Workbook> {
  const wb = new ExcelJS.Workbook();
  try {
    if (/\.csv$/i.test(filename)) await wb.csv.read(Readable.from(buf));
    else await wb.xlsx.load(buf as unknown as ExcelJS.Buffer);
  } catch {
    throw badRequest("That file could not be read. Please upload a valid .xlsx or .csv workbook.");
  }
  if (wb.worksheets.length === 0) throw badRequest("The workbook contains no sheets.");
  return wb;
}

/** Lists sheets and columns (header = first row) so the examiner can pick e.g. "T1". */
export async function inspectWorkbook(buf: Buffer, filename: string): Promise<SheetInfo[]> {
  const wb = await load(buf, filename);
  return wb.worksheets.map((ws) => {
    const header = ws.getRow(1);
    const columns: SheetInfo["columns"] = [];
    const width = Math.min(ws.columnCount, 200);
    for (let c = 1; c <= width; c++) {
      const h = cellValue(header.getCell(c).value);
      let nonBlank = 0;
      const last = Math.min(ws.rowCount, MAX_ROWS);
      for (let r = 2; r <= last; r++) {
        const v = cellValue(ws.getRow(r).getCell(c).value);
        if (v !== null && String(v).trim() !== "") nonBlank++;
      }
      if (h === null && nonBlank === 0) continue;
      columns.push({ index: c, header: h === null || String(h).trim() === "" ? `Column ${c}` : String(h).trim(), nonBlank });
    }
    return { name: ws.name, columns };
  });
}

/** Header text and raw cell values of one column (excluding the header row). */
export async function readColumn(
  buf: Buffer,
  filename: string,
  sheet: string,
  columnIndex: number,
): Promise<{ header: string; values: unknown[] }> {
  const wb = await load(buf, filename);
  const ws = wb.getWorksheet(sheet);
  if (!ws) throw badRequest(`Sheet "${sheet}" was not found in the workbook.`);
  if (ws.rowCount - 1 > MAX_ROWS) throw badRequest(`Sheets are limited to ${MAX_ROWS.toLocaleString()} rows.`);
  const h = cellValue(ws.getRow(1).getCell(columnIndex).value);
  const values: unknown[] = [];
  for (let r = 2; r <= ws.rowCount; r++) values.push(cellValue(ws.getRow(r).getCell(columnIndex).value));
  return { header: h === null || String(h).trim() === "" ? `Column ${columnIndex}` : String(h).trim(), values };
}
