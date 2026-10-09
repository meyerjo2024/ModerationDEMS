<?php

namespace App\Services;

use App\Enums\AttachmentKind;
use App\Enums\ModerationStage;
use App\Enums\SignatureSection;
use App\Models\Assessment;
use Dompdf\Dompdf;
use Dompdf\Options;

/** Builds and renders the audit-compliant moderation report PDF. */
class ReportService
{
    public function __construct(private StatisticsCalculator $calc) {}

    /** @return array<string, mixed> */
    public function data(Assessment $a): array
    {
        $a->load(['subject.hod', 'examiner', 'internalModerator', 'externalModerator', 'records.reviewer', 'signatures.user', 'attachments']);

        $docs = [];
        foreach ([AttachmentKind::Paper, AttachmentKind::Memo, AttachmentKind::Marks] as $kind) {
            if ($att = $a->latestAttachment($kind)) {
                $docs[] = ['label' => $kind->label(), 'filename' => $att->filename, 'sha' => $att->sha256, 'at' => $att->created_at];
            }
        }

        $reviews = [];
        foreach (ModerationStage::cases() as $stage) {
            $r = $a->records->where('stage', $stage)->where('decision', 'APPROVED')->last();
            if ($r) {
                $checks = collect($r->checklist ?? [])->filter(fn ($c) => isset(config('dems.quality_checks')[$c['id'] ?? '']))->values()->all();
                $reviews[] = ['label' => $stage->label(), 'reviewer' => $r->reviewer->name, 'consensus' => $r->consensus_reached,
                    'comments' => $r->comments, 'scripts' => $r->scripts_sampled, 'checks' => $checks, 'at' => $r->created_at];
            }
        }

        $signatures = [];
        foreach (SignatureSection::cases() as $section) {
            $s = $a->signatures->where('section', $section)->last();
            if ($s) {
                $signatures[] = ['label' => $section->label(), 'name' => $s->user->name, 'role' => $s->user->role->label(),
                    'at' => $s->signed_at, 'hash' => $s->content_hash, 'image' => $s->image_data];
            }
        }

        $stats = null;
        if ($a->scores && $a->total_marks) {
            $c = $this->calc->calculate($a->scores, $a->total_marks);
            $stats = [
                'total_marks' => $c['total_marks'], 'pass_mark' => $c['pass_mark'],
                'candidates' => $a->candidate_count, 'passed' => $a->pass_count, 'pass_rate' => $a->pass_rate,
                'highest' => $a->highest_mark, 'lowest' => $a->lowest_mark, 'average' => $a->class_average,
                'invalid' => $a->invalid_entries ?? 0, 'distribution' => $c['distribution'], 'source' => $a->marks_source,
            ];
        }

        $audit = $a->auditLogs()->get();

        return [
            'institution' => config('dems.institution'),
            'reportId' => $a->id,
            'generatedAt' => now('UTC'),
            'subject' => $a->subject,
            'number' => $a->number,
            'examiner' => $a->examiner->name,
            'internal' => $a->internalModerator->name,
            'external' => $a->externalModerator?->name,
            'hod' => $a->subject->hod->name,
            'questionTypes' => $a->question_types ?? [],
            'docs' => $docs,
            'stats' => $stats,
            'commentary' => $a->examiner_commentary,
            'reviews' => $reviews,
            'signatures' => $signatures,
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
        $pdf->setPaper('A4');
        $pdf->render();

        $canvas = $pdf->getCanvas();
        $font = $pdf->getFontMetrics()->getFont('DejaVu Sans');
        $w = $canvas->get_width();
        $canvas->page_text($w - 190, $canvas->get_height() - 32, "{$data['subject']->code} {$data['number']} · Page {PAGE_NUM} of {PAGE_COUNT}", $font, 7.5, [0.43, 0.43, 0.45]);
        $canvas->page_text(52, $canvas->get_height() - 32, $data['institution'], $font, 7.5, [0.43, 0.43, 0.45]);

        return $pdf->output();
    }
}
