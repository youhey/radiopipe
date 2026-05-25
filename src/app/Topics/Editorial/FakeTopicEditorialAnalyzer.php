<?php

namespace App\Topics\Editorial;

use App\Topics\TopicDraft;

/**
 * テストとローカル開発向けの deterministic な fake editorial analyzer です。
 */
class FakeTopicEditorialAnalyzer implements TopicEditorialAnalyzer
{
    private TopicDuplicateCandidateDetector $duplicateCandidateDetector;

    /**
     * Constructor.
     */
    public function __construct(?TopicDuplicateCandidateDetector $duplicateCandidateDetector = null)
    {
        $this->duplicateCandidateDetector = $duplicateCandidateDetector ?? new TopicDuplicateCandidateDetector();
    }

    /**
     * TopicDraft から fake editorial evaluation を生成します。
     */
    public function analyze(TopicDraft $topicDraft): TopicEditorialEvaluation
    {
        $title = $topicDraft->title ?? $topicDraft->originalTitle ?? 'Untitled topic';
        $summary = $topicDraft->summarySeed ?? sprintf('Fake summary for %s.', $title);
        $whyItMatters = $topicDraft->whyItMattersSeed ?? 'Fake editorial context generated from deterministic local heuristics.';
        $preference = 60;
        $generalImportance = $this->importanceScore($topicDraft->importance);
        $freshness = $this->freshnessScore($topicDraft);
        $certainty = $this->certaintyScore($topicDraft->confidence);
        $scenarioFitness = $this->scenarioFitnessScore($topicDraft);
        $flowFlexibility = 60;
        $scores = new TopicEditorialScores(
            preference: $preference,
            generalImportance: $generalImportance,
            freshness: $freshness,
            certainty: $certainty,
            scenarioFitness: $scenarioFitness,
            flowFlexibility: $flowFlexibility,
        );
        $editorialScore = $this->editorialScore($scores);
        $isUncertain = $certainty < 45;
        $isSensitive = $this->isSensitive($topicDraft);
        $status = $this->status($isSensitive, $isUncertain, $editorialScore);
        $avoid = [];
        $reasons = [
            'fake_editorial_analyzer',
            'score_from_deterministic_heuristics',
        ];

        if ($isUncertain) {
            $avoid[] = 'avoid presenting as confirmed';
            $reasons[] = 'low_certainty';
        }

        if ($isSensitive) {
            $avoid[] = 'avoid playful framing';
            $reasons[] = 'sensitive_keyword_detected';
        }

        if ($editorialScore < 45) {
            $reasons[] = 'low_editorial_score';
        }

        return new TopicEditorialEvaluation(
            status: $status,
            editorialScore: $editorialScore,
            localized: new TopicLocalizedText(
                title: $title,
                summary: $summary,
                whyItMatters: $whyItMatters,
            ),
            scores: $scores,
            flags: new TopicEditorialFlags(
                isDuplicateCandidate: false,
                isUncertain: $isUncertain,
                isSensitive: $isSensitive,
            ),
            duplicate: new TopicDuplicateAssessment(
                canonicalKey: $this->duplicateCandidateDetector->canonicalKey($topicDraft),
                similarTopicIds: [],
                duplicateOf: null,
                confidence: null,
                reason: null,
            ),
            scenarioNotes: new TopicScenarioNotes(
                suggestedRole: $this->suggestedRole($editorialScore),
                tone: 'neutral',
                transitionHint: $this->transitionHint($title, $topicDraft->tags),
                avoid: $avoid,
            ),
            reasons: $reasons,
            metadata: [
                'driver' => 'fake',
                'schema_version' => '1.0',
            ],
        );
    }

    private function importanceScore(float|int|null $importance): int
    {
        $normalized = is_int($importance) ? $importance : null;

        return match ($normalized) {
            5 => 95,
            4 => 80,
            3 => 60,
            2 => 40,
            1 => 20,
            default => 50,
        };
    }

    private function freshnessScore(TopicDraft $topicDraft): int
    {
        if ($topicDraft->publishedAt === null || $topicDraft->fetchedAt === null) {
            return 60;
        }

        $hours = ($topicDraft->fetchedAt->getTimestamp() - $topicDraft->publishedAt->getTimestamp()) / 3600;

        if ($hours <= 6) {
            return 90;
        }

        if ($hours <= 24) {
            return 80;
        }

        if ($hours <= 72) {
            return 65;
        }

        if ($hours <= 168) {
            return 45;
        }

        return 25;
    }

    private function certaintyScore(?float $confidence): int
    {
        if ($confidence === null || $confidence < 0.0 || $confidence > 1.0) {
            return 60;
        }

        return $this->clampInt((int) round($confidence * 100), 0, 100);
    }

    private function scenarioFitnessScore(TopicDraft $topicDraft): int
    {
        return match ($topicDraft->contentType) {
            'research_article', 'technical_article', 'data_analysis_article' => 75,
            'technical_blog_post', 'news_article', 'news' => 70,
            'landing_page', 'news_article_headline_only', 'support_question', 'privacy_policy' => 35,
            default => 65,
        };
    }

    private function editorialScore(TopicEditorialScores $scores): int
    {
        $score = $scores->preference * 0.20
            + $scores->generalImportance * 0.25
            + $scores->freshness * 0.15
            + $scores->certainty * 0.20
            + $scores->scenarioFitness * 0.15
            + $scores->flowFlexibility * 0.05;

        return $this->clampInt((int) round($score), 0, 100);
    }

    private function isSensitive(TopicDraft $topicDraft): bool
    {
        $haystack = strtolower(implode(' ', array_filter([
            $topicDraft->title,
            $topicDraft->summarySeed,
            $topicDraft->whyItMattersSeed,
            $topicDraft->contentType,
            implode(' ', $topicDraft->tags),
        ], static fn (?string $value): bool => $value !== null && $value !== '')));

        foreach ($this->sensitiveKeywords() as $keyword) {
            if (str_contains($haystack, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function status(bool $isSensitive, bool $isUncertain, int $editorialScore): TopicEditorialStatus
    {
        if ($isSensitive) {
            return TopicEditorialStatus::SkippedSensitive;
        }

        if ($isUncertain) {
            return TopicEditorialStatus::SkippedUncertain;
        }

        if ($editorialScore < 45) {
            return TopicEditorialStatus::SkippedLowValue;
        }

        return TopicEditorialStatus::Pending;
    }

    /**
     * @param list<string> $tags
     */
    private function transitionHint(string $title, array $tags): string
    {
        $tag = $tags[0] ?? null;

        if ($tag !== null && $tag !== '') {
            return sprintf('%s の話題として %s を紹介できる', $tag, $title);
        }

        return sprintf('%s を短く紹介できる', $title);
    }

    private function suggestedRole(int $editorialScore): string
    {
        if ($editorialScore >= 75) {
            return 'main_story';
        }

        if ($editorialScore < 60) {
            return 'quick_mention';
        }

        return 'background_context';
    }

    private function clampInt(int $value, int $min, int $max): int
    {
        return max($min, min($max, $value));
    }

    /**
     * @return list<string>
     */
    private function sensitiveKeywords(): array
    {
        return [
            'disaster',
            'accident',
            'crime',
            'war',
            'military',
            'terrorism',
            'politics',
            'election',
            'medical',
            'health',
            'finance',
            'investment',
            'self-harm',
            'sexual',
            'abuse',
            'violence',
            'hate',
            'security breach',
            'credential leak',
            'exploit',
        ];
    }
}
