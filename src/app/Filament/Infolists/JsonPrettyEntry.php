<?php

namespace App\Filament\Infolists;

use Filament\Infolists\Components\ViewEntry;

/**
 * JSON snapshot を読みやすく表示する Infolist entry helper。
 */
class JsonPrettyEntry
{
    /**
     * JSON pretty 表示用 entry を返す。
     */
    public static function make(string $name, string $label): ViewEntry
    {
        return ViewEntry::make($name)
            ->label($label)
            ->view('filament.infolists.json-pretty-entry')
            ->extraEntryWrapperAttributes(self::entryWrapperAttributes())
            ->columnSpanFull();
    }

    /**
     * JSON 互換値を読みやすい文字列へ変換する。
     */
    public static function prettyJson(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($json) ? $json : '';
    }

    /**
     * JSON 表示領域を視覚的に区切る wrapper 属性を返す。
     *
     * @return array<string, string>
     */
    private static function entryWrapperAttributes(): array
    {
        return [
            'class' => 'rounded-xl border border-gray-200 bg-gray-50 p-4 shadow-sm dark:border-white/10 dark:bg-white/5',
        ];
    }
}
