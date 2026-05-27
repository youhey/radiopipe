<?php

namespace App\Filament\Resources\TopicScreeningKeywordRuleResource\Pages;

use App\Filament\Resources\TopicScreeningKeywordRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/**
 * Topic screening keyword rule の一覧ページ。
 */
class ListTopicScreeningKeywordRules extends ListRecords
{
    protected static string $resource = TopicScreeningKeywordRuleResource::class;

    /**
     * ヘッダーに表示する操作を返す。
     *
     * @return array<int, \Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
