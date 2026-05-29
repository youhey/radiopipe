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
 * Episode 生成結果を分析する Filament Page。
 */
class EpisodesAnalysis extends Page
{
    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Analysis';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Episodes Analysis';

    protected static ?string $title = 'Episodes Analysis';

    protected string $view = 'filament.pages.episodes-analysis';

    /**
     * Episode 分析用 stat 一覧を返す。
     *
     * @return list<array{label: string, value: string}>
     */
    public function stats(): array
    {
        $lastDay = Carbon::now()->subDay();
        $total = DB::table('episodes')->count();
        $completed = DB::table('episodes')->where('status', 'completed')->count();
        $completedWithErrors = DB::table('episodes')->where('status', 'completed_with_errors')->count();
        $failed = DB::table('episodes')->where('status', 'failed')->count();
        $episodesLastDay = DB::table('episodes')->where('created_at', '>=', $lastDay)->count();
        $avgTarget = DB::table('episodes')->whereNotNull('target_duration_seconds')->avg('target_duration_seconds');
        $avgEstimated = DB::table('episodes')->whereNotNull('estimated_duration_seconds')->avg('estimated_duration_seconds');
        $usedTopicCount = DB::table('episode_topics')->where('scenario_selection_status', 'used_in_scenario')->count();
        $selectedNotUsedCount = DB::table('episode_topics')->where('scenario_selection_status', 'selected_not_used')->count();

        return [
            ['label' => 'Total episodes', 'value' => (string) $total],
            ['label' => 'Completed episodes', 'value' => (string) $completed],
            ['label' => 'Completed with errors', 'value' => (string) $completedWithErrors],
            ['label' => 'Failed episodes', 'value' => (string) $failed],
            ['label' => 'Episodes in last 24h', 'value' => (string) $episodesLastDay],
            ['label' => 'Average target duration', 'value' => $this->secondsValue($avgTarget)],
            ['label' => 'Average estimated duration', 'value' => $this->secondsValue($avgEstimated)],
            ['label' => 'Average used topics per episode', 'value' => $this->ratioValue($usedTopicCount, $total)],
            ['label' => 'Selected-not-used topics count', 'value' => (string) $selectedNotUsedCount],
            ['label' => 'Episodes with errors', 'value' => (string) ($completedWithErrors + $failed)],
        ];
    }

    /**
     * Status 別の episode 件数を返す。
     *
     * @return list<array{label: string, value: int}>
     */
    public function statusDistribution(): array
    {
        return $this->distribution('episodes', 'status');
    }

    /**
     * Character 別の episode 件数を返す。
     *
     * @return list<array{label: string, value: int}>
     */
    public function characterDistribution(): array
    {
        return $this->distribution('episodes', 'character_key');
    }

    /**
     * 直近の Episode 一覧を返す。
     *
     * @return list<array{episode_key: string, status: string, title: string, published_at: string}>
     */
    public function recentEpisodes(): array
    {
        $rows = DB::table('episodes')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $episodes = [];

        foreach ($rows as $row) {
            $episodes[] = [
                'episode_key' => is_scalar($row->episode_key ?? null) ? (string) $row->episode_key : '',
                'status' => is_scalar($row->status ?? null) ? (string) $row->status : '',
                'title' => is_scalar($row->title ?? null) ? (string) $row->title : '',
                'published_at' => EpisodeResource::dateTimeString($row->published_at ?? null) ?? '',
            ];
        }

        return $episodes;
    }

    /**
     * 秒数の平均値を表示用に整形する。
     */
    private function secondsValue(mixed $value): string
    {
        return is_numeric($value) ? sprintf('%.1f sec', (float) $value) : '0 sec';
    }

    /**
     * 件数比率を表示用に整形する。
     */
    private function ratioValue(int $count, int $total): string
    {
        if ($total <= 0) {
            return '0';
        }

        return sprintf('%.2f', $count / $total);
    }

    /**
     * 指定 table / column の分布を返す。
     *
     * @return list<array{label: string, value: int}>
     */
    private function distribution(string $table, string $column): array
    {
        $rows = DB::table($table)
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
}
