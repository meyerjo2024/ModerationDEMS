<?php

namespace App\Services;

use App\Enums\AttachmentKind;
use App\Enums\SignatureSection as Sig;
use App\Models\Assessment;
use Dompdf\Dompdf;
use Dompdf\Options;

/** Builds and renders the official "Appendix 2: Comprehensive Moderation Report" PDF. */
class ReportService
{
    /** @return array<string, mixed> */
    public function data(Assessment $a): array
    {
        $a->load(['subject.hod', 'examiner', 'internalModerator', 'externalModerator', 'records.reviewer', 'signatures.user', 'attachments']);

        // Latest signature per section → what is printed in the Name / Signature / Date rows.
        $sig = function (Sig $section) use ($a) {
            $s = $a->signatures->where('section', $section)->last();

            return $s ? ['name' => $s->user->name, 'image' => $s->image_data, 'date' => $s->signed_at->utc()->format('d/m/Y'), 'hash' => $s->content_hash] : null;
        };
        $external = $sig(Sig::ExternalModerator);
        $s1e = $a->s1_examiner ?? [];
        $s2e = $a->s2_examiner ?? [];
        $registered = $s2e['registered'] ?? $a->enrolled_count ?? $a->candidate_count;

        $docs = [];
        foreach ([AttachmentKind::Paper, AttachmentKind::Memo, AttachmentKind::Marks] as $kind) {
            if ($att = $a->latestAttachment($kind)) {
                $docs[] = ['label' => $kind->label(), 'filename' => $att->filename, 'sha' => $att->sha256, 'at' => $att->created_at];
            }
        }
        $audit = $a->auditLogs()->get();

        return [
            'institution' => config('dems.institution'),
            'logo' => $this->logo(),
            'reportId' => $a->id,
            'generatedAt' => now('UTC'),
            'form' => config('moderation_form'),
            'subject' => $a->subject,
            'number' => $a->number,
            'names' => ['examiner' => $a->examiner->name, 'internal' => $a->internalModerator->name, 'external' => $a->externalModerator?->name, 'hod' => $a->subject->hod->name],
            'hasExternal' => (bool) $a->external_moderator_id,
            's1e' => $s1e,
            's1m' => $a->s1_moderator ?? [],
            's2e' => $s2e,
            's2m' => $a->s2_moderator ?? [],
            's3' => $a->s3_external ?? [],
            'stats' => [
                'registered' => $registered,
                'absent' => $registered !== null && $a->candidate_count !== null ? max(0, $registered - $a->candidate_count) : null,
                'candidates' => $a->candidate_count,
                'highest' => $a->highest_mark,
                'passes' => $a->pass_count,
                'pass_rate' => $a->pass_rate,
                'average' => $a->class_average,
                'invalid' => $a->invalid_entries,
                'source' => $a->marks_source,
                'weight' => $a->test_weight,
            ],
            'sig' => [
                's1' => ['examiner' => $sig(Sig::ExaminerSection1), 'internal' => $sig(Sig::PreModerator), 'external' => $external],
                's2' => ['examiner' => $sig(Sig::ExaminerSection2), 'internal' => $sig(Sig::FinalInternalModerator), 'external' => $external],
                's3' => ['external' => $external, 'examiner' => $sig(Sig::ExaminerSection3), 'hod' => $sig(Sig::HodSection3)],
            ],
            'docs' => $docs,
            'auditCount' => $audit->count(),
            'auditHead' => $audit->last()?->hash,
        ];
    }

    public function render(Assessment $a): string
    {
        $data = $this->data($a);
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('pdf.report', $data)->render());
        $pdf->setPaper(config('dems.paper', 'a4'));
        $pdf->render();

        $canvas = $pdf->getCanvas();
        $font = $pdf->getFontMetrics()->getFont('DejaVu Sans');
        $w = $canvas->get_width();
        $h = $canvas->get_height();
        $grey = [0.43, 0.43, 0.45];
        $canvas->page_text($w - 205, $h - 24, "{$data['subject']->code} {$data['number']} · Page {PAGE_NUM} of {PAGE_COUNT}", $font, 7, $grey);
        $canvas->page_text(30, $h - 24, $data['institution'].' · Appendix 2: Comprehensive Moderation Report', $font, 7, $grey);

        return $pdf->output();
    }

    private function logo(): ?string
    {
        $path = config('dems.logo') ? base_path(config('dems.logo')) : null;
        if (! $path || ! is_file($path)) {
            return null;
        }
        $mime = str_ends_with(strtolower($path), 'png') ? 'image/png' : 'image/jpeg';

        return "data:{$mime};base64,".base64_encode(file_get_contents($path));
    }
}
