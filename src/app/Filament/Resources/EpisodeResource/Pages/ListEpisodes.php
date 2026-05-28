<?php

namespace App\Filament\Resources\EpisodeResource\Pages;

use App\Filament\Resources\EpisodeResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Episode 分析用の一覧ページ。
 */
class ListEpisodes extends ListRecords
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
