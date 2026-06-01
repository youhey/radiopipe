<?php

namespace App\Filament\Resources\ApiTokens;

use App\ApiTokens\ApiTokenService;
use App\Filament\Resources\ApiTokens\Pages\ListApiTokens;
use App\Filament\Resources\EpisodeResource;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
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
                    ->formatStateUsing(static fn (mixed $state): string => self::formatAbilityBadges($state))
                    ->html()
                    ->wrap(),
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
                Action::make('editMetadata')
                    ->label('Edit Token')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->form(fn (PersonalAccessToken $record, ApiTokenService $tokens): array => [
                        TextInput::make('name')
                            ->label('Token name')
                            ->default($record->name)
                            ->required()
                            ->maxLength(255),
                        CheckboxList::make('abilities')
                            ->label('Abilities')
                            ->options($tokens->allowedAbilities())
                            ->default(self::abilityList($record->abilities))
                            ->required(),
                    ])
                    ->action(static function (PersonalAccessToken $record, array $data, ApiTokenService $tokens): void {
                        $allowedAbilities = array_keys($tokens->allowedAbilities());

                        Validator::make($data, [
                            'name' => ['required', 'string', 'max:255'],
                            'abilities' => ['required', 'array', 'min:1'],
                            'abilities.*' => ['required', 'string', Rule::in($allowedAbilities)],
                        ])->validate();

                        $tokens->updateTokenMetadata(
                            token: $record,
                            name: self::stringData($data, 'name'),
                            abilities: self::arrayData($data, 'abilities'),
                        );
                    })
                    ->successNotificationTitle('API token updated.'),
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
     * abilities を表示用 badge HTML に変換する。
     */
    private static function formatAbilityBadges(mixed $state): string
    {
        $abilities = self::abilityList($state);

        if ($abilities === []) {
            return '<span class="text-sm text-gray-500 dark:text-gray-400">None</span>';
        }

        $badges = array_map(
            static fn (string $ability): string => sprintf(
                '<span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 ring-1 ring-gray-200 dark:bg-white/10 dark:text-gray-200 dark:ring-white/10">%s</span>',
                e($ability),
            ),
            $abilities,
        );

        return '<div class="flex flex-wrap gap-1.5">' . implode('', $badges) . '</div>';
    }

    /**
     * action data から string 値を取り出す。
     *
     * @param array<array-key, mixed> $data
     */
    private static function stringData(array $data, string $key): string
    {
        $value = $data[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    /**
     * action data から list 値を取り出す。
     *
     * @param array<array-key, mixed> $data
     *
     * @return list<mixed>
     */
    private static function arrayData(array $data, string $key): array
    {
        $value = $data[$key] ?? [];

        return is_array($value) ? array_values($value) : [];
    }

    /**
     * abilities cast 値を表示・form 用 list に整える。
     *
     * @return list<string>
     */
    private static function abilityList(mixed $state): array
    {
        if (is_string($state)) {
            return $state === '' ? [] : [$state];
        }

        if (! is_array($state)) {
            return [];
        }

        $abilities = [];

        foreach ($state as $ability) {
            if (is_string($ability) && $ability !== '') {
                $abilities[] = $ability;
            }
        }

        return $abilities;
    }
}
