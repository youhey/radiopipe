<?php

namespace App\Filament\Widgets;

use App\Models\Episode;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

/**
 * Dashboard に表示する pipeline activity の概要。
 */
class PipelineStatsOverviewWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Pipeline health';

    protected ?string $description = 'Recent episode generation and topic nomination activity.';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $since = Carbon::now()->subDay();
        $latestEpisode = DB::table('episodes')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->first();

        $latestEpisodeIdValue = $latestEpisode === null ? null : ($latestEpisode->id ?? null);
        $latestEpisodeKeyValue = $latestEpisode === null ? null : ($latestEpisode->episode_key ?? null);
        $latestEpisodeId = is_numeric($latestEpisodeIdValue) ? (int) $latestEpisodeIdValue : null;
        $latestEpisodeLabel = is_scalar($latestEpisodeKeyValue)
            ? (string) $latestEpisodeKeyValue
            : 'None';
        $latestEpisodeTopics = $latestEpisodeId === null
            ? 0
            : DB::table('episode_topics')
                ->where('episode_id', $latestEpisodeId)
                ->where('scenario_selection_status', 'used_in_scenario')
                ->count();

        return [
            Stat::make('Latest Episode', $this->latestEpisodeValue($latestEpisodeLabel))
                ->icon(Heroicon::OutlinedRadio),
            Stat::make('Episodes / last 24h', DB::table('episodes')->where('created_at', '>=', $since)->count())
                ->icon(Heroicon::OutlinedDocumentText),
            Stat::make('Candidate Topics / last 24h', DB::table('candidate_topics')->where('processed_at', '>=', $since)->count())
                ->icon(Heroicon::OutlinedRectangleStack),
            Stat::make('Used Topics in Latest Episode', $latestEpisodeTopics)
                ->icon(Heroicon::OutlinedQueueList),
            Stat::make('Errors / last 24h', $this->errorCount($since))
                ->icon(Heroicon::OutlinedExclamationTriangle),
        ];
    }

    /**
     * 直近 window の episode error 件数を返す。
     */
    private function errorCount(Carbon $since): int
    {
        return DB::table('episodes')
            ->where('created_at', '>=', $since)
            ->where(static function (Builder $query): void {
                $query
                    ->whereIn('status', [Episode::STATUS_COMPLETED_WITH_ERRORS, Episode::STATUS_FAILED])
                    ->orWhereNotNull('errors');
            })
            ->count();
    }

    /**
     * 長い episode key を Stats card 内で省略表示できる HTML にする。
     */
    private function latestEpisodeValue(string $value): HtmlString
    {
        $label = $this->formatLatestEpisodeLabel($value);
        $escapedTitle = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $escapedPrimary = htmlspecialchars($label['primary'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $escapedSecondary = htmlspecialchars($label['secondary'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        if ($escapedSecondary === '') {
            return new HtmlString(sprintf(
                '<span title="%s" style="display:block;max-width:100%%;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:1rem;line-height:1.5rem;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;">%s</span>',
                $escapedTitle,
                $escapedPrimary,
            ));
        }

        return new HtmlString(sprintf(
            '<span title="%s" style="display:block;max-width:100%%;min-width:0;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;"><span style="display:block;max-width:100%%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:1rem;line-height:1.5rem;">%s</span><span style="display:block;max-width:100%%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:1rem;line-height:1.5rem;">%s</span></span>',
            $escapedTitle,
            $escapedPrimary,
            $escapedSecondary,
        ));
    }

    /**
     * episode key を dashboard 用の短い複数行表示に整形する。
     *
     * @return array{primary: string, secondary: string}
     */
    private function formatLatestEpisodeLabel(string $value): array
    {
        if ($value === 'None') {
            return [
                'primary' => 'None',
                'secondary' => '',
            ];
        }

        if (preg_match('/^episode_(\d{4}-\d{2}-\d{2})_(\d{4})(\d{2})?_(.+)$/', $value, $matches) !== 1) {
            return [
                'primary' => $value,
                'secondary' => '',
            ];
        }

        $time = sprintf('%s:%s', substr($matches[2], 0, 2), substr($matches[2], 2, 2));

        if ($matches[3] !== '') {
            $time .= ':' . $matches[3];
        }

        return [
            'primary' => sprintf('episode %s %s', $matches[1], $time),
            'secondary' => $this->formatCharacterKey($matches[4]),
        ];
    }

    /**
     * character key を dashboard 上で読みやすい空白区切りにする。
     */
    private function formatCharacterKey(string $value): string
    {
        $words = explode('_', $value);
        $formatted = [];

        foreach ($words as $word) {
            $formatted[] = in_array($word, ['balanced', 'radio'], true) ? ucfirst($word) : $word;
        }

        return implode(' ', $formatted);
    }
}
