<?php

namespace App\Upstream;

use Carbon\CarbonImmutable;

/**
 * テスト用の Fake Provider
 */
class FakeUpstreamProvider implements UpstreamProvider
{
    /**
     * deterministic な fake Upstream Items を返す
     *
     * @param UpstreamArticleQuery $query
     *
     * @return list<UpstreamArticleItem>
     */
    public function fetch(UpstreamArticleQuery $query): array
    {
        $now = CarbonImmutable::now('UTC');

        return [
            new UpstreamArticleItem(
                upstreamId: 'fake-1',
                source: [
                    'key' => $query->source ?? 'fake_source',
                    'name' => 'Fake Digest Source',
                ],
                article: [
                    'title' => 'Fake completed digest article',
                    'url' => 'https://example.test/articles/fake-completed-digest-article',
                    'published_at' => $now->toJSON(),
                ],
                selection: [
                    'status' => 'selected',
                    'score' => 1.0,
                ],
                analysis: [
                    'title' => [
                        'normalized' => 'Fake completed digest article',
                    ],
                    'content' => [
                        'brief' => 'Fake upstream digest brief.',
                    ],
                ],
                processing: [
                    'analysis_model' => 'fake',
                    'analyzed_at' => $now->toJSON(),
                ],
                fetchedAt: $now,
                providerName: 'fake',
            ),
        ];
    }
}
