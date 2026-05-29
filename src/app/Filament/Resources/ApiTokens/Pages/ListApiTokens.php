<?php

namespace App\Filament\Resources\ApiTokens\Pages;

use App\ApiTokens\ApiTokenService;
use App\Filament\Resources\ApiTokens\ApiTokenResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Validator;

/**
 * API token metadata の一覧ページ。
 */
class ListApiTokens extends ListRecords
{
    /** @var string|null 発行直後だけ表示する plain text token */
    public ?string $createdPlainTextToken = null;

    /** @var string|null 発行した token name */
    public ?string $createdTokenName = null;

    /** @var string|null 発行対象 User email */
    public ?string $createdUserEmail = null;

    protected static string $resource = ApiTokenResource::class;

    /**
     * token 発行結果と一覧テーブルを表示する。
     */
    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.resources.api-tokens.created-token')
                    ->viewData(fn (): array => [
                        'plainTextToken' => $this->createdPlainTextToken,
                        'tokenName' => $this->createdTokenName,
                        'userEmail' => $this->createdUserEmail,
                    ])
                    ->hidden(fn (): bool => $this->createdPlainTextToken === null),
                EmbeddedTable::make(),
            ]);
    }

    /**
     * ヘッダーに表示する操作を返す。
     *
     * @return array<int, \Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->createApiTokenAction(),
            $this->revokeAllApiTokensAction(),
        ];
    }

    /**
     * API token 作成 action を返す。
     */
    private function createApiTokenAction(): Action
    {
        return Action::make('createApiToken')
            ->label('Create API Token')
            ->icon(Heroicon::OutlinedKey)
            ->form(fn (ApiTokenService $tokens): array => [
                Select::make('user_id')
                    ->label('User')
                    ->options(fn (): array => User::query()
                        ->getQuery()
                        ->orderBy('email')
                        ->pluck('email', 'id')
                        ->all())
                    ->searchable()
                    ->required(),
                TextInput::make('token_name')
                    ->label('Token name')
                    ->default($tokens->defaultTokenName())
                    ->required()
                    ->maxLength(255),
                CheckboxList::make('abilities')
                    ->label('Abilities')
                    ->options($tokens->allowedAbilities())
                    ->default($tokens->defaultAbilities())
                    ->required(),
            ])
            ->action(function (array $data, ApiTokenService $tokens): void {
                $validator = Validator::make($data, [
                    'user_id' => ['required', 'integer', 'exists:users,id'],
                    'token_name' => ['required', 'string', 'max:255'],
                    'abilities' => ['required', 'array', 'min:1'],
                    'abilities.*' => ['required', 'string'],
                ]);

                $validator->validate();

                $user = User::query()->findOrFail($this->intData($data, 'user_id'));
                $createdToken = $tokens->createToken(
                    user: $user,
                    name: $this->stringData($data, 'token_name'),
                    abilities: $this->arrayData($data, 'abilities'),
                );

                $this->createdPlainTextToken = $createdToken->plainTextToken;
                $this->createdTokenName = $createdToken->accessToken->name;
                $this->createdUserEmail = $user->email;
            })
            ->successNotificationTitle('API token created.');
    }

    /**
     * User の全 API token を失効する header action を返す。
     */
    private function revokeAllApiTokensAction(): Action
    {
        return Action::make('revokeAllApiTokens')
            ->label('Revoke All API Tokens')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->requiresConfirmation()
            ->form([
                Select::make('user_id')
                    ->label('User')
                    ->options(fn (): array => User::query()
                        ->getQuery()
                        ->orderBy('email')
                        ->pluck('email', 'id')
                        ->all())
                    ->searchable()
                    ->required(),
            ])
            ->action(function (array $data, ApiTokenService $tokens): void {
                $validator = Validator::make($data, [
                    'user_id' => ['required', 'integer', 'exists:users,id'],
                ]);

                $validator->validate();

                $user = User::query()->findOrFail($this->intData($data, 'user_id'));

                $tokens->revokeAllTokens($user);
            })
            ->successNotificationTitle('API tokens revoked.');
    }

    /**
     * action data から int 値を取り出す。
     *
     * @param array<array-key, mixed> $data
     */
    private function intData(array $data, string $key): int
    {
        $value = $data[$key] ?? 0;

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * action data から string 値を取り出す。
     *
     * @param array<array-key, mixed> $data
     */
    private function stringData(array $data, string $key): string
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
    private function arrayData(array $data, string $key): array
    {
        $value = $data[$key] ?? [];

        return is_array($value) ? array_values($value) : [];
    }
}
