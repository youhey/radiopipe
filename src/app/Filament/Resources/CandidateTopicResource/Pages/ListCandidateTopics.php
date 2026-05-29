<?php

namespace App\Filament\Resources\CandidateTopicResource\Pages;

use App\Filament\Resources\CandidateTopicResource;
use Filament\Resources\Pages\ListRecords;

/**
 * CandidateTopic の一覧ページ。
 */
class ListCandidateTopics extends ListRecords
{
    protected static string $resource = CandidateTopicResource::class;
}
