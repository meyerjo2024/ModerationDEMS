<?php

namespace App\Services;

use Illuminate\Validation\Rule;

/** Validation rules and normalisation for the official moderation form (wording lives in config/moderation_form.php). */
class ModerationForm
{
    /** @return array<string, list<mixed>> rules keyed under $p (e.g. "s1") */
    public static function rules(string $part, string $p, bool $draft = false): array
    {
        $req = $draft ? 'nullable' : 'required';
        $text = fn (int $max = 3000) => [$req, 'string', 'max:'.$max];
        $yn = [$req, Rule::in(['YES', 'NO'])];

        switch ($part) {
            case 's1_examiner':
                $r = [
                    $p => [$req, 'array'],
                    "$p.period" => [$req, Rule::in(array_keys(config('moderation_form.periods')))],
                    "$p.year" => [$req, 'integer', 'between:2000,2100'],
                    "$p.heqf_level" => [$req, 'integer', 'between:5,10'],
                    "$p.subject_level" => $text(40),
                    "$p.qualification" => $text(160),
                    "$p.qualification_code" => $text(40),
                    "$p.assessment_date" => [$req, 'date'],
                    "$p.weights" => [$req, 'array'],
                ];
                foreach (array_keys(config('moderation_form.question_types')) as $k) {
                    $r["$p.weights.$k"] = ['nullable', 'numeric', 'between:0,100'];
                }

                return $r;

            case 's1_moderator':
                $r = [$p => ['required', 'array'], "$p.ratings" => ['required', 'array'], "$p.questions" => ['required', 'array']];
                foreach (array_keys(config('moderation_form.ratings')) as $k) {
                    $r["$p.ratings.$k"] = ['required', Rule::in([0, 1, 2])];
                }
                foreach (array_keys(config('moderation_form.s1_questions')) as $k) {
                    $r["$p.questions.$k.answer"] = ['required', Rule::in(['YES', 'NO'])];
                    $r["$p.questions.$k.comment"] = ['nullable', 'string', 'max:2000'];
                }

                return $r;

            case 's2_examiner':
                $r = [
                    $p => ['required', 'array'],
                    "$p.registered" => ['required', 'integer', 'min:0', 'max:100000'],
                    "$p.type_of_assessment" => ['required', 'string', 'max:80'],
                    "$p.answers" => ['required', 'array'],
                ];
                foreach (array_keys(config('moderation_form.s2_examiner_questions')) as $k) {
                    $r["$p.answers.$k"] = ['required', 'string', 'max:3000'];
                }

                return $r;

            case 's2_moderator':
                $r = [$p => ['required', 'array'], "$p.answers" => ['required', 'array'], "$p.items" => ['required', 'array'], "$p.adjustments" => ['required', 'array']];
                foreach (array_keys(config('moderation_form.s2_moderator_questions')) as $k) {
                    $r["$p.answers.$k"] = ['required', 'string', 'max:3000'];
                }
                foreach (array_keys(config('moderation_form.comment_items')) as $k) {
                    $r["$p.items.$k"] = ['required', 'string', 'max:3000'];
                }

                return $r + self::adjustmentRules($p);

            case 's3_external':
                $r = [$p => ['required', 'array'], "$p.items" => ['required', 'array'], "$p.adjustments" => ['required', 'array']];
                foreach (array_keys(config('moderation_form.comment_items') + config('moderation_form.s3_extra_items')) as $k) {
                    $r["$p.items.$k"] = ['required', 'string', 'max:3000'];
                }

                return $r + self::adjustmentRules($p);
        }

        return [];
    }

    private static function adjustmentRules(string $p): array
    {
        return [
            "$p.adjustments.recommended" => ['required', Rule::in(['YES', 'NO'])],
            "$p.adjustments.specify" => ["required_if:$p.adjustments.recommended,YES", 'nullable', 'string', 'max:2000'],
        ];
    }

    /** Examiner's Section 1 data in a stable shape (also used for the signed content hash). */
    public static function s1Examiner(array $in): array
    {
        $weights = [];
        foreach (array_keys(config('moderation_form.question_types')) as $k) {
            $weights[$k] = round((float) ($in['weights'][$k] ?? 0), 2) + 0;
        }

        return [
            'period' => $in['period'] ?? null,
            'year' => isset($in['year']) ? (int) $in['year'] : null,
            'heqf_level' => isset($in['heqf_level']) ? (int) $in['heqf_level'] : null,
            'subject_level' => trim((string) ($in['subject_level'] ?? '')),
            'qualification' => trim((string) ($in['qualification'] ?? '')),
            'qualification_code' => trim((string) ($in['qualification_code'] ?? '')),
            'assessment_date' => $in['assessment_date'] ?? null,
            'weights' => $weights,
        ];
    }

    public static function s1Moderator(array $in): array
    {
        $ratings = [];
        foreach (array_keys(config('moderation_form.ratings')) as $k) {
            $ratings[$k] = (int) $in['ratings'][$k];
        }
        $questions = [];
        foreach (array_keys(config('moderation_form.s1_questions')) as $k) {
            $questions[$k] = ['answer' => $in['questions'][$k]['answer'], 'comment' => trim((string) ($in['questions'][$k]['comment'] ?? ''))];
        }

        return ['ratings' => $ratings, 'questions' => $questions];
    }

    public static function s2Examiner(array $in): array
    {
        $answers = [];
        foreach (array_keys(config('moderation_form.s2_examiner_questions')) as $k) {
            $answers[$k] = trim((string) $in['answers'][$k]);
        }

        return ['registered' => (int) $in['registered'], 'type_of_assessment' => trim((string) $in['type_of_assessment']), 'answers' => $answers];
    }

    public static function s2Moderator(array $in): array
    {
        $answers = [];
        foreach (array_keys(config('moderation_form.s2_moderator_questions')) as $k) {
            $answers[$k] = trim((string) $in['answers'][$k]);
        }

        return ['answers' => $answers, 'items' => self::items($in['items'], config('moderation_form.comment_items')), 'adjustments' => self::adjustments($in['adjustments'])];
    }

    public static function s3External(array $in): array
    {
        return ['items' => self::items($in['items'], config('moderation_form.comment_items') + config('moderation_form.s3_extra_items')), 'adjustments' => self::adjustments($in['adjustments'])];
    }

    private static function items(array $in, array $spec): array
    {
        $out = [];
        foreach (array_keys($spec) as $k) {
            $out[$k] = trim((string) $in[$k]);
        }

        return $out;
    }

    private static function adjustments(array $in): array
    {
        return ['recommended' => $in['recommended'], 'specify' => $in['recommended'] === 'YES' ? trim((string) ($in['specify'] ?? '')) : ''];
    }
}
