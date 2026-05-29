<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use InvalidArgumentException;

/**
 * radiopipe pipeline compile command の scheduled task 登録を担当します。
 */
class EpisodeGenerationSchedule
{
    /**
     * 設定が有効な場合のみ pipeline compile command を scheduler に登録します。
     */
    public function register(Schedule $schedule): void
    {
        if ($this->enabled() === false) {
            return;
        }

        $event = $schedule->command('radiopipe:pipeline:compile');

        match ($this->intervalMinutes()) {
            5 => $event->everyFiveMinutes(),
            10 => $event->everyTenMinutes(),
            15 => $event->everyFifteenMinutes(),
            30 => $event->everyThirtyMinutes(),
            default => throw new InvalidArgumentException('Unsupported pipeline schedule interval. Supported values are 5, 10, 15, and 30 minutes.'),
        };

        $event
            ->timezone($this->timezone())
            ->withoutOverlapping(30)
            ->name('radiopipe:pipeline:compile')
            ->description('radiopipe:pipeline:compile');
    }

    /**
     * pipeline schedule が有効かどうかを返します。
     */
    private function enabled(): bool
    {
        return config('radiopipe.pipeline.schedule_enabled', false) === true;
    }

    /**
     * pipeline compile の実行間隔を分単位で返します。
     */
    private function intervalMinutes(): int
    {
        $interval = config('radiopipe.pipeline.interval_minutes', 10);

        return is_numeric($interval) ? (int) $interval : 10;
    }

    /**
     * pipeline schedule の timezone を返します。
     */
    private function timezone(): string
    {
        $timezone = config('radiopipe.pipeline.timezone', 'Asia/Tokyo');

        return is_string($timezone) && trim($timezone) !== '' ? trim($timezone) : 'Asia/Tokyo';
    }
}
