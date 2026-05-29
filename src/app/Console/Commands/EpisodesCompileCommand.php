<?php

namespace App\Console\Commands;

use App\Episodes\CandidateEpisodeCompiler;
use App\Models\CharacterProfile;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * CandidateTopic から Episode を生成・永続化するコマンド。
 */
class EpisodesCompileCommand extends Command
{
    protected $signature = 'radiopipe:episodes:compile
        {--character= : Character profile key}
        {--limit= : Maximum candidate topics to consider}';

    protected $description = 'Compile a persisted episode from saved candidate topics when input changed.';

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
     * CandidateTopic から Episode を生成する。
     */
    public function handle(): int
    {
        $character = $this->characterProfile($this->option('character'));

        if (! $character instanceof CharacterProfile) {
            return self::FAILURE;
        }

        try {
            $result = $this->compiler->compile($character, $this->limitOption(), CarbonImmutable::now());
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($result->skipped) {
            $this->line('Episode compile fingerprint unchanged; skipping generation.');

            return self::SUCCESS;
        }

        $episode = $result->episode;

        if ($episode === null) {
            $this->warn('No candidate topics were available for episode compilation.');

            return self::SUCCESS;
        }

        $this->line("Episode compiled: id={$episode->id} key={$episode->episode_key} status={$episode->status}");

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
            return 20;
        }

        return max(1, (int) $value);
    }
}
