<?php

namespace App\Console\Commands;

use App\Topics\Candidates\CandidateTopicNominationInput;
use App\Topics\Candidates\CandidateTopicNominationService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Upstream article を CandidateTopic として推薦・永続化するコマンド。
 */
class TopicsNominateCommand extends Command
{
    private const THROTTLE_KEY = 'radiopipe:topics:nominate:last_success';

    protected $signature = 'radiopipe:topics:nominate
        {--from= : Start datetime for upstream article fetch}
        {--to= : End datetime for upstream article fetch}
        {--limit= : Maximum number of upstream articles to fetch}
        {--force : Ignore existing candidate fingerprints and throttle lock}';

    protected $description = 'Prepare and persist reusable candidate topic records.';

    private CandidateTopicNominationService $nominationService;

    /**
     * Constructor.
     */
    public function __construct(CandidateTopicNominationService $nominationService)
    {
        parent::__construct();

        $this->nominationService = $nominationService;
    }

    /**
     * CandidateTopic nomination を実行する。
     */
    public function handle(): int
    {
        $force = $this->option('force');
        $throttleSeconds = $this->throttleSeconds();

        if (! $force && $throttleSeconds > 0 && Cache::has(self::THROTTLE_KEY)) {
            $this->line('Topic nomination throttle lock is active; skipped.');

            return self::SUCCESS;
        }

        $timezone = $this->timezone();
        $now = CarbonImmutable::now($timezone);
        $to = $this->dateOption('to') ?? $now;
        $from = $this->dateOption('from') ?? $to->subDay();

        try {
            $result = $this->nominationService->nominate(new CandidateTopicNominationInput(
                from: $from,
                to: $to,
                limit: $this->limitOption(),
                processedAt: $now,
                force: $force,
            ));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($throttleSeconds > 0) {
            Cache::put(self::THROTTLE_KEY, $now->toAtomString(), $throttleSeconds);
        }

        $this->line(sprintf(
            'Candidate topics nominated: fetched=%d created=%d updated=%d unchanged=%d errors=%d',
            $result->fetched,
            $result->created,
            $result->updated,
            $result->unchanged,
            count($result->errors),
        ));

        return self::SUCCESS;
    }

    private function dateOption(string $name): ?CarbonImmutable
    {
        $value = $this->option($name);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return CarbonImmutable::parse($value, $this->timezone());
    }

    private function limitOption(): int
    {
        $value = $this->option('limit');

        if (! is_string($value) || trim($value) === '') {
            return 20;
        }

        return max(1, (int) $value);
    }

    private function throttleSeconds(): int
    {
        $value = config('radiopipe.topic_nomination.throttle_seconds', 3600);

        return is_numeric($value) ? (int) $value : 3600;
    }

    private function timezone(): string
    {
        $timezone = config('app.timezone', 'UTC');

        return is_string($timezone) && trim($timezone) !== '' ? trim($timezone) : 'UTC';
    }
}
