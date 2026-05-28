<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EpisodeTopicResource\Pages;
use App\Models\EpisodeTopic;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
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
 * Episode topic processing snapshot を分析用に参照する Filament Resource。
 */
class EpisodeTopicResource extends Resource
{
    protected static ?string $model = EpisodeTopic::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Analysis';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Episode Topics';

    protected static ?string $modelLabel = 'Episode Topic';

    protected static ?string $pluralModelLabel = 'Episode Topics';

    /**
     * 一覧テーブルを構成する。
     */
    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('episode_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('episode.episode_key')
                    ->label('Episode key')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('topic_id')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('source_name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->limit(70)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('screening_status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('editorial_status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('scenario_selection_status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('upstream_provider')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('upstream_id')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('source_type')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                TextColumn::make('url')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('screening_status')
                    ->options(static fn (): array => self::distinctOptions('screening_status')),
                SelectFilter::make('editorial_status')
                    ->options(static fn (): array => self::distinctOptions('editorial_status')),
                SelectFilter::make('scenario_selection_status')
                    ->options(static fn (): array => self::distinctOptions('scenario_selection_status')),
                SelectFilter::make('source_name')
                    ->options(static fn (): array => self::distinctOptions('source_name'))
                    ->searchable(),
                SelectFilter::make('source_type')
                    ->options(static fn (): array => self::distinctOptions('source_type')),
                SelectFilter::make('upstream_provider')
                    ->options(static fn (): array => self::distinctOptions('upstream_provider'))
                    ->searchable(),
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
                Section::make('Basic topic metadata')
                    ->schema([
                        TextEntry::make('episode_id')
                            ->numeric(),
                        TextEntry::make('episode.episode_key')
                            ->label('Episode key'),
                        TextEntry::make('topic_id'),
                        TextEntry::make('title')
                            ->columnSpanFull(),
                        TextEntry::make('screening_status')
                            ->badge(),
                        TextEntry::make('editorial_status')
                            ->badge(),
                        TextEntry::make('scenario_selection_status')
                            ->badge(),
                        TextEntry::make('sort_order')
                            ->numeric(),
                        TextEntry::make('created_at')
                            ->dateTime(),
                    ])
                    ->columns(3),
                Section::make('Upstream/source metadata')
                    ->schema([
                        TextEntry::make('upstream_provider'),
                        TextEntry::make('upstream_id'),
                        TextEntry::make('source_name'),
                        TextEntry::make('source_type'),
                        TextEntry::make('url')
                            ->url(static fn (?string $state): ?string => $state)
                            ->openUrlInNewTab()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Raw JSON')
                    ->schema([
                        EpisodeResource::jsonEntry('topic_draft_json', 'Topic draft JSON'),
                        EpisodeResource::jsonEntry('screening_json', 'Screening JSON'),
                        EpisodeResource::jsonEntry('editorial_json', 'Editorial JSON'),
                        EpisodeResource::jsonEntry('scenario_selection_json', 'Scenario selection JSON'),
                        EpisodeResource::jsonEntry('metadata', 'Metadata JSON'),
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
            'index' => Pages\ListEpisodeTopics::route('/'),
            'view' => Pages\ViewEpisodeTopic::route('/{record}'),
        ];
    }

    /**
     * EpisodeTopic は管理画面から作成しない。
     */
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * EpisodeTopic は管理画面から編集しない。
     */
    public static function canEdit(Model $record): bool
    {
        return false;
    }

    /**
     * EpisodeTopic は管理画面から削除しない。
     */
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /**
     * EpisodeTopic の bulk delete を許可しない。
     */
    public static function canDeleteAny(): bool
    {
        return false;
    }

    /**
     * 指定 column の重複しない option 一覧を返す。
     *
     * @return array<string, string>
     */
    private static function distinctOptions(string $column): array
    {
        $values = DB::table('episode_topics')
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
}
