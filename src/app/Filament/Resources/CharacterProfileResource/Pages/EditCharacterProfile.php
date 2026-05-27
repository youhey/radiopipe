<?php

namespace App\Filament\Resources\CharacterProfileResource\Pages;

use App\Filament\Resources\CharacterProfileResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * キャラクター人格マスターの編集ページ。
 */
class EditCharacterProfile extends EditRecord
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
            DeleteAction::make(),
        ];
    }

    /**
     * 保存前にフォームデータを正規化する。
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return CharacterProfileResource::normalizeFormData($data);
    }
}
