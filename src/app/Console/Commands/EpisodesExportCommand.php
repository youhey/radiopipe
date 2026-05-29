<?php

namespace App\Console\Commands;

use App\Episodes\CandidateEpisodeCompiler;
use App\Models\CharacterProfile;
use App\Scenarios\ScenarioTopicSelection;
use Illuminate\Console\Command;
use JsonException;
use Throwable;

/**
 * CandidateTopic から Scenario JSON を生成して標準出力へ出すコマンド。
 */
class EpisodesExportCommand extends Command
{
    protected $signature = 'radiopipe:episodes:export
        {--character= : Character profile key}
        {--limit= : Maximum candidate topics to consider}';

    protected $description = 'Export scenario JSON from saved candidate topics without persisting an episode.';

    private CandidateEpisodeCompiler $compiler;

    /**
     * Constructor.
     */
    public function __construct(CandidateEpisodeCompiler $compiler)
    {
        parent::__construct();

        $this->compiler = $compiler;
    }

    /**
     * CandidateTopic から Scenario JSON を生成する。
     */
    public function handle(): int
    {
        $character = $this->characterProfile($this->option('character'));

        if (! $character instanceof CharacterProfile) {
            return self::FAILURE;
        }

        try {
            $result = $this->compiler->export($character, $this->limitOption());
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $output = [
            'schema_version' => '1.0',
            'character' => [
                'character_key' => $character->character_key,
                'name' => $character->name,
            ],
            'candidate_topic_count' => count($result->candidateTopics),
            'compile_fingerprint' => $result->compileFingerprint,
            'scenario' => $result->scenarioResult->scenario->toArray(),
            'topic_selections' => array_map(
                static fn (ScenarioTopicSelection $selection): array => $selection->toArray(),
                $result->topicSelections,
            ),
        ];

        try {
            $this->line(json_encode($output, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } catch (JsonException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function characterProfile(mixed $characterKey): ?CharacterProfile
    {
        $query = CharacterProfile::query()->where('is_active', true);

        if (is_string($characterKey) && trim($characterKey) !== '') {
            $profile = $query->where('character_key', trim($characterKey))->first();

            if (! $profile instanceof CharacterProfile) {
                $this->error("Active character profile [{$characterKey}] was not found.");

                return null;
            }

            return $profile;
        }

        $query->getQuery()
            ->orderBy('sort_order')
            ->orderBy('name');

        $profile = $query->first();

        if (! $profile instanceof CharacterProfile) {
            $this->error('No active character profile was found.');

            return null;
        }

        return $profile;
    }

    private function limitOption(): int
    {
        $value = $this->option('limit');

        if (! is_string($value) || trim($value) === '') {
            $configured = config('radiopipe.pipeline.limit', 20);

            return is_numeric($configured) ? max(1, (int) $configured) : 20;
        }

        return max(1, (int) $value);
    }
}
