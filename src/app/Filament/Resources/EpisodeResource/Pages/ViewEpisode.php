<?php

namespace App\Filament\Resources\EpisodeResource\Pages;

use App\Filament\Resources\EpisodeResource;
use App\Models\Episode;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use LogicException;

/**
 * Episode 分析用の詳細ページ。
 */
class ViewEpisode extends ViewRecord
{
    protected static string $resource = EpisodeResource::class;

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
                    $record = $this->episodeRecord();

                    return EpisodeResource::jsonDownloadResponse(
                        EpisodeResource::exportPayload($record),
                        EpisodeResource::exportFilename($record),
                    );
                }),
        ];
    }

    /**
     * 表示中の Episode record を返す。
     */
    private function episodeRecord(): Episode
    {
        $record = $this->getRecord();

        if (! $record instanceof Episode) {
            throw new LogicException('Episode record is required.');
        }

        return $record;
    }
}
