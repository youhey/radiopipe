<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;

/**
 * Episode generation command の scheduled task 登録を担当します。
 */
class EpisodeGenerationSchedule
{
    /**
     * 設定が有効な場合のみ Episode generation command を scheduler に登録します。
     */
    public function register(Schedule $schedule): void
    {
        if ($this->enabled() === false) {
            return;
        }

        $parameters = [
            '--limit' => $this->limit(),
        ];

        $character = $this->character();

        if ($character !== null) {
            $parameters['--character'] = $character;
        }

        $schedule->command('radiopipe:episodes:generate', $parameters)
            ->dailyAt($this->time())
            ->timezone($this->timezone())
            ->withoutOverlapping()
            ->name('radiopipe episode generation')
            ->description('radiopipe episode generation');
    }

    /**
     * Episode generation schedule が有効かどうかを返します。
     */
    private function enabled(): bool
    {
        return config('radiopipe.episode_schedule.enabled', false) === true;
    }

    /**
     * scheduled command に渡す取得件数上限を返します。
     */
    private function limit(): int
    {
        $limit = config('radiopipe.episode_schedule.limit', 20);

        return is_numeric($limit) ? (int) $limit : 20;
    }

    /**
     * Episode generation を実行する日次時刻を返します。
     */
    private function time(): string
    {
        $time = config('radiopipe.episode_schedule.time', '07:00');

        return is_string($time) && trim($time) !== '' ? trim($time) : '07:00';
    }

    /**
     * Episode generation schedule の timezone を返します。
     */
    private function timezone(): string
    {
        $timezone = config('radiopipe.episode_schedule.timezone', 'Asia/Tokyo');

        return is_string($timezone) && trim($timezone) !== '' ? trim($timezone) : 'Asia/Tokyo';
    }

    /**
     * scheduled command に渡す character key を返します。
     */
    private function character(): ?string
    {
        $character = config('radiopipe.episode_schedule.character');

        if (! is_string($character)) {
            return null;
        }

        $character = trim($character);

        return $character !== '' ? $character : null;
    }
}
