<?php

namespace App\Filament\Resources\EpisodeTopicResource\Pages;

use App\Filament\Resources\EpisodeTopicResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Episode topic snapshot 分析用の一覧ページ。
 */
class ListEpisodeTopics extends ListRecords
{
    protected static string $resource = EpisodeTopicResource::class;

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
