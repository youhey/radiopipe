<?php

namespace App\Console\Commands;

use App\Topics\Editorial\TopicEditorialAnalyzer;
use App\Topics\Screening\TopicScreeningEvaluator;
use App\Topics\Screening\TopicScreeningStatus;
use App\Topics\TopicBuilder;
use App\Upstream\UpstreamArticleItem;
use App\Upstream\UpstreamArticleQuery;
use App\Upstream\UpstreamProvider;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use JsonException;
use Throwable;

/**
 * 中間データを JSON で出力するデバッグコマンド
 */
class TopicsDebugCommand extends Command
{
    protected $signature = 'radiopipe:topics:debug
        {--from= : Start datetime for upstream article fetch}
        {--to= : End datetime for upstream article fetch}
        {--limit= : Maximum number of upstream articles to fetch}';

    protected $description = 'Inspect the configured topic processing pipeline as JSON.';

    private UpstreamProvider $upstreamProvider;

    private TopicBuilder $topicBuilder;

    private TopicScreeningEvaluator $screeningEvaluator;

    private TopicEditorialAnalyzer $editorialAnalyzer;

    /**
     * Constructor.
     */
    public function __construct(
        UpstreamProvider $upstreamProvider,
        TopicBuilder $topicBuilder,
        TopicScreeningEvaluator $screeningEvaluator,
        TopicEditorialAnalyzer $editorialAnalyzer,
    ) {
        parent::__construct();

        $this->upstreamProvider = $upstreamProvider;
        $this->topicBuilder = $topicBuilder;
        $this->screeningEvaluator = $screeningEvaluator;
        $this->editorialAnalyzer = $editorialAnalyzer;
    }

    /**
     * Configured Topic Pipeline を実行して JSON を標準出力へ出力する
     */
    public function handle(): int
    {
        $timezone = $this->timezone();
        $now = CarbonImmutable::now($timezone);
        $to = $this->dateOption('to') ?? $now;
        $from = $this->dateOption('from') ?? $to->subDay();
        $limit = $this->limitOption();

        try {
            $upstreamArticles = $this->upstreamProvider->fetch(new UpstreamArticleQuery(
                from: $from,
                to: $to,
                limit: $limit,
            ));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $items = [];
        $errors = [];

        foreach ($upstreamArticles as $upstreamArticle) {
            $item = [
                'upstream_article' => $upstreamArticle->toArray(),
                'topic_draft' => null,
                'screening' => null,
                'editorial' => null,
            ];

            try {
                $topicDraft = $this->topicBuilder->build($upstreamArticle);
                $item['topic_draft'] = $topicDraft->toArray();

                $screening = $this->screeningEvaluator->evaluate($topicDraft);
                $item['screening'] = $screening->toArray();

                $editorial = null;

                if ($screening->screeningStatus === TopicScreeningStatus::Passed) {
                    $editorial = $this->editorialAnalyzer->analyze($topicDraft);
                }

                $item['editorial'] = $editorial?->toArray();
            } catch (Throwable $exception) {
                $errors[] = $this->errorItem($exception, $item, $upstreamArticle);
            }

            $items[] = $item;
        }

        $output = [
            'schema_version' => '1.0',
            'generated_at' => $now->toAtomString(),
            'input' => [
                'from' => $from->toAtomString(),
                'to' => $to->toAtomString(),
                'limit' => $limit,
            ],
            'count' => count($items),
            'items' => $items,
            'errors' => $errors,
        ];

        try {
            $this->line(json_encode($output, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } catch (JsonException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return $errors === [] ? self::SUCCESS : self::FAILURE;
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

    /**
     * @param array<string, mixed> $item
     *
     * @return array{stage: string, topic_id: string|null, message: string}
     */
    private function errorItem(Throwable $exception, array $item, UpstreamArticleItem $upstreamArticle): array
    {
        $topicDraft = $item['topic_draft'] ?? null;
        $screening = $item['screening'] ?? null;
        $stage = 'topic_building';
        $topicId = null;

        if (is_array($topicDraft)) {
            $stage = is_array($screening) ? 'editorial' : 'screening';
            $topicId = is_string($topicDraft['id'] ?? null) ? $topicDraft['id'] : null;
        }

        return [
            'stage' => $stage,
            'topic_id' => $topicId ?? 'upstream:' . $upstreamArticle->upstreamId,
            'message' => $exception->getMessage(),
        ];
    }
}
