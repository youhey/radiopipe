<?php

namespace App\Filament\Resources\CharacterProfileResource\Pages;

use App\Filament\Resources\CharacterProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/**
 * キャラクター人格マスターの一覧ページ。
 */
class ListCharacterProfiles extends ListRecords
{
    protected static string $resource = CharacterProfileResource::class;

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
