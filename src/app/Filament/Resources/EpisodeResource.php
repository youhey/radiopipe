<?php

namespace App\Filament\Resources;

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
use UnitEnum;

/**
 * 生成済み Episode を分析用に参照する Filament Resource。
 */
class EpisodeResource extends Resource
{
    protected static ?string $model = Episode::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Analysis';

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
                        TextEntry::make('episode_key'),
                        TextEntry::make('status')
                            ->badge(),
                        TextEntry::make('date')
                            ->date(),
                        TextEntry::make('published_at')
                            ->dateTime(),
                        TextEntry::make('processed_at')
                            ->dateTime(),
                        TextEntry::make('character_key'),
                        TextEntry::make('characterProfile.name')
                            ->label('Character'),
                        TextEntry::make('title'),
                        TextEntry::make('language'),
                        TextEntry::make('target_duration_seconds')
                            ->numeric(),
                        TextEntry::make('estimated_duration_seconds')
                            ->numeric(),
                    ])
                    ->columns(3),
                Section::make('Scenario summary')
                    ->schema([
                        TextEntry::make('scenario_json.title')
                            ->label('Scenario title'),
                        TextEntry::make('scenario_json.language')
                            ->label('Scenario language'),
                        TextEntry::make('scenario_json.script_text')
                            ->label('Scenario script text')
                            ->columnSpanFull(),
                        TextEntry::make('topics_summary')
                            ->label('Related episode topics')
                            ->state(static fn (Episode $record): string => self::topicsSummary($record))
                            ->fontFamily(FontFamily::Mono)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Raw JSON')
                    ->schema([
                        self::jsonEntry('scenario_json', 'Raw scenario_json'),
                        self::jsonEntry('errors', 'Errors'),
                        self::jsonEntry('metadata', 'Raw metadata'),
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
     * JSON 表示用 entry を返す。
     */
    public static function jsonEntry(string $name, string $label): TextEntry
    {
        return TextEntry::make($name)
            ->label($label)
            ->formatStateUsing(static fn (mixed $state): string => self::prettyJson($state))
            ->fontFamily(FontFamily::Mono)
            ->copyable()
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
