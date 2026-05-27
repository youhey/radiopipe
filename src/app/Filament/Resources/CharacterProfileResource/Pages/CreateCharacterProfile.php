<?php

namespace App\Filament\Resources\CharacterProfileResource\Pages;

use App\Filament\Resources\CharacterProfileResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * キャラクター人格マスターの作成ページ。
 */
class CreateCharacterProfile extends CreateRecord
{
    protected static string $resource = CharacterProfileResource::class;

    /**
     * 保存前にフォームデータを正規化する。
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return CharacterProfileResource::normalizeFormData($data);
    }
}
