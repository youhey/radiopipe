<?php

namespace App\Filament\Resources\EpisodeTopicResource\Pages;

use App\Filament\Resources\EpisodeTopicResource;
use App\Models\EpisodeTopic;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use LogicException;

/**
 * Episode topic snapshot 分析用の詳細ページ。
 */
class ViewEpisodeTopic extends ViewRecord
{
    protected static string $resource = EpisodeTopicResource::class;

    /**
     * ヘッダーに表示する操作を返す。
     *
     * @return array<int, \Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->action(function () {
                    $record = $this->episodeTopicRecord();

                    return EpisodeTopicResource::jsonDownloadResponse(
                        EpisodeTopicResource::exportPayload($record),
                        sprintf('episode-topic-%s.json', Str::slug(str_replace(':', '-', $record->topic_id))),
                    );
                }),
        ];
    }

    /**
     * 表示中の EpisodeTopic record を返す。
     */
    private function episodeTopicRecord(): EpisodeTopic
    {
        $record = $this->getRecord();

        if (! $record instanceof EpisodeTopic) {
            throw new LogicException('EpisodeTopic record is required.');
        }

        return $record;
    }
}
