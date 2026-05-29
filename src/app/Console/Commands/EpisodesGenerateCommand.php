<?php

namespace App\Console\Commands;

use App\Episodes\EpisodeGenerationInput;
use App\Episodes\EpisodeGenerationRunResult;
use App\Episodes\EpisodeGenerationService;
use App\Models\CharacterProfile;
use App\Scenarios\ScenarioTopicSelection;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use JsonException;
use Throwable;

/**
 * Configured pipeline を実行して Episode を生成・永続化するコマンド。
 */
class EpisodesGenerateCommand extends Command
{
    protected $signature = 'radiopipe:episodes:generate
        {--from= : Start datetime for upstream article fetch}
        {--to= : End datetime for upstream article fetch}
        {--limit= : Maximum number of upstream articles to fetch}
        {--character= : Character profile key to use}
        {--published-at= : Published datetime for the episode}
        {--dry-run : Run the pipeline and print JSON without saving}';

    protected $description = 'Generate and persist an episode using the configured radiopipe pipeline.';

    private EpisodeGenerationService $episodeGenerationService;

    /**
     * Constructor.
     */
    public function __construct(EpisodeGenerationService $episodeGenerationService)
    {
        parent::__construct();

        $this->episodeGenerationService = $episodeGenerationService;
    }

    /**
     * Episode generation pipeline を実行する。
     */
    public function handle(): int
    {
        $timezone = $this->timezone();
        $now = CarbonImmutable::now($timezone);
        $to = $this->dateOption('to') ?? $now;
        $from = $this->dateOption('from') ?? $to->subDay();
        $limit = $this->limitOption();
        $publishedAt = $this->dateOption('published-at') ?? $now;
        $character = $this->characterProfile($this->option('character'));

        if (! $character instanceof CharacterProfile) {
            return self::FAILURE;
        }

        try {
            $result = $this->episodeGenerationService->generate(new EpisodeGenerationInput(
                from: $from,
                to: $to,
                limit: $limit,
                publishedAt: $publishedAt,
                processedAt: $now,
                characterProfile: $character,
                persist: ! $this->option('dry-run'),
                commandName: 'radiopipe:episodes:generate',
            ));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            return $this->printDryRun($now, $from, $to, $limit, $publishedAt, $character, $result);
        }

        $episode = $result->episode;

        if ($episode === null) {
            $this->error('Episode was not persisted.');

            return self::FAILURE;
        }

        $this->line("Episode generated: id={$episode->id} key={$episode->episode_key} status={$episode->status}");

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

    private function dateOption(string $name): ?CarbonImmutable
    {
        $value = $this->option($name);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return CarbonImmutable::parse($value, $this->timezone());
    }

    private function timezone(): string
    {
        $timezone = config('app.timezone', 'UTC');

        return is_string($timezone) && $timezone !== '' ? $timezone : 'UTC';
    }

    private function limitOption(): int
    {
        $value = $this->option('limit');

        if (! is_string($value) || trim($value) === '') {
            return 20;
        }

        return max(1, (int) $value);
    }

    private function printDryRun(
        CarbonImmutable $now,
        CarbonImmutable $from,
        CarbonImmutable $to,
        int $limit,
        CarbonImmutable $publishedAt,
        CharacterProfile $character,
        EpisodeGenerationRunResult $result,
    ): int {
        $output = [
            'schema_version' => '1.0',
            'dry_run' => true,
            'generated_at' => $now->toAtomString(),
            'input' => [
                'from' => $from->toAtomString(),
                'to' => $to->toAtomString(),
                'limit' => $limit,
                'character_key' => $character->character_key,
                'published_at' => $publishedAt->toAtomString(),
            ],
            'scenario' => $result->scenarioResult->scenario->toArray(),
            'topic_selections' => array_map(
                static fn (ScenarioTopicSelection $selection): array => $selection->toArray(),
                $result->topicSelections,
            ),
            'errors' => $result->errors,
        ];

        try {
            $this->line(json_encode($output, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } catch (JsonException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
