<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TopicScreeningKeywordRuleResource\Pages;
use App\Models\TopicScreeningKeywordRule;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use UnitEnum;

/**
 * Topic screening keyword rule マスターデータを管理する Filament Resource。
 */
class TopicScreeningKeywordRuleResource extends Resource
{
    protected static ?string $model = TopicScreeningKeywordRule::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Topic Screening Keyword Rules';

    protected static ?string $modelLabel = 'Topic Screening Keyword Rule';

    protected static ?string $pluralModelLabel = 'Topic Screening Keyword Rules';

    /**
     * 入力フォームを構成する。
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Rule')
                    ->schema([
                        Select::make('rule_type')
                            ->required()
                            ->options(TopicScreeningKeywordRule::ruleTypeOptions())
                            ->rules([Rule::in(array_keys(TopicScreeningKeywordRule::ruleTypeOptions()))]),
                        TextInput::make('keyword')
                            ->required()
                            ->maxLength(255)
                            ->regex('/\S/')
                            ->dehydrateStateUsing(static fn (?string $state): string => trim((string) $state)),
                        Select::make('match_type')
                            ->required()
                            ->default(TopicScreeningKeywordRule::MATCH_CONTAINS)
                            ->options(TopicScreeningKeywordRule::matchTypeOptions())
                            ->rules([Rule::in(array_keys(TopicScreeningKeywordRule::matchTypeOptions()))]),
                        CheckboxList::make('target_fields')
                            ->required()
                            ->options(TopicScreeningKeywordRule::targetFieldOptions())
                            ->columns(2),
                        TextInput::make('penalty')
                            ->numeric()
                            ->minValue(0)
                            ->rules(['nullable', 'integer', 'min:0']),
                        TextInput::make('severity')
                            ->maxLength(50),
                        Select::make('action')
                            ->required()
                            ->options(TopicScreeningKeywordRule::actionOptions())
                            ->rules([Rule::in(array_keys(TopicScreeningKeywordRule::actionOptions()))]),
                        Toggle::make('is_active')
                            ->default(true)
                            ->rules(['boolean']),
                        TextInput::make('sort_order')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->rules(['integer', 'min:0']),
                        Textarea::make('notes')
                            ->rows(4),
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
            ->defaultSort('rule_type')
            ->columns([
                TextColumn::make('rule_type')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('keyword')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('match_type')
                    ->sortable(),
                TextColumn::make('action')
                    ->sortable(),
                TextColumn::make('penalty')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('severity')
                    ->sortable(),
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
                SelectFilter::make('rule_type')
                    ->options(TopicScreeningKeywordRule::ruleTypeOptions()),
                SelectFilter::make('action')
                    ->options(TopicScreeningKeywordRule::actionOptions()),
                TernaryFilter::make('is_active'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(static function (Builder $query): Builder {
                $query->getQuery()
                    ->orderBy('rule_type')
                    ->orderBy('sort_order')
                    ->orderBy('keyword');

                return $query;
            });
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
            'index' => Pages\ListTopicScreeningKeywordRules::route('/'),
            'create' => Pages\CreateTopicScreeningKeywordRule::route('/create'),
            'edit' => Pages\EditTopicScreeningKeywordRule::route('/{record}/edit'),
        ];
    }
}
