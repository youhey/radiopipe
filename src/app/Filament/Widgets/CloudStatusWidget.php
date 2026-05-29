<?php

namespace App\Filament\Widgets;

use App\Cloud\LaravelCloudDeploymentStatus;
use App\Cloud\LaravelCloudDeploymentStatusQuery;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

/**
 * Laravel Cloud deployment status を Dashboard に表示する widget。
 */
class CloudStatusWidget extends Widget
{
    protected string $view = 'filament.widgets.cloud-status';

    protected array|int|string $columnSpan = 'full';

    /**
     * View に渡す deployment status data を返す。
     *
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $status = app(LaravelCloudDeploymentStatusQuery::class)->status();

        return [
            'status' => $status,
            'rows' => $this->rows($status),
        ];
    }

    /**
     * 詳細 grid に表示する行を返す。
     *
     * @return list<array{label: string, value: string}>
     */
    private function rows(LaravelCloudDeploymentStatus $status): array
    {
        return [
            ['label' => 'Branch', 'value' => $this->value($status->branch)],
            ['label' => 'Commit', 'value' => $this->commitHash($status->commitHash)],
            ['label' => 'Commit author', 'value' => $this->value($status->commitAuthor)],
            ['label' => 'Started at', 'value' => $this->timestamp($status->startedAt)],
            ['label' => 'Finished at', 'value' => $this->timestamp($status->finishedAt)],
            ['label' => 'Failure reason', 'value' => $this->value($status->failureReason)],
        ];
    }

    /**
     * null 値を表示用文字列へ変換する。
     */
    private function value(?string $value): string
    {
        return $value ?? 'N/A';
    }

    /**
     * commit hash を短縮表示する。
     */
    private function commitHash(?string $value): string
    {
        if ($value === null) {
            return 'N/A';
        }

        return strlen($value) > 12 ? substr($value, 0, 12) : $value;
    }

    /**
     * timestamp を Dashboard 表示用に整形する。
     */
    private function timestamp(?string $value): string
    {
        if ($value === null) {
            return 'N/A';
        }

        return Carbon::parse($value)->format('Y-m-d H:i:s T');
    }
}
