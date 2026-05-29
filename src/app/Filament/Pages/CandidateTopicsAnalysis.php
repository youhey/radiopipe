<?php

namespace App\Filament\Pages;

use App\Filament\Resources\EpisodeResource;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * CandidateTopic nomination 品質を分析する Filament Page。
 */
class CandidateTopicsAnalysis extends Page
{
    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedChartPie;

    protected static string|UnitEnum|null $navigationGroup = 'Analysis';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Candidate Topics Analysis';

    protected static ?string $title = 'Candidate Topics Analysis';

    protected string $view = 'filament.pages.candidate-topics-analysis';

    /**
     * CandidateTopic 分析用 stat 一覧を返す。
     *
     * @return list<array{label: string, value: string}>
     */
    public function stats(): array
    {
        $lastDay = Carbon::now()->subDay();
        $total = DB::table('candidate_topics')->count();
        $lastDayCount = DB::table('candidate_topics')->where('processed_at', '>=', $lastDay)->count();
        $screeningPassed = DB::table('candidate_topics')->where('screening_status', 'passed')->count();
        $screeningRejected = DB::table('candidate_topics')->where('screening_status', '!=', 'passed')->count();
        $editorialPending = DB::table('candidate_topics')->where('editorial_status', 'pending')->count();
        $editorialSkipped = DB::table('candidate_topics')->where('editorial_status', '!=', 'pending')->count();
        $avgScreeningScore = DB::table('candidate_topics')->whereNotNull('screening_score')->avg('screening_score');
        $avgEditorialScore = DB::table('candidate_topics')->whereNotNull('editorial_score')->avg('editorial_score');
        $sourceCount = DB::table('candidate_topics')->whereNotNull('source_name')->distinct('source_name')->count('source_name');

        return [
            ['label' => 'Total candidate topics', 'value' => (string) $total],
            ['label' => 'Candidate topics in last 24h', 'value' => (string) $lastDayCount],
            ['label' => 'Screening passed count', 'value' => (string) $screeningPassed],
            ['label' => 'Screening rejected count', 'value' => (string) $screeningRejected],
            ['label' => 'Screening pass rate', 'value' => $this->percentValue($screeningPassed, $total)],
            ['label' => 'Editorial pending count', 'value' => (string) $editorialPending],
            ['label' => 'Editorial skipped count', 'value' => (string) $editorialSkipped],
            ['label' => 'Editorial pending rate', 'value' => $this->percentValue($editorialPending, $total)],
            ['label' => 'Average screening score', 'value' => $this->numberValue($avgScreeningScore)],
            ['label' => 'Average editorial score', 'value' => $this->numberValue($avgEditorialScore)],
            ['label' => 'Source count', 'value' => (string) $sourceCount],
        ];
    }

    /**
     * Screening status 分布を返す。
     *
     * @return list<array{label: string, value: int}>
     */
    public function screeningStatusDistribution(): array
    {
        return $this->distribution('screening_status');
    }

    /**
     * Editorial status 分布を返す。
     *
     * @return list<array{label: string, value: int}>
     */
    public function editorialStatusDistribution(): array
    {
        return $this->distribution('editorial_status');
    }

    /**
     * Source 分布を返す。
     *
     * @return list<array{label: string, value: int}>
     */
    public function sourceDistribution(): array
    {
        return $this->distribution('source_name');
    }

    /**
     * 直近の CandidateTopic 一覧を返す。
     *
     * @return list<array{topic_id: string, title: string, screening_status: string, editorial_status: string, processed_at: string}>
     */
    public function recentCandidateTopics(): array
    {
        $rows = DB::table('candidate_topics')
            ->orderByDesc('processed_at')
            ->orderByDesc('article_published_at')
            ->limit(5)
            ->get();

        $topics = [];

        foreach ($rows as $row) {
            $topics[] = [
                'topic_id' => is_scalar($row->topic_id ?? null) ? (string) $row->topic_id : '',
                'title' => $this->topicTitle($row->topic_draft_json ?? null),
                'screening_status' => is_scalar($row->screening_status ?? null) ? (string) $row->screening_status : '',
                'editorial_status' => is_scalar($row->editorial_status ?? null) ? (string) $row->editorial_status : '',
                'processed_at' => EpisodeResource::dateTimeString($row->processed_at ?? null) ?? '',
            ];
        }

        return $topics;
    }

    /**
     * 件数比率を表示用パーセントへ整形する。
     */
    private function percentValue(int $count, int $total): string
    {
        if ($total <= 0) {
            return '0%';
        }

        return sprintf('%.1f%%', ($count / $total) * 100);
    }

    /**
     * 平均値を表示用に整形する。
     */
    private function numberValue(mixed $value): string
    {
        return is_numeric($value) ? sprintf('%.1f', (float) $value) : '0';
    }

    /**
     * 指定 column の分布を返す。
     *
     * @return list<array{label: string, value: int}>
     */
    private function distribution(string $column): array
    {
        $rows = DB::table('candidate_topics')
            ->select($column, DB::raw('count(*) as aggregate_count'))
            ->whereNotNull($column)
            ->groupBy($column)
            ->orderByDesc('aggregate_count')
            ->limit(10)
            ->get();

        $distribution = [];

        foreach ($rows as $row) {
            $label = $row->{$column} ?? null;
            $count = $row->aggregate_count ?? null;

            $distribution[] = [
                'label' => is_scalar($label) ? (string) $label : '',
                'value' => is_numeric($count) ? (int) $count : 0,
            ];
        }

        return $distribution;
    }

    /**
     * topic_draft_json から表示用タイトルを取り出す。
     */
    private function topicTitle(mixed $value): string
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : null;
        }

        $title = is_array($value) ? ($value['title'] ?? null) : null;

        return is_string($title) ? $title : '';
    }
}
