<?php

namespace App\Filament\Resources;

use App\Filament\Infolists\JsonPrettyEntry;
use App\Filament\Resources\CandidateTopicResource\Pages;
use App\Models\CandidateTopic;
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
 * CandidateTopic を分析用に参照する Filament Resource。
 */
class CandidateTopicResource extends Resource
{
    protected static ?string $model = CandidateTopic::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'Candidate Topics';

    protected static ?string $modelLabel = 'Candidate Topic';

    protected static ?string $pluralModelLabel = 'Candidate Topics';

    /**
     * 一覧テーブルを構成する。
     */
    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('processed_at', 'desc')
            ->columns([
                TextColumn::make('topic_id')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('source_name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('source_type')
                    ->sortable(),
                TextColumn::make('upstream_provider')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('upstream_id')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('topic_title')
                    ->label('Title')
                    ->state(static fn (CandidateTopic $record): ?string => self::topicTitle($record))
                    ->limit(70),
                TextColumn::make('screening_status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('screening_score')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('editorial_status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('editorial_score')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('article_published_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('processed_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('source_name')
                    ->options(static fn (): array => self::distinctOptions('source_name'))
                    ->searchable(),
                SelectFilter::make('source_type')
                    ->options(static fn (): array => self::distinctOptions('source_type')),
                SelectFilter::make('upstream_provider')
                    ->options(static fn (): array => self::distinctOptions('upstream_provider'))
                    ->searchable(),
                SelectFilter::make('screening_status')
                    ->options(static fn (): array => self::distinctOptions('screening_status')),
                SelectFilter::make('editorial_status')
                    ->options(static fn (): array => self::distinctOptions('editorial_status')),
                self::dateRangeFilter('article_published_at', 'article_published_at'),
                self::dateRangeFilter('processed_at', 'processed_at'),
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
                        self::summaryEntry('topic_id'),
                        TextEntry::make('topic_title')
                            ->label('Title')
                            ->state(static fn (CandidateTopic $record): ?string => self::topicTitle($record))
                            ->extraEntryWrapperAttributes(EpisodeResource::summaryEntryWrapperAttributes())
                            ->columnSpanFull(),
                        self::summaryEntry('screening_status')
                            ->badge(),
                        self::summaryEntry('screening_score')
                            ->numeric(),
                        self::summaryEntry('editorial_status')
                            ->badge(),
                        self::summaryEntry('editorial_score')
                            ->numeric(),
                        self::summaryEntry('processed_at')
                            ->dateTime(),
                        self::summaryEntry('updated_at')
                            ->dateTime(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make('Source/upstream metadata')
                    ->schema([
                        self::summaryEntry('source_name'),
                        self::summaryEntry('source_type'),
                        self::summaryEntry('upstream_provider'),
                        self::summaryEntry('upstream_id'),
                        self::summaryEntry('article_published_at')
                            ->dateTime(),
                        self::summaryEntry('article_url')
                            ->label('Article URL')
                            ->url(static fn (?string $state): ?string => $state)
                            ->openUrlInNewTab()
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make('Candidate fingerprint')
                    ->schema([
                        self::summaryEntry('candidate_fingerprint')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Section::make('Raw JSON')
                    ->schema([
                        JsonPrettyEntry::make('topic_draft_json', 'TopicDraft JSON'),
                        JsonPrettyEntry::make('screening_json', 'Screening JSON'),
                        JsonPrettyEntry::make('editorial_json', 'Editorial JSON'),
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
            'index' => Pages\ListCandidateTopics::route('/'),
            'view' => Pages\ViewCandidateTopic::route('/{record}'),
        ];
    }

    /**
     * CandidateTopic は管理画面から作成しない。
     */
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * CandidateTopic は管理画面から編集しない。
     */
    public static function canEdit(Model $record): bool
    {
        return false;
    }

    /**
     * CandidateTopic は管理画面から削除しない。
     */
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /**
     * CandidateTopic の bulk delete を許可しない。
     */
    public static function canDeleteAny(): bool
    {
        return false;
    }

    /**
     * CandidateTopic の表示用タイトルを返す。
     */
    public static function topicTitle(CandidateTopic $topic): ?string
    {
        $draft = $topic->getAttribute('topic_draft_json');

        if (! is_array($draft)) {
            return null;
        }

        $title = $draft['title'] ?? null;

        return is_string($title) && $title !== '' ? $title : null;
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
        $values = DB::table('candidate_topics')
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
