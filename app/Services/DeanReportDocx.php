<?php

namespace App\Services;

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Table as TableStyle;

/** The Dean report as an editable Word document (same content as the PDF). Returns the .docx bytes. */
class DeanReportDocx
{
    public function render(array $d, ?string $logoPath): string
    {
        $w = new PhpWord();
        $w->setDefaultFontName('Calibri');
        $w->setDefaultFontSize(10);
        $w->addTitleStyle(1, ['bold' => true, 'size' => 15, 'color' => '006699'], ['spaceBefore' => 240, 'spaceAfter' => 80]);
        $w->addTitleStyle(2, ['bold' => true, 'size' => 11.5, 'color' => '1D1D1F'], ['spaceBefore' => 160, 'spaceAfter' => 60]);
        $sec = $w->addSection(['marginLeft' => 900, 'marginRight' => 900, 'marginTop' => 800, 'marginBottom' => 800]);
        $m = $d['meta'];

        if ($logoPath && is_file($logoPath)) {
            $sec->addImage($logoPath, ['height' => 48, 'alignment' => Jc::LEFT]);
        }
        $sec->addText($m['institution'], ['size' => 9, 'color' => '6E6E73']);
        $sec->addText('Assessment moderation overview', ['bold' => true, 'size' => 20], ['spaceAfter' => 40]);
        $sec->addText('Report for: '.$m['to_whom'], ['size' => 11]);
        $sec->addText(implode('  ·  ', array_filter([$m['department'] ? 'Department: '.$m['department'] : null, $m['period'], $m['qualification'], $m['kind']])), ['size' => 9, 'color' => '6E6E73']);
        $sec->addText('Prepared by '.$m['hod'].' (Head of Department) · generated '.$m['generated'], ['size' => 9, 'color' => '6E6E73']);
        if ($m['comment'] !== '') {
            $sec->addTitle('Head of Department’s comment', 2);
            foreach (preg_split('/\R/', $m['comment']) as $line) {
                $sec->addText($line);
            }
        }

        $para = function (string $k) use ($sec, $d) {
            foreach (preg_split('/\R+/', (string) ($d['narrative'][$k] ?? '')) as $line) {
                if (trim($line) !== '') {
                    $sec->addText($line, ['size' => 10.5], ['spaceAfter' => 100, 'alignment' => Jc::BOTH]);
                }
            }
        };
        $para('summary');
        $h = $d['headline'];
        $sec->addTitle('1. At a glance (numbers only)', 1);
        $this->table($sec, ['Qualification code', 'Subjects', 'Assessments', 'Completed', 'Pending', 'In process', 'Overdue', 'No moderator', 'No examiner'],
            array_merge(array_map(fn ($x) => [$x['code'], $x['subjects'], $x['assessments'], $x['completed'], $x['pending'], $x['in_process'], $x['overdue'], $x['no_moderator'], $x['no_examiner']], $d['by_code']),
                [['Total', $h['subjects'], $h['assessments'], $h['completed'], $h['pending'], $h['in_process'], $h['overdue'], count($h['no_moderator']), count($h['no_examiner'])]]));
        $this->table($sec, ['Term', 'Assessments', 'Completed', 'Pending', 'In process', 'Overdue'], array_map(fn ($x) => [$x['label'], $x['assessments'], $x['completed'], $x['pending'], $x['in_process'], $x['overdue']], $d['by_term']));
        $sec->addText('Terms follow the month of the assessment date (Term 1 Jan–Mar, Term 2 Apr–Jun, Term 3 Jul–Sep, Term 4 Oct–Dec). Pending = waiting on a moderator or signatory; in process = with the examiner.', ['size' => 8, 'color' => '6E6E73']);

        $c = $d['compliance'];
        $sec->addTitle('2. Moderation compliance', 1);
        $para('compliance');
        $this->table($sec, ['Measure', 'Result'], [
            ['Moderated before the students wrote (Yes / No / not yet recorded)', "{$c['pre_yes']} / {$c['pre_no']} / {$c['pre_unknown']}"],
            ['Completed on time (within the post-moderation window) / late', "{$c['on_time']} / {$c['late']}"],
            ['Records needing revision after pre-moderation', (string) $c['needing_revision']],
            ['Average number of revisions per submitted record', $c['avg_revisions'] !== null ? (string) $c['avg_revisions'] : '—'],
        ]);
        if ($c['late_list']) {
            $sec->addText('Late: '.implode('; ', $c['late_list']), ['size' => 9]);
        }

        $q = $d['quality'];
        $sec->addTitle('3. Assessment quality (internal moderators’ ratings, 0 = poor, 1 = adequate, 2 = good)', 1);
        $para('quality');
        $this->table($sec, ['Area', 'Average (of 2)', 'Rated', 'Rated poor'], array_map(fn ($a) => [$a['label'], $a['avg'] ?? '—', $a['n'], $a['poor']], $q['areas']));
        $this->table($sec, ['Moderator’s check', 'Yes', 'No'], array_map(fn ($a) => [$a['label'], $a['yes'], $a['no']], $q['yn']));
        if ($q['poor']) {
            $sec->addTitle('Rated poor in at least one area', 2);
            $this->lines($sec, array_map(fn ($p) => $p['name'].': '.implode(', ', $p['areas']), $q['poor']));
        }

        $r = $d['results'];
        $sec->addTitle('4. Student results (anonymous, from the marks submitted)', 1);
        $para('results');
        $sec->addText("Overall: {$r['overall']['assessments']} assessment(s), {$r['overall']['candidates']} candidate results".($r['overall']['pass_rate'] !== null ? ", pass rate {$r['overall']['pass_rate']}%." : '.'));
        foreach ([['By qualification code', $r['by_qualification']], ['By term', $r['by_term']], ['By year level', $r['by_level']]] as [$title, $set]) {
            if ($set) {
                $sec->addTitle($title, 2);
                $this->table($sec, [$title === 'By year level' ? 'Year level' : ($title === 'By term' ? 'Term' : 'Qualification code'), 'Assessments', 'Candidates', 'Pass rate', 'Class average', 'Absent'],
                    array_map(fn ($x) => [$x['name'], $x['assessments'], $x['candidates'], $x['pass_rate'] !== null ? $x['pass_rate'].'%' : '—', $x['average'].'%', $x['absent']], $set));
            }
        }
        if ($r['low']) {
            $sec->addTitle('Pass rate below 50%', 2);
            $this->table($sec, ['Assessment', 'Pass rate', 'Candidates'], array_map(fn ($x) => [$x['name'], $x['pass_rate'].'%', $x['candidates']], $r['low']));
        }
        if ($r['drops']) {
            $sec->addTitle('Pass rate fell by 10 points or more since the previous assessment', 2);
            $this->lines($sec, array_map(fn ($x) => $x['name'].': '.$x['text'], $r['drops']));
        }
        if ($r['subminimum']['assessments']) {
            $s = $r['subminimum'];
            $sec->addText("Subminimum: {$s['assessments']} assessment(s) with a subminimum; {$s['not_met']} of {$s['students']} students did not meet it, {$s['passed_below']} of them having reached the pass mark.");
        }

        $e = $d['exceptions'];
        $sec->addTitle('5. Matters for attention', 1);
        $para('attention');
        $sec->addTitle('General mark adjustments recommended ('.count($e['adjustments']).')', 2);
        $this->lines($sec, $e['adjustments'] ? array_map(fn ($x) => $x['name'].($x['why'] !== '' ? ': '.$x['why'] : ''), $e['adjustments']) : ['None.']);
        $sec->addTitle('Overdue ('.count($e['overdue']).')', 2);
        $this->lines($sec, $e['overdue'] ? array_map(fn ($x) => "{$x['name']} — {$x['status']} (examiner: {$x['examiner']})", $e['overdue']) : ['None.']);
        $sec->addTitle('Subjects without an internal moderator ('.count($h['no_moderator']).')', 2);
        $this->lines($sec, $h['no_moderator'] ?: ['None.']);
        $sec->addTitle('Subjects without an examiner ('.count($h['no_examiner']).')', 2);
        $this->lines($sec, $h['no_examiner'] ?: ['None.']);
        $sec->addTitle('External moderators’ recommendations ('.count($e['external']).')', 2);
        $this->lines($sec, $e['external'] ? array_map(fn ($x) => $x['name'].': '.$x['text'], $e['external']) : ['None recorded.']);

        $sec->addTitle('6. What examiners will do differently next time', 1);
        $para('actions');
        $this->lines($sec, $d['actions'] ? array_map(fn ($x) => $x['name'].': '.$x['text'], $d['actions']) : ['None recorded.']);

        $sec->addTitle('Appendix: all assessments by term ('.count($d['appendix']).')', 1);
        $byTerm = collect($d['appendix'])->groupBy('term');
        foreach ([1, 2, 3, 4, 0] as $tn) {
            $rowsT = $byTerm->get($tn, collect());
            if ($tn === 0 && $rowsT->isEmpty()) {
                continue;
            }
            $sec->addTitle(($tn ? 'Term '.$tn : 'No assessment date').' · '.$rowsT->count().' assessment(s)', 2);
            $this->table($sec, ['Subject', 'Assessment', 'Date', 'Status', 'Examiner', 'Moderator', 'Cand.', 'Pass %', 'Adj.'],
                $rowsT->map(fn ($x) => [$x['code'].' '.$x['subject'], $x['title'], $x['date'] ?? '—', $x['status'].($x['overdue'] ? ' (overdue)' : ''), $x['examiner'], $x['moderator'], $x['candidates'] ?? '—', $x['pass_rate'] !== null ? $x['pass_rate'] : '—', $x['adjust'] ? 'Yes' : 'No'])->all(), 8);
        }

        $sec->addTextBreak(2);
        $sec->addText('Head of Department: ______________________________        Date: ________________', ['size' => 10]);
        $sec->addText($m['hod'], ['size' => 9, 'color' => '6E6E73']);

        $tmp = tempnam(sys_get_temp_dir(), 'dean').'.docx';
        \PhpOffice\PhpWord\IOFactory::createWriter($w, 'Word2007')->save($tmp);
        $bytes = file_get_contents($tmp);
        @unlink($tmp);

        return $bytes;
    }

    private function lines($sec, array $lines): void
    {
        foreach (array_filter($lines, fn ($l) => $l !== null && $l !== '') as $l) {
            $sec->addListItem((string) $l, 0, ['size' => 9.5]);
        }
    }

    private function table($sec, array $head, array $rows, float $size = 9): void
    {
        $t = $sec->addTable(['borderSize' => 4, 'borderColor' => 'C7C7CC', 'cellMargin' => 50, 'layout' => TableStyle::LAYOUT_AUTO]);
        $t->addRow();
        foreach ($head as $h) {
            $t->addCell(null, ['bgColor' => 'E8F1F7'])->addText((string) $h, ['bold' => true, 'size' => $size]);
        }
        foreach ($rows as $row) {
            $t->addRow();
            foreach ($row as $cell) {
                $t->addCell()->addText((string) ($cell ?? ''), ['size' => $size]);
            }
        }
        $sec->addTextBreak(0);
    }
}
