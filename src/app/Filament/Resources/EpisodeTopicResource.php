<?php

namespace App\Filament\Resources;

use App\Filament\Infolists\JsonPrettyEntry;
use App\Filament\Resources\EpisodeTopicResource\Pages;
use App\Models\EpisodeTopic;
use BackedEnum;
use Filament\Actions\Action;
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
use Illuminate\Support\Str;
use JsonException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Episode topic processing snapshot を分析用に参照する Filament Resource。
 */
class EpisodeTopicResource extends Resource
{
    protected static ?string $model = EpisodeTopic::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

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
                Action::make('export')
                    ->label('Export')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->action(static fn (EpisodeTopic $record): StreamedResponse => self::jsonDownloadResponse(
                        self::exportPayload($record),
                        self::exportFilename($record),
                    )),
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
                        self::summaryEntry('episode_id')
                            ->numeric(),
                        self::summaryEntry('episode.episode_key')
                            ->label('Episode key'),
                        self::summaryEntry('topic_id'),
                        self::summaryEntry('title')
                            ->columnSpanFull(),
                        self::summaryEntry('screening_status')
                            ->badge(),
                        self::summaryEntry('editorial_status')
                            ->badge(),
                        self::summaryEntry('scenario_selection_status')
                            ->badge(),
                        self::summaryEntry('sort_order')
                            ->numeric(),
                        self::summaryEntry('created_at')
                            ->dateTime(),
                    ])
                    ->columns(3),
                Section::make('Upstream/source metadata')
                    ->schema([
                        self::summaryEntry('upstream_provider'),
                        self::summaryEntry('upstream_id'),
                        self::summaryEntry('source_name'),
                        self::summaryEntry('source_type'),
                        self::summaryEntry('url')
                            ->url(static fn (?string $state): ?string => $state)
                            ->openUrlInNewTab()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Raw JSON')
                    ->schema([
                        JsonPrettyEntry::make('topic_draft_json', 'Topic draft JSON'),
                        JsonPrettyEntry::make('screening_json', 'Screening JSON'),
                        JsonPrettyEntry::make('editorial_json', 'Editorial JSON'),
                        JsonPrettyEntry::make('scenario_selection_json', 'Scenario selection JSON'),
                        JsonPrettyEntry::make('metadata', 'Metadata JSON'),
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
     * EpisodeTopic export 用の JSON 互換配列を返す。
     *
     * @return array<string, mixed>
     */
    public static function exportPayload(EpisodeTopic $topic): array
    {
        $topic->loadMissing('episode');

        return [
            'schema_version' => '1.0',
            'type' => 'episode_topic',
            'episode' => [
                'id' => $topic->episode?->id,
                'episode_key' => $topic->episode?->episode_key,
                'status' => $topic->episode?->status,
                'title' => $topic->episode?->title,
                'published_at' => EpisodeResource::dateTimeString($topic->episode?->published_at),
            ],
            'episode_topic' => [
                'id' => $topic->id,
                'episode_id' => $topic->episode_id,
                'topic_id' => $topic->topic_id,
                'upstream_provider' => $topic->upstream_provider,
                'upstream_id' => $topic->upstream_id,
                'source_name' => $topic->source_name,
                'source_type' => $topic->source_type,
                'title' => $topic->title,
                'url' => $topic->url,
                'screening_status' => $topic->screening_status,
                'editorial_status' => $topic->editorial_status,
                'scenario_selection_status' => $topic->scenario_selection_status,
                'sort_order' => $topic->sort_order,
                'topic_draft_json' => $topic->topic_draft_json,
                'screening_json' => $topic->screening_json,
                'editorial_json' => $topic->editorial_json,
                'scenario_selection_json' => $topic->scenario_selection_json,
                'metadata' => $topic->metadata,
                'created_at' => EpisodeResource::dateTimeString($topic->created_at),
                'updated_at' => EpisodeResource::dateTimeString($topic->updated_at),
            ],
        ];
    }

    /**
     * JSON payload を download response として返す。
     *
     * @param array<string, mixed> $payload
     *
     * @throws JsonException
     */
    public static function jsonDownloadResponse(array $payload, string $fileName): StreamedResponse
    {
        return EpisodeResource::jsonDownloadResponse($payload, $fileName);
    }

    /**
     * EpisodeTopic export 用のファイル名を返す。
     */
    public static function exportFilename(EpisodeTopic $topic): string
    {
        return sprintf('episode-topic-%s.json', Str::slug(str_replace(':', '-', $topic->topic_id)));
    }

    /**
     * 要約項目用のカード状 entry を返す。
     */
    private static function summaryEntry(string $name): TextEntry
    {
        return TextEntry::make($name)
            ->extraEntryWrapperAttributes(EpisodeResource::summaryEntryWrapperAttributes());
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
