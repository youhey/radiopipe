<?php

namespace App\Filament\Resources\EpisodeResource\Pages;

use App\Filament\Resources\EpisodeResource;
use Filament\Resources\Pages\ViewRecord;

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
        return [];
    }
}
