import { prisma } from "./db";

/**
 * Pluggable file storage.
 *  - "db": bytes live in PostgreSQL (default; no extra infrastructure, survives Render redeploys)
 *  - "s3": AWS S3 or any S3-compatible service
 * Keys are stored on Attachment.storageKey as "<driver>:<id>".
 */
const driver = () => (process.env.STORAGE_DRIVER === "s3" ? "s3" : "db");

async function s3() {
  const { S3Client } = await import("@aws-sdk/client-s3");
  const g = globalThis as unknown as { _s3?: InstanceType<typeof S3Client> };
  g._s3 ??= new S3Client({
    region: process.env.S3_REGION || "us-east-1",
    endpoint: process.env.S3_ENDPOINT || undefined,
    forcePathStyle: !!process.env.S3_ENDPOINT,
    credentials: process.env.S3_ACCESS_KEY_ID
      ? { accessKeyId: process.env.S3_ACCESS_KEY_ID, secretAccessKey: process.env.S3_SECRET_ACCESS_KEY ?? "" }
      : undefined,
  });
  return g._s3;
}

function bucket() {
  const b = process.env.S3_BUCKET;
  if (!b) throw new Error("STORAGE_DRIVER=s3 requires S3_BUCKET.");
  return b;
}

export async function putFile(data: Buffer, mimeType: string): Promise<string> {
  if (driver() === "s3") {
    const { PutObjectCommand } = await import("@aws-sdk/client-s3");
    const key = `dems/${crypto.randomUUID()}`;
    await (await s3()).send(
      new PutObjectCommand({
        Bucket: bucket(),
        Key: key,
        Body: data,
        ContentType: mimeType,
        ServerSideEncryption: process.env.S3_ENDPOINT ? undefined : "AES256",
      }),
    );
    return `s3:${key}`;
  }
  const blob = await prisma.fileBlob.create({ data: { data }, select: { id: true } });
  return `db:${blob.id}`;
}

export async function getFile(storageKey: string): Promise<Buffer> {
  const [kind, ...rest] = storageKey.split(":");
  const id = rest.join(":");
  if (kind === "s3") {
    const { GetObjectCommand } = await import("@aws-sdk/client-s3");
    const out = await (await s3()).send(new GetObjectCommand({ Bucket: bucket(), Key: id }));
    return Buffer.from(await out.Body!.transformToByteArray());
  }
  const blob = await prisma.fileBlob.findUnique({ where: { id } });
  if (!blob) throw new Error("Stored file is missing.");
  return Buffer.from(blob.data);
}
