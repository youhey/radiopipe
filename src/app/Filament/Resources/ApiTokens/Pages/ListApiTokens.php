<?php

namespace App\Filament\Resources\ApiTokens\Pages;

use App\ApiTokens\ApiTokenService;
use App\Filament\Resources\ApiTokens\ApiTokenResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Validator;

/**
 * API token metadata の一覧ページ。
 */
class ListApiTokens extends ListRecords
{
    protected static string $resource = ApiTokenResource::class;

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
            ->action(function (array $data, ApiTokenService $tokens, HasActions $livewire): void {
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

                $livewire->mountAction('showCreatedApiToken', arguments: [
                    'plainTextToken' => $createdToken->plainTextToken,
                    'tokenName' => $createdToken->accessToken->name,
                    'userEmail' => $user->email,
                ]);
            })
            ->registerModalActions([
                Action::make('showCreatedApiToken')
                    ->modalHeading('API token created')
                    ->modalDescription('Copy this token now. It will not be shown again.')
                    ->modalContent(fn (array $arguments): View => view('filament.resources.api-tokens.created-token', [
                        'plainTextToken' => $this->stringArgument($arguments, 'plainTextToken'),
                        'tokenName' => $this->stringArgument($arguments, 'tokenName'),
                        'userEmail' => $this->stringArgument($arguments, 'userEmail'),
                    ]))
                    ->modalWidth(Width::Large)
                    ->closeModalByClickingAway(false)
                    ->closeModalByEscaping(false)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->cancelParentActions(),
            ])
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

    /**
     * modal action arguments から string 値を取り出す。
     *
     * @param array<array-key, mixed> $arguments
     */
    private function stringArgument(array $arguments, string $key): string
    {
        $value = $arguments[$key] ?? '';

        return is_string($value) ? $value : '';
    }
}
