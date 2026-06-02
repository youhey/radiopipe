<?php

namespace App\Filament\Resources\TopicScreeningKeywordRuleResource\Pages;

use App\Filament\Resources\TopicScreeningKeywordRuleResource;
use App\Topics\Screening\TopicScreeningKeywordRuleSpreadsheet;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Topic screening keyword rule の一覧ページ。
 */
class ListTopicScreeningKeywordRules extends ListRecords
{
    protected static string $resource = TopicScreeningKeywordRuleResource::class;

    /**
     * ヘッダーに表示する操作を返す。
     *
     * @return array<int, \Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('importExcel')
                ->label('Import Excel')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->modalHeading('Import screening rules')
                ->form([
                    FileUpload::make('file')
                        ->label('Excel file')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ])
                        ->storeFiles(false)
                        ->required(),
                ])
                ->action(function (array $data, TopicScreeningKeywordRuleSpreadsheet $spreadsheet): void {
                    $result = $spreadsheet->import($this->uploadedFilePath($data));

                    Notification::make()
                        ->success()
                        ->title('Screening rules imported.')
                        ->body(sprintf(
                            'Created: %d / Updated: %d / Skipped: %d',
                            $result->createdCount(),
                            $result->updatedCount(),
                            $result->skippedCount(),
                        ))
                        ->send();
                }),
            Action::make('exportExcel')
                ->label('Export Excel')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->action(static fn (TopicScreeningKeywordRuleSpreadsheet $spreadsheet): StreamedResponse => $spreadsheet->downloadResponse()),
        ];
    }

    /**
     * upload action data から一時ファイル path を取り出す。
     *
     * @param array<array-key, mixed> $data
     */
    private function uploadedFilePath(array $data): string
    {
        $file = $data['file'] ?? null;

        if (! $file instanceof TemporaryUploadedFile) {
            throw new InvalidArgumentException('Topic screening keyword rule import file is missing.');
        }

        $path = $file->getRealPath();

        if ($path === '') {
            throw new InvalidArgumentException('Topic screening keyword rule import file is not readable.');
        }

        return $path;
    }
}
