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
            'd' => $this->withNarrative($report->build($request->user(), $f), $request), 'f' => $f, 'groq' => app(\App\Services\GroqClient::class)->configured(), 'sections' => \App\Services\DeanNarrative::SECTIONS, 'query' => $request->query(),
            'years' => Assessment::whereIn('subject_id', Subject::where('hod_id', $request->user()->id)->pluck('id'))->whereNotNull('academic_year')->distinct()->orderByDesc('academic_year')->pluck('academic_year'),
            'quals' => Subject::where('hod_id', $request->user()->id)->get()->flatMap->qualificationList()->unique()->sort()->values(),
        ]);
    }

    /** Write the narrative: with Groq ('ai') or as a plain draft from the figures ('draft'). Nothing is saved until the HOD saves it. */
    public function narrative(Request $request, DeanReport $report, \App\Services\DeanNarrative $narrative, Audit $audit)
    {
        $d = $report->build($request->user(), $this->filters($request));
        if ($request->input('mode') !== 'ai') {
            return response()->json(['sections' => $narrative->draft($d)]);
        }
        if ($d['headline']['assessments'] === 0) {
            return response()->json(['message' => 'There are no assessments in this selection to write about.'], 422);
        }
        try {
            $sections = $narrative->generate($d);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'The AI writer could not be reached. Please try again, or use the quick draft.'], 422);
        }
        $audit->log('DEAN_NARRATIVE_GENERATED', null, $request->user()->id, ['provider' => 'groq', 'model' => config('dems.groq.model'), 'assessments' => $d['headline']['assessments']], $request->ip());

        return response()->json(['sections' => $sections]);
    }

    public function saveNarrative(Request $request)
    {
        $in = (array) $request->input('sections', []);
        $clean = [];
        foreach (array_keys(\App\Services\DeanNarrative::SECTIONS) as $k) {
            $clean[$k] = mb_substr(trim((string) ($in[$k] ?? '')), 0, 4000);
        }
        $request->session()->put('dean_narrative', array_filter($clean) ?: null);
        if (! array_filter($clean)) {
            $request->session()->forget('dean_narrative');
        }

        return response()->json(['ok' => true]);
    }

    public function pdf(Request $request, DeanReport $report, Audit $audit)
    {
        $d = $this->withNarrative($report->build($request->user(), $this->filters($request)), $request);
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
        $d = $this->withNarrative($report->build($request->user(), $this->filters($request)), $request);
        $logo = config('dems.logo') ? base_path(config('dems.logo')) : null;
        $bytes = $docx->render($d, $logo);
        $audit->log('DEAN_REPORT_DOWNLOADED', null, $request->user()->id, ['format' => 'docx', 'assessments' => $d['headline']['assessments']], $request->ip());

        return response($bytes, 200, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'Content-Disposition' => 'attachment; filename="'.$this->name().'.docx"', 'Cache-Control' => 'private, no-store']);
    }

    private function withNarrative(array $d, Request $r): array
    {
        // text sent with the download (what is on screen) wins; otherwise the narrative saved for this session
        $sent = collect((array) $r->input('sections', []))->only(array_keys(\App\Services\DeanNarrative::SECTIONS))->map(fn ($v) => mb_substr(trim((string) $v), 0, 4000))->filter()->all();
        $d['narrative'] = $sent ?: (array) $r->session()->get('dean_narrative', []);

        return $d;
    }

    private function filters(Request $r): array
    {
        $date = fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;

        return ['year' => $r->integer('year') ?: null, 'qualification' => trim((string) $r->input('qualification', '')), 'kind' => (string) $r->input('kind', 'all'),
            'from' => $date($r->input('from')), 'to' => $date($r->input('to')), 'to_whom' => mb_substr(trim((string) $r->input('to_whom', '')), 0, 120), 'comment' => mb_substr(trim((string) $r->input('comment', '')), 0, 3000)];
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
