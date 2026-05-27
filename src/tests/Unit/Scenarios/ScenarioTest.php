<?php

namespace Tests\Unit\Scenarios;

use App\Scenarios\Scenario;
use App\Scenarios\ScenarioSection;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class ScenarioTest extends TestCase
{
    public function testScenarioAndSectionsSerializeToExpectedArrayShape(): void
    {
        $section = new ScenarioSection(
            type: 'topic',
            title: 'GitHubアカウントのセキュリティ設定を点検するCLI',
            text: '次の話題です。GitHub の設定を確認する CLI です。',
            topicIds: ['upstream:236'],
            estimatedDurationSeconds: 90,
            metadata: ['role' => 'main_story'],
        );
        $scenario = new Scenario(
            title: '今日のギークニュース',
            language: 'ja',
            targetDurationSeconds: 900,
            estimatedDurationSeconds: 420,
            characterKey: 'neko_nyan_balanced_radio',
            scriptText: 'さてさて、今日のニュースを一旦見ていきます。',
            sections: [$section],
            metadata: ['driver' => 'fake'],
        );

        self::assertSame([
            'title' => '今日のギークニュース',
            'language' => 'ja',
            'target_duration_seconds' => 900,
            'estimated_duration_seconds' => 420,
            'character_key' => 'neko_nyan_balanced_radio',
            'script_text' => 'さてさて、今日のニュースを一旦見ていきます。',
            'sections' => [
                [
                    'type' => 'topic',
                    'title' => 'GitHubアカウントのセキュリティ設定を点検するCLI',
                    'text' => '次の話題です。GitHub の設定を確認する CLI です。',
                    'topic_ids' => ['upstream:236'],
                    'estimated_duration_seconds' => 90,
                    'metadata' => ['role' => 'main_story'],
                ],
            ],
            'metadata' => ['driver' => 'fake'],
        ], $scenario->toArray());
    }

    public function testScenarioRejectsNegativeDuration(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Scenario(
            title: 'invalid',
            language: 'ja',
            targetDurationSeconds: -1,
            estimatedDurationSeconds: null,
            characterKey: null,
            scriptText: '',
        );
    }

    public function testScenarioSectionRejectsNegativeEstimatedDuration(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ScenarioSection(
            type: 'topic',
            title: 'invalid',
            text: '',
            estimatedDurationSeconds: -1,
        );
    }
}
