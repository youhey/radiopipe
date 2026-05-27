<?php

namespace App\Topics\Screening;

use App\Models\TopicScreeningKeywordRule;
use App\Topics\TopicDraft;
use Carbon\CarbonImmutable;

/**
 * TopicDraft を低コストな deterministic screening で評価
 */
class TopicScreeningEvaluator
{
    private TopicScreeningKeywordRuleProvider $keywordRuleProvider;

    /**
     * Constructor.
     */
    public function __construct(?TopicScreeningKeywordRuleProvider $keywordRuleProvider = null)
    {
        $this->keywordRuleProvider = $keywordRuleProvider ?? app(TopicScreeningKeywordRuleProvider::class);
    }

    /**
     * TopicDraft を Stage 1 screening で評価
     *
     * @param TopicDraft $draft
     * @param list<string> $seenUrls
     * @param CarbonImmutable|null $now
     *
     * @return TopicScreeningEvaluation
     */
    public function evaluate(TopicDraft $draft, array $seenUrls = [], ?CarbonImmutable $now = null): TopicScreeningEvaluation
    {
        $current = $now ?? CarbonImmutable::now('UTC');
        $reasons = [];

        $isDuplicateUrl = $draft->url !== null && in_array($draft->url, $seenUrls, true);
        $freshnessScore = $this->freshnessScore($draft, $current, $reasons);
        $importanceScore = $this->importanceScore($draft, $reasons);
        $confidenceScore = $this->confidenceScore($draft, $reasons);
        $contentTypeScore = $this->contentTypeScore($draft, $reasons);
        $keywordRules = $this->keywordRuleProvider->activeRules();
        $limitationPenalty = $this->limitationPenalty($draft, $keywordRules['limitation'], $reasons);
        $selectionBonus = $this->selectionBonus($draft, $reasons);
        $sensitiveAssessment = $this->sensitiveAssessment($draft, $keywordRules['sensitive'], $reasons);
        $isSensitive = $sensitiveAssessment['is_sensitive'];
        $rejectSensitive = $sensitiveAssessment['reject_sensitive'];
        $isUncertain = $confidenceScore < $this->intConfig('radiopipe.topic_screening.thresholds.uncertain_confidence_score', 45)
            || $limitationPenalty >= $this->intConfig('radiopipe.topic_screening.thresholds.strong_limitation_penalty', 30);

        if ($isDuplicateUrl) {
            $reasons[] = 'duplicate URL';
        }

        if ($isUncertain) {
            $reasons[] = 'topic is uncertain';
        }

        $weightedScore = $freshnessScore * $this->floatConfig('radiopipe.topic_screening.weights.freshness', 0.25)
            + $importanceScore * $this->floatConfig('radiopipe.topic_screening.weights.importance', 0.35)
            + $confidenceScore * $this->floatConfig('radiopipe.topic_screening.weights.confidence', 0.25)
            + $contentTypeScore * $this->floatConfig('radiopipe.topic_screening.weights.content_type', 0.15)
            - $limitationPenalty
            + $selectionBonus;

        $screeningScore = $this->clampInt((int) round($weightedScore), 0, 100);
        $lowValueThreshold = $this->intConfig('radiopipe.topic_screening.thresholds.low_value_score', 45);
        $screeningStatus = $this->screeningStatus($isDuplicateUrl, $rejectSensitive, $isUncertain, $screeningScore, $lowValueThreshold);

        if ($screeningScore < $lowValueThreshold) {
            $reasons[] = 'screening score is below threshold';
        }

        return new TopicScreeningEvaluation(
            screeningStatus: $screeningStatus,
            screeningScore: $screeningScore,
            signals: [
                'is_duplicate_url' => $isDuplicateUrl,
                'freshness_score' => $freshnessScore,
                'upstream_importance_score' => $importanceScore,
                'upstream_confidence_score' => $confidenceScore,
                'content_type_score' => $contentTypeScore,
                'limitation_penalty' => $limitationPenalty,
                'selection_bonus' => $selectionBonus,
            ],
            flags: [
                'is_duplicate' => $isDuplicateUrl,
                'is_uncertain' => $isUncertain,
                'is_sensitive' => $isSensitive,
            ],
            reasons: array_values(array_unique($reasons)),
        );
    }

    /**
     * published_at から freshness score を算出して返す
     *
     * @param TopicDraft $draft
     * @param CarbonImmutable $now
     * @param list<string> $reasons
     *
     * @return int
     */
    private function freshnessScore(TopicDraft $draft, CarbonImmutable $now, array &$reasons): int
    {
        if (! $draft->publishedAt instanceof CarbonImmutable) {
            $reasons[] = 'published time is missing';

            return 10;
        }

        $hours = ($now->getTimestamp() - $draft->publishedAt->getTimestamp()) / 3600;

        if ($hours <= 6) {
            $reasons[] = 'article is fresh';

            return 100;
        }

        if ($hours <= 24) {
            $reasons[] = 'article is recent';

            return 85;
        }

        if ($hours <= 72) {
            return 60;
        }

        if ($hours <= 168) {
            $reasons[] = 'article is old';

            return 30;
        }

        $reasons[] = 'article is stale';

        return 10;
    }

    /**
     * Digestpipe Importance を Trusted Scale から score へ変換して返す
     *
     * @param TopicDraft $draft
     * @param list<string> $reasons
     *
     * @return int
     */
    private function importanceScore(TopicDraft $draft, array &$reasons): int
    {
        $importance = is_int($draft->importance) ? $draft->importance : null;
        $map = $this->intMapConfig('radiopipe.topic_screening.importance_scores', [
            5 => 100,
            4 => 80,
            3 => 60,
            2 => 30,
            1 => 10,
        ]);
        $score = $importance !== null ? ($map[$importance] ?? null) : null;

        if ($score === null) {
            return 40;
        }

        if ($importance >= 4) {
            $reasons[] = 'digestpipe importance is high';
        } elseif ($importance <= 2) {
            $reasons[] = 'digestpipe importance is low';
        }

        return $score;
    }

    /**
     * Digestpipe Confidence を Trusted Scale から score へ変換して返す
     *
     * @param TopicDraft $draft
     * @param list<string> $reasons
     *
     * @return int
     */
    private function confidenceScore(TopicDraft $draft, array &$reasons): int
    {
        if ($draft->confidence === null || $draft->confidence < 0.0 || $draft->confidence > 1.0) {
            return 40;
        }

        $score = $this->clampInt((int) round($draft->confidence * 100), 0, 100);

        if ($score >= 90) {
            $reasons[] = 'source confidence is high';
        } elseif ($score < 45) {
            $reasons[] = 'digestpipe confidence is low';
        }

        return $score;
    }

    /**
     * content_type から score を算出して返す
     *
     * @param list<string> $reasons
     */
    private function contentTypeScore(TopicDraft $draft, array &$reasons): int
    {
        $contentType = $draft->contentType ?? 'unknown';
        $map = $this->intMapConfig('radiopipe.topic_screening.content_type_scores', []);
        $score = $map[$contentType] ?? $map['unknown'] ?? 50;

        if ($score >= 75) {
            $reasons[] = 'content type is useful';
        } elseif ($score <= 35) {
            $reasons[] = 'content type is weak';
        }

        return $score;
    }

    /**
     * Limitation keyword rule から Penalty を算出して返す
     *
     * @param TopicDraft $draft
     * @param list<TopicScreeningKeywordRule> $rules
     * @param list<string> $reasons
     *
     * @return int
     */
    private function limitationPenalty(TopicDraft $draft, array $rules, array &$reasons): int
    {
        $penalty = 0;

        foreach ($rules as $rule) {
            if ($this->matchesRule($draft, $rule)) {
                $reasons[] = 'limitations mention weak source quality';
                $penalty = max($penalty, $rule->penalty ?? 0);
            }
        }

        return $penalty;
    }

    /**
     * Upstream Selection を弱い Bounded Signal として加算
     *
     * @param TopicDraft $draft
     * @param list<string> $reasons
     *
     * @return int
     */
    private function selectionBonus(TopicDraft $draft, array &$reasons): int
    {
        $status = $draft->upstreamSelection['status'] ?? null;
        $score = $draft->upstreamSelection['score'] ?? null;
        $bonus = 0;

        if ($status === 'selected') {
            $bonus += 5;
            $reasons[] = 'upstream selection status is selected';
        } elseif ($status === 'skipped') {
            $bonus -= 5;
            $reasons[] = 'upstream selection status is skipped';
        }

        if (is_int($score)) {
            if ($score > 0) {
                $bonus += min(2, $score);
            } else {
                $bonus -= 2;
            }
        }

        return $this->clampInt($bonus, -5, 5);
    }

    /**
     * Sensitive keyword rule に一致する Topic を判定する。
     *
     * @param TopicDraft $draft
     * @param list<TopicScreeningKeywordRule> $rules
     * @param list<string> $reasons
     *
     * @return array{is_sensitive: bool, reject_sensitive: bool}
     */
    private function sensitiveAssessment(TopicDraft $draft, array $rules, array &$reasons): array
    {
        $isSensitive = false;
        $rejectSensitive = false;

        foreach ($rules as $rule) {
            if ($this->matchesRule($draft, $rule)) {
                $reasons[] = 'topic contains sensitive keyword';
                $isSensitive = true;

                if ($rule->action === TopicScreeningKeywordRule::ACTION_REJECT) {
                    $rejectSensitive = true;
                }
            }
        }

        return [
            'is_sensitive' => $isSensitive,
            'reject_sensitive' => $rejectSensitive,
        ];
    }

    /**
     * TopicDraft が keyword rule に一致するか判定する。
     */
    private function matchesRule(TopicDraft $draft, TopicScreeningKeywordRule $rule): bool
    {
        $keyword = mb_strtolower(trim($rule->keyword));

        if ($keyword === '' || $rule->match_type !== TopicScreeningKeywordRule::MATCH_CONTAINS) {
            return false;
        }

        foreach ($this->targetFieldValues($draft, $rule) as $value) {
            if (str_contains(mb_strtolower($value), $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Rule の target_fields から検索対象文字列を返す。
     *
     * @return list<string>
     */
    private function targetFieldValues(TopicDraft $draft, TopicScreeningKeywordRule $rule): array
    {
        $targetFields = $rule->getAttribute('target_fields');

        if (! is_array($targetFields)) {
            return [];
        }

        $values = [];

        foreach ($targetFields as $field) {
            if (! is_string($field)) {
                continue;
            }

            $values = [
                ...$values,
                ...match ($field) {
                    TopicScreeningKeywordRule::FIELD_TITLE => $this->stringValues($draft->title),
                    TopicScreeningKeywordRule::FIELD_SUMMARY_SEED => $this->stringValues($draft->summarySeed),
                    TopicScreeningKeywordRule::FIELD_WHY_IT_MATTERS_SEED => $this->stringValues($draft->whyItMattersSeed),
                    TopicScreeningKeywordRule::FIELD_TAGS => $this->stringValues($draft->tags),
                    TopicScreeningKeywordRule::FIELD_CONTENT_TYPE => $this->stringValues($draft->contentType),
                    TopicScreeningKeywordRule::FIELD_LIMITATIONS => $this->stringValues($draft->limitations),
                    default => [],
                },
            ];
        }

        return $values;
    }

    /**
     * 文字列または文字列配列を検索用文字列一覧へ正規化する。
     *
     * @return list<string>
     */
    private function stringValues(mixed $value): array
    {
        if (is_string($value) && $value !== '') {
            return [$value];
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            $value,
            static fn (mixed $item): bool => is_string($item) && $item !== '',
        ));
    }

    private function screeningStatus(bool $isDuplicate, bool $isSensitive, bool $isUncertain, int $screeningScore, int $lowValueThreshold): TopicScreeningStatus
    {
        if ($isDuplicate) {
            return TopicScreeningStatus::RejectedDuplicate;
        }

        if ($isSensitive) {
            return TopicScreeningStatus::RejectedSensitive;
        }

        if ($isUncertain) {
            return TopicScreeningStatus::RejectedUncertain;
        }

        if ($screeningScore < $lowValueThreshold) {
            return TopicScreeningStatus::RejectedLowValue;
        }

        return TopicScreeningStatus::Passed;
    }

    private function clampInt(int $value, int $min, int $max): int
    {
        return max($min, min($max, $value));
    }

    private function intConfig(string $key, int $default): int
    {
        $value = config($key, $default);

        return is_int($value) ? $value : $default;
    }

    private function floatConfig(string $key, float $default): float
    {
        $value = config($key, $default);

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        return $default;
    }

    /**
     * @param string $key
     * @param array<int|string, int> $default
     *
     * @return array<int|string, int>
     */
    private function intMapConfig(string $key, array $default): array
    {
        $value = config($key, $default);

        if (! is_array($value)) {
            return $default;
        }

        $map = [];

        foreach ($value as $mapKey => $mapValue) {
            if (is_int($mapKey) && is_int($mapValue)) {
                $map[$mapKey] = $mapValue;
            } elseif (is_string($mapKey) && is_numeric($mapKey) && is_int($mapValue)) {
                $map[(int) $mapKey] = $mapValue;
            } elseif (is_string($mapKey) && is_int($mapValue)) {
                $map[$mapKey] = $mapValue;
            }
        }

        return $map;
    }
}
