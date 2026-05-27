<?php

namespace App\Filament\Resources\TopicScreeningKeywordRuleResource\Pages;

use App\Filament\Resources\TopicScreeningKeywordRuleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * Topic screening keyword rule の編集ページ。
 */
class EditTopicScreeningKeywordRule extends EditRecord
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
            DeleteAction::make(),
        ];
    }
}
