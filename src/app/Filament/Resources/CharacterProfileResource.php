<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CharacterProfileResource\Pages;
use App\Models\CharacterProfile;
use BackedEnum;
use Closure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Validation\ValidationRule;
use UnitEnum;

/**
 * キャラクター人格マスターデータを管理する Filament Resource。
 */
class CharacterProfileResource extends Resource
{
    protected static ?string $model = CharacterProfile::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Character Profiles';

    protected static ?string $modelLabel = 'Character Profile';

    protected static ?string $pluralModelLabel = 'Character Profiles';

    /**
     * 入力フォームを構成する。
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic')
                    ->schema([
                        TextInput::make('character_key')
                            ->required()
                            ->maxLength(100)
                            ->regex('/^[a-z0-9_-]+$/')
                            ->unique(ignoreRecord: true),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('role')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('personality')
                            ->required()
                            ->maxLength(5000)
                            ->rows(5),
                        Textarea::make('tone')
                            ->required()
                            ->maxLength(3000)
                            ->rows(4),
                        Toggle::make('is_active')
                            ->default(true)
                            ->rules(['boolean']),
                        TextInput::make('sort_order')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->rules(['integer', 'min:0']),
                    ])
                    ->columns(2),
                Section::make('Speech Style')
                    ->schema([
                        TextInput::make('speech_style.language')
                            ->required()
                            ->maxLength(20),
                        TextInput::make('speech_style.sentence_length')
                            ->maxLength(100),
                        Toggle::make('speech_style.uses_listener_address')
                            ->default(false)
                            ->rules(['boolean']),
                        TextInput::make('speech_style.listener_name')
                            ->maxLength(50),
                        TextInput::make('speech_style.first_person')
                            ->required()
                            ->maxLength(50),
                        Textarea::make('speech_style.ending_style')
                            ->maxLength(1000)
                            ->rows(3),
                        Textarea::make('speech_style.pace')
                            ->maxLength(1000)
                            ->rows(3),
                        Textarea::make('speech_style.rhythm')
                            ->maxLength(1000)
                            ->rows(3),
                    ])
                    ->columns(2),
                Section::make('Phrases')
                    ->schema([
                        self::linesTextarea('catchphrases', 30),
                        self::linesTextarea('style_examples', 30),
                        self::linesTextarea('banned_phrases', 100),
                        self::linesTextarea('disallowed_expressions', 100),
                    ])
                    ->columns(2),
                Section::make('Serious Topic Behavior')
                    ->schema([
                        TextInput::make('serious_topic_behavior.tone'),
                        Toggle::make('serious_topic_behavior.allow_jokes')
                            ->default(false)
                            ->rules(['boolean']),
                        Textarea::make('serious_topic_behavior.required_style')
                            ->rows(3),
                        TextInput::make('serious_topic_behavior.catchphrase_limit'),
                        self::linesTextarea('serious_topic_behavior.applies_to', 50),
                    ])
                    ->columns(2),
                Section::make('Content Policy')
                    ->schema([
                        Textarea::make('content_policy.factuality_priority')
                            ->rows(3),
                        Textarea::make('content_policy.uncertainty_handling')
                            ->rows(3),
                        Textarea::make('content_policy.source_limitations')
                            ->rows(3),
                        Toggle::make('content_policy.no_fabrication')
                            ->default(true)
                            ->rules(['boolean']),
                        Textarea::make('content_policy.identity_safety')
                            ->rows(3),
                    ])
                    ->columns(2),
                Section::make('Script Preferences')
                    ->schema([
                        Textarea::make('script_preferences.opening_style')
                            ->rows(3),
                        Textarea::make('script_preferences.transition_style')
                            ->rows(3),
                        Textarea::make('script_preferences.closing_style')
                            ->rows(3),
                        self::linesTextarea('script_preferences.preferred_segment_roles', 30),
                    ])
                    ->columns(2),
                Section::make('Metadata')
                    ->schema([
                        TextInput::make('metadata.direction')
                            ->default('sample_safe_news_mascot'),
                        TextInput::make('metadata.reference_policy')
                            ->default('original_sample_character'),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * 一覧テーブルを構成する。
     */
    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('character_key')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('role')
                    ->limit(50)
                    ->searchable(),
                IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Resource relation 一覧を返す。
     *
     * @return array<class-string<\Filament\Resources\RelationManagers\RelationManager>|\Filament\Resources\RelationManagers\RelationGroup|\Filament\Resources\RelationManagers\RelationManagerConfiguration>
     */
    public static function getRelations(): array
    {
        return [];
    }

    /**
     * Resource page route を返す。
     *
     * @return array<string, \Filament\Resources\Pages\PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCharacterProfiles::route('/'),
            'create' => Pages\CreateCharacterProfile::route('/create'),
            'edit' => Pages\EditCharacterProfile::route('/{record}/edit'),
        ];
    }

    /**
     * フォーム保存前に JSON 配列と固定 metadata を正規化する。
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public static function normalizeFormData(array $data): array
    {
        foreach ([
            'catchphrases',
            'style_examples',
            'banned_phrases',
            'disallowed_expressions',
        ] as $field) {
            $data[$field] = self::normalizeLineState($data[$field] ?? null);
        }

        if (isset($data['serious_topic_behavior']) && is_array($data['serious_topic_behavior'])) {
            $data['serious_topic_behavior']['applies_to'] = self::normalizeLineState(
                $data['serious_topic_behavior']['applies_to'] ?? null,
            );
        }

        if (isset($data['script_preferences']) && is_array($data['script_preferences'])) {
            $data['script_preferences']['preferred_segment_roles'] = self::normalizeLineState(
                $data['script_preferences']['preferred_segment_roles'] ?? null,
            );
        }

        $metadata = self::stringKeyedArray($data['metadata'] ?? null);
        $data['metadata'] = CharacterProfile::withFixedMetadata($metadata);

        return $data;
    }

    /**
     * 改行区切り入力を JSON 配列へ変換する。
     *
     * @return array<int, string>
     */
    public static function linesToArray(?string $value): array
    {
        if ($value === null) {
            return [];
        }

        $lines = preg_split('/\R/u', $value);

        if ($lines === false) {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', $lines),
            static fn (string $line): bool => $line !== '',
        ));
    }

    /**
     * JSON 配列を編集フォーム用の改行区切り文字列へ変換する。
     */
    public static function arrayToLines(mixed $value): string
    {
        if (! is_array($value)) {
            return '';
        }

        return implode(PHP_EOL, array_values(array_filter(
            array_map(
                static fn (mixed $line): string => is_scalar($line) ? trim((string) $line) : '',
                $value,
            ),
            static fn (string $line): bool => $line !== '',
        )));
    }

    /**
     * 改行配列用 Textarea を作成する。
     */
    private static function linesTextarea(string $name, int $maxLines): Textarea
    {
        return Textarea::make($name)
            ->formatStateUsing(static fn (mixed $state): string => self::arrayToLines($state))
            ->dehydrateStateUsing(static fn (?string $state): array => self::linesToArray($state))
            ->rules([self::maxLinesRule($maxLines)])
            ->rows(5);
    }

    /**
     * 改行区切り入力の最大行数 validation rule を返す。
     */
    private static function maxLinesRule(int $maxLines): ValidationRule
    {
        return new class($maxLines) implements ValidationRule {
            /**
             * Constructor.
             */
            public function __construct(private readonly int $maxLines)
            {
            }

            /**
             * Validate the attribute.
             */
            public function validate(string $attribute, mixed $value, Closure $fail): void
            {
                $lineCount = is_string($value)
                    ? count(CharacterProfileResource::linesToArray($value))
                    : count($this->normalizeLineState($value));

                if ($lineCount > $this->maxLines) {
                    $fail("The {$attribute} field must not contain more than {$this->maxLines} non-empty lines.");
                }
            }

            /**
             * 文字列または配列の行状態を保存用配列へ正規化する。
             *
             * @return array<int, string>
             */
            private function normalizeLineState(mixed $value): array
            {
                if (! is_array($value)) {
                    return [];
                }

                return array_values(array_filter(
                    array_map(
                        static fn (mixed $line): string => is_scalar($line) ? trim((string) $line) : '',
                        $value,
                    ),
                    static fn (string $line): bool => $line !== '',
                ));
            }
        };
    }

    /**
     * 文字列または配列の行状態を保存用配列へ正規化する。
     *
     * @return array<int, string>
     */
    private static function normalizeLineState(mixed $value): array
    {
        if (is_string($value)) {
            return self::linesToArray($value);
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(
                static fn (mixed $line): string => is_scalar($line) ? trim((string) $line) : '',
                $value,
            ),
            static fn (string $line): bool => $line !== '',
        ));
    }

    /**
     * 文字列キーの配列として扱える値だけを抽出する。
     *
     * @return array<string, mixed>
     */
    private static function stringKeyedArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $result = [];

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }
}
