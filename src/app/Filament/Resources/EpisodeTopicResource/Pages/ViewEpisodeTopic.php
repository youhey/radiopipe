<?php

namespace App\Filament\Resources\EpisodeTopicResource\Pages;

use App\Filament\Resources\EpisodeTopicResource;
use Filament\Resources\Pages\ViewRecord;

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
        return [];
    }
}
