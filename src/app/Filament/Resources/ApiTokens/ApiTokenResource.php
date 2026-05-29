<?php

namespace App\Filament\Resources\ApiTokens;

use App\Filament\Resources\ApiTokens\Pages\ListApiTokens;
use App\Filament\Resources\EpisodeResource;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\PersonalAccessToken;
use UnitEnum;

/**
 * private Web API 用 Sanctum token metadata を表示する Filament Resource。
 */
class ApiTokenResource extends Resource
{
    protected static ?string $model = PersonalAccessToken::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'API Tokens';

    protected static ?string $modelLabel = 'API Token';

    protected static ?string $pluralModelLabel = 'API Tokens';

    /**
     * User に紐づく token だけを対象にする。
     *
     * @return Builder<Model>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('tokenable_type', User::class)
            ->with('tokenable');
    }

    /**
     * 一覧テーブルを構成する。
     */
    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('tokenable.name')
                    ->label('User name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tokenable.email')
                    ->label('User email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Token name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('abilities')
                    ->formatStateUsing(static fn (mixed $state): string => self::formatAbilities($state))
                    ->badge()
                    ->separator(', '),
                TextColumn::make('last_used_at')
                    ->dateTime(EpisodeResource::DATETIME_FORMAT)
                    ->placeholder('Never')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime(EpisodeResource::DATETIME_FORMAT)
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->dateTime(EpisodeResource::DATETIME_FORMAT)
                    ->placeholder('Never')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->label('Revoke Token')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(static function (PersonalAccessToken $record): void {
                        $record->delete();
                    }),
                Action::make('revokeAllForUser')
                    ->label('Revoke All For User')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(static function (PersonalAccessToken $record): void {
                        $user = $record->tokenable;

                        if ($user instanceof User) {
                            $user->tokens()->getQuery()->delete();
                        }
                    }),
            ]);
    }

    /**
     * Resource page route を返す。
     *
     * @return array<string, \Filament\Resources\Pages\PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListApiTokens::route('/'),
        ];
    }

    /**
     * token metadata は管理画面から作成しない。
     */
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * token metadata は管理画面から編集しない。
     */
    public static function canEdit(Model $record): bool
    {
        return false;
    }

    /**
     * token metadata の直接削除は dedicated revoke action に限定する。
     */
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /**
     * token metadata の bulk delete を許可しない。
     */
    public static function canDeleteAny(): bool
    {
        return false;
    }

    /**
     * abilities を表示用文字列に変換する。
     */
    private static function formatAbilities(mixed $state): string
    {
        if (! is_array($state)) {
            return '';
        }

        $abilities = [];

        foreach ($state as $ability) {
            if (is_string($ability)) {
                $abilities[] = $ability;
            }
        }

        return implode(', ', $abilities);
    }
}
