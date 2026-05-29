<?php

namespace App\Filament\Resources;

use App\Filament\Infolists\JsonPrettyEntry;
use App\Filament\Resources\EpisodeResource\Pages;
use App\Models\Episode;
use App\Models\EpisodeTopic;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * 生成済み Episode を分析用に参照する Filament Resource。
 */
class EpisodeResource extends Resource
{
    protected static ?string $model = Episode::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedRadio;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Episodes';

    protected static ?string $modelLabel = 'Episode';

    protected static ?string $pluralModelLabel = 'Episodes';

    /**
     * 一覧テーブルを構成する。
     */
    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('published_at', 'desc')
            ->columns([
                TextColumn::make('episode_key')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('date')
                    ->date()
                    ->sortable(),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('character_key')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('characterProfile.name')
                    ->label('Character')
                    ->sortable(),
                TextColumn::make('title')
                    ->limit(60)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('language')
                    ->sortable(),
                TextColumn::make('target_duration_seconds')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('estimated_duration_seconds')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('topics_count')
                    ->counts('topics')
                    ->label('Episode Topics')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        Episode::STATUS_COMPLETED => Episode::STATUS_COMPLETED,
                        Episode::STATUS_COMPLETED_WITH_ERRORS => Episode::STATUS_COMPLETED_WITH_ERRORS,
                        Episode::STATUS_FAILED => Episode::STATUS_FAILED,
                    ]),
                SelectFilter::make('character_key')
                    ->options(static fn (): array => self::distinctOptions('character_key'))
                    ->searchable(),
                self::dateRangeFilter('date', 'date'),
                self::dateRangeFilter('published_at', 'published_at'),
                self::dateRangeFilter('created_at', 'created_at'),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    /**
     * 詳細表示用 infolist を構成する。
     */
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic metadata')
                    ->schema([
                        self::summaryEntry('episode_key'),
                        self::summaryEntry('status')
                            ->badge(),
                        self::summaryEntry('date')
                            ->date(),
                        self::summaryEntry('published_at')
                            ->dateTime(),
                        self::summaryEntry('processed_at')
                            ->dateTime(),
                        self::summaryEntry('character_key'),
                        self::summaryEntry('characterProfile.name')
                            ->label('Character'),
                        self::summaryEntry('title'),
                        self::summaryEntry('language'),
                        self::summaryEntry('target_duration_seconds')
                            ->numeric(),
                        self::summaryEntry('estimated_duration_seconds')
                            ->numeric(),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),
                Section::make('Scenario summary')
                    ->schema([
                        self::summaryEntry('scenario_json.title')
                            ->label('Scenario title'),
                        self::summaryEntry('scenario_json.language')
                            ->label('Scenario language'),
                        TextEntry::make('scenario_json.script_text')
                            ->label('Scenario script text')
                            ->formatStateUsing(static fn (mixed $state): HtmlString => self::lineBreakHtml($state))
                            ->extraEntryWrapperAttributes(self::summaryEntryWrapperAttributes())
                            ->columnSpanFull(),
                        TextEntry::make('topics_summary')
                            ->label('Related episode topics')
                            ->state(static fn (Episode $record): string => self::topicsSummary($record))
                            ->fontFamily(FontFamily::Mono)
                            ->extraEntryWrapperAttributes(self::summaryEntryWrapperAttributes())
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make('Raw JSON')
                    ->schema([
                        JsonPrettyEntry::make('scenario_json', 'Raw scenario_json'),
                        JsonPrettyEntry::make('errors', 'Errors'),
                        JsonPrettyEntry::make('metadata', 'Raw metadata'),
                    ])
                    ->columnSpanFull(),
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
            'index' => Pages\ListEpisodes::route('/'),
            'view' => Pages\ViewEpisode::route('/{record}'),
        ];
    }

    /**
     * Episode は管理画面から作成しない。
     */
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Episode は管理画面から編集しない。
     */
    public static function canEdit(Model $record): bool
    {
        return false;
    }

    /**
     * Episode は管理画面から削除しない。
     */
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /**
     * Episode の bulk delete を許可しない。
     */
    public static function canDeleteAny(): bool
    {
        return false;
    }

    /**
     * 要約項目を視覚的に区切る wrapper 属性を返す。
     *
     * @return array<string, string>
     */
    public static function summaryEntryWrapperAttributes(): array
    {
        return [
            'class' => 'rounded-xl border border-gray-200 bg-gray-50 p-4 shadow-sm dark:border-white/10 dark:bg-white/5',
        ];
    }

    /**
     * 要約項目用のカード状 entry を返す。
     */
    private static function summaryEntry(string $name): TextEntry
    {
        return TextEntry::make($name)
            ->extraEntryWrapperAttributes(self::summaryEntryWrapperAttributes());
    }

    /**
     * 改行を HTML 表示用に変換する。
     */
    private static function lineBreakHtml(mixed $value): HtmlString
    {
        $text = is_scalar($value) ? (string) $value : '';
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return new HtmlString(nl2br($escaped, false));
    }

    /**
     * 指定 column の重複しない option 一覧を返す。
     *
     * @return array<string, string>
     */
    private static function distinctOptions(string $column): array
    {
        $values = DB::table('episodes')
            ->whereNotNull($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->all();

        $options = [];

        foreach ($values as $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $option = (string) $value;
            $options[$option] = $option;
        }

        return $options;
    }

    /**
     * 日付範囲 filter を返す。
     */
    private static function dateRangeFilter(string $name, string $column): Filter
    {
        return Filter::make($name)
            ->schema([
                DatePicker::make('from'),
                DatePicker::make('until'),
            ])
            ->query(static function (Builder $query, array $data) use ($column): Builder {
                $from = $data['from'] ?? null;
                $until = $data['until'] ?? null;

                if (is_string($from) && $from !== '') {
                    $query->getQuery()->whereDate($column, '>=', $from);
                }

                if (is_string($until) && $until !== '') {
                    $query->getQuery()->whereDate($column, '<=', $until);
                }

                return $query;
            });
    }

    /**
     * 関連 topic の要約を返す。
     */
    private static function topicsSummary(Episode $episode): string
    {
        return $episode->topics()
            ->get()
            ->sortBy([
                ['sort_order', 'asc'],
                ['id', 'asc'],
            ])
            ->map(static function (EpisodeTopic $topic): string {
                $parts = [];

                foreach ([
                    $topic->topic_id,
                    $topic->title,
                    $topic->screening_status,
                    $topic->editorial_status,
                    $topic->scenario_selection_status,
                ] as $value) {
                    if (is_string($value) && $value !== '') {
                        $parts[] = $value;
                    }
                }

                return implode(' | ', $parts);
            })
            ->implode(PHP_EOL);
    }
}
