<?php

namespace App\Topics\Editorial;

use App\Topics\TopicDraft;

/**
 * TopicDraft 群から Obvious な Duplicate Candidate を Deterministic に検出
 */
class TopicDuplicateCandidateDetector
{
    public const STRONG_DUPLICATE_SCORE = 90;

    public const CANDIDATE_SCORE = 70;

    /**
     * Topic ID ごとに Duplicate Candidate Topic ID を返す
     *
     * @param array<int, TopicDraft> $topics
     *
     * @return array<string, list<string>>
     */
    public function detectCandidates(array $topics): array
    {
        $candidates = [];
        $count = count($topics);

        for ($i = 0; $i < $count; ++$i) {
            for ($j = $i + 1; $j < $count; ++$j) {
                $left = $topics[$i];
                $right = $topics[$j];
                $assessment = $this->assess($left, $right);

                if (! $assessment->isCandidate()) {
                    continue;
                }

                $candidates[$left->id] ??= [];
                $candidates[$right->id] ??= [];
                $candidates[$left->id][] = $right->id;
                $candidates[$right->id][] = $left->id;
            }
        }

        foreach ($candidates as $topicId => $topicCandidates) {
            $candidates[$topicId] = array_values(array_unique($topicCandidates));
        }

        ksort($candidates);

        return $candidates;
    }

    /**
     * 2 つの TopicDraft の Duplicate Candidate Score を算出
     *
     * @param TopicDraft $topic
     * @param TopicDraft $otherTopic
     *
     * @return TopicDuplicateCandidateAssessment
     */
    public function assess(TopicDraft $topic, TopicDraft $otherTopic): TopicDuplicateCandidateAssessment
    {
        $urlMatch = $this->normalizedUrl($topic->url) !== null
            && $this->normalizedUrl($topic->url) === $this->normalizedUrl($otherTopic->url);
        $discussionUrlMatch = $this->normalizedUrl($topic->discussionUrl) !== null
            && $this->normalizedUrl($topic->discussionUrl) === $this->normalizedUrl($otherTopic->discussionUrl);
        $titleKey = $this->normalizedText($topic->title ?? $topic->originalTitle);
        $otherTitleKey = $this->normalizedText($otherTopic->title ?? $otherTopic->originalTitle);
        $titleExactMatch = $titleKey !== null && $titleKey === $otherTitleKey;
        $titleSimilarity = $this->tokenSimilarity($topic->title ?? $topic->originalTitle, $otherTopic->title ?? $otherTopic->originalTitle);
        $summarySimilarity = $this->tokenSimilarity($topic->summarySeed, $otherTopic->summarySeed);
        $entityOverlap = $this->overlapScore($topic->entities, $otherTopic->entities);
        $tagOverlap = $this->overlapScore($topic->tags, $otherTopic->tags);
        $sameSource = $topic->sourceType === $otherTopic->sourceType
            && $topic->sourceName !== null
            && $topic->sourceName === $otherTopic->sourceName;

        if ($urlMatch) {
            return $this->assessment(100, $urlMatch, $discussionUrlMatch, $titleExactMatch, $titleSimilarity, $summarySimilarity, $entityOverlap, $tagOverlap, $sameSource, 'exact normalized URL match');
        }

        if ($discussionUrlMatch) {
            return $this->assessment(95, $urlMatch, $discussionUrlMatch, $titleExactMatch, $titleSimilarity, $summarySimilarity, $entityOverlap, $tagOverlap, $sameSource, 'exact normalized discussion URL match');
        }

        if ($titleExactMatch) {
            return $this->assessment(90, $urlMatch, $discussionUrlMatch, $titleExactMatch, $titleSimilarity, $summarySimilarity, $entityOverlap, $tagOverlap, $sameSource, 'exact normalized title match');
        }

        $score = 0;

        if ($titleSimilarity >= 70) {
            $score += min(75, $titleSimilarity);
        }

        if ($summarySimilarity >= 70) {
            $score += min(20, (int) round($summarySimilarity * 0.2));
        }

        if ($entityOverlap >= 50) {
            $score += min(20, (int) round($entityOverlap * 0.2));
        }

        if ($tagOverlap >= 50) {
            $score += min(15, (int) round($tagOverlap * 0.15));
        }

        if ($sameSource && $titleSimilarity >= 70) {
            $score += 5;
        }

        $score = $this->clampInt($score, 0, 100);
        $reason = $score >= self::CANDIDATE_SCORE ? 'similar deterministic topic signals' : null;

        return $this->assessment($score, $urlMatch, $discussionUrlMatch, $titleExactMatch, $titleSimilarity, $summarySimilarity, $entityOverlap, $tagOverlap, $sameSource, $reason);
    }

    /**
     * TopicDraft から Deterministic Canonical Key を作成して返す
     *
     * @param TopicDraft $topic
     *
     * @return string|null
     */
    public function canonicalKey(TopicDraft $topic): ?string
    {
        $url = $this->normalizedUrl($topic->url);

        if ($url !== null) {
            return 'url-' . substr(sha1($url), 0, 16);
        }

        $title = $this->slug($topic->title ?? $topic->originalTitle);

        return $title === '' ? null : $title;
    }

    private function assessment(
        int $score,
        bool $urlMatch,
        bool $discussionUrlMatch,
        bool $titleExactMatch,
        int $titleSimilarity,
        int $summarySimilarity,
        int $entityOverlap,
        int $tagOverlap,
        bool $sameSource,
        ?string $reason,
    ): TopicDuplicateCandidateAssessment {
        return new TopicDuplicateCandidateAssessment(
            duplicateScore: $this->clampInt($score, 0, 100),
            signals: [
                'exact_url_match' => $urlMatch,
                'exact_discussion_url_match' => $discussionUrlMatch,
                'exact_title_match' => $titleExactMatch,
                'title_similarity' => $titleSimilarity,
                'summary_similarity' => $summarySimilarity,
                'entity_overlap' => $entityOverlap,
                'tag_overlap' => $tagOverlap,
                'same_source' => $sameSource,
            ],
            reason: $reason,
        );
    }

    private function normalizedUrl(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $parts = parse_url(trim($url));

        if (! is_array($parts) || ! isset($parts['host'])) {
            return strtolower(rtrim(trim($url), '/'));
        }

        $host = strtolower($parts['host']);
        $path = isset($parts['path']) ? preg_replace('#/+#', '/', $parts['path']) : '';
        $path = is_string($path) ? rtrim($path, '/') : '';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';

        return $host . $path . $query;
    }

    private function normalizedText(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $normalized = strtolower(str_replace('　', ' ', trim($text)));
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $normalized);
        $normalized = is_string($normalized) ? trim(preg_replace('/\s+/u', ' ', $normalized) ?? '') : '';

        return $normalized === '' ? null : $normalized;
    }

    private function slug(?string $text): string
    {
        $normalized = $this->normalizedText($text);

        if ($normalized === null) {
            return '';
        }

        return str_replace(' ', '-', $normalized);
    }

    private function tokenSimilarity(?string $left, ?string $right): int
    {
        $leftTokens = $this->tokens($left);
        $rightTokens = $this->tokens($right);

        if ($leftTokens === [] || $rightTokens === []) {
            return 0;
        }

        $intersection = array_intersect($leftTokens, $rightTokens);
        $union = array_unique(array_merge($leftTokens, $rightTokens));

        return (int) round((count($intersection) / count($union)) * 100);
    }

    /**
     * @return list<string>
     */
    private function tokens(?string $text): array
    {
        $normalized = $this->normalizedText($text);

        if ($normalized === null) {
            return [];
        }

        return array_values(array_unique(array_filter(
            explode(' ', $normalized),
            static fn (string $token): bool => mb_strlen($token) >= 2,
        )));
    }

    /**
     * @param list<string> $left
     * @param list<string> $right
     */
    private function overlapScore(array $left, array $right): int
    {
        $leftNormalized = $this->normalizedList($left);
        $rightNormalized = $this->normalizedList($right);

        if ($leftNormalized === [] || $rightNormalized === []) {
            return 0;
        }

        $intersection = array_intersect($leftNormalized, $rightNormalized);
        $minimum = min(count($leftNormalized), count($rightNormalized));

        return (int) round((count($intersection) / $minimum) * 100);
    }

    /**
     * @param list<string> $values
     *
     * @return list<string>
     */
    private function normalizedList(array $values): array
    {
        return array_values(array_unique(array_filter(
            array_map(fn (string $value): ?string => $this->normalizedText($value), $values),
            static fn (?string $value): bool => $value !== null,
        )));
    }

    private function clampInt(int $value, int $min, int $max): int
    {
        return max($min, min($max, $value));
    }
}
