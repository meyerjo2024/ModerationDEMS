<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\Subject;
use App\Services\Audit;
use App\Services\DeanReport;
use App\Services\DeanReportDocx;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;

/** The Head of Department's overview report for the Dean — on screen, as PDF, or as an editable Word document. Available at any time. */
class DeanReportController extends Controller
{
    public function show(Request $request, DeanReport $report)
    {
        $f = $this->filters($request);

        return view('overview.report', [
            'd' => $report->build($request->user(), $f), 'f' => $f, 'query' => $request->query(),
            'years' => Assessment::whereIn('subject_id', Subject::where('hod_id', $request->user()->id)->pluck('id'))->whereNotNull('academic_year')->distinct()->orderByDesc('academic_year')->pluck('academic_year'),
            'quals' => Subject::where('hod_id', $request->user()->id)->get()->flatMap->qualificationList()->unique()->sort()->values(),
        ]);
    }

    public function pdf(Request $request, DeanReport $report, Audit $audit)
    {
        $d = $report->build($request->user(), $this->filters($request));
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->setPaper('A4');
        $pdf->loadHtml(view('pdf.dean-report', ['d' => $d, 'logo' => $this->logo()])->render());
        $pdf->render();
        $audit->log('DEAN_REPORT_DOWNLOADED', null, $request->user()->id, ['format' => 'pdf', 'assessments' => $d['headline']['assessments']], $request->ip());

        return response($pdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$this->name().'.pdf"', 'Cache-Control' => 'private, no-store']);
    }

    public function docx(Request $request, DeanReport $report, DeanReportDocx $docx, Audit $audit)
    {
        $d = $report->build($request->user(), $this->filters($request));
        $logo = config('dems.logo') ? base_path(config('dems.logo')) : null;
        $bytes = $docx->render($d, $logo);
        $audit->log('DEAN_REPORT_DOWNLOADED', null, $request->user()->id, ['format' => 'docx', 'assessments' => $d['headline']['assessments']], $request->ip());

        return response($bytes, 200, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'Content-Disposition' => 'attachment; filename="'.$this->name().'.docx"', 'Cache-Control' => 'private, no-store']);
    }

    private function filters(Request $r): array
    {
        $date = fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;

        return ['year' => $r->integer('year') ?: null, 'qualification' => trim((string) $r->query('qualification', '')), 'kind' => (string) $r->query('kind', 'all'),
            'from' => $date($r->query('from')), 'to' => $date($r->query('to')), 'to_whom' => mb_substr(trim((string) $r->query('to_whom', '')), 0, 120), 'comment' => mb_substr(trim((string) $r->query('comment', '')), 0, 3000)];
    }

    private function name(): string
    {
        return 'Moderation-overview-'.now('UTC')->format('Y-m-d');
    }

    private function logo(): ?string
    {
        $path = config('dems.logo') ? base_path(config('dems.logo')) : null;
        if (! $path || ! is_file($path)) {
            return null;
        }

        return 'data:'.(str_ends_with(strtolower($path), 'png') ? 'image/png' : 'image/jpeg').';base64,'.base64_encode(file_get_contents($path));
    }
}
