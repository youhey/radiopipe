<?php

namespace Tests\Unit\Console;

use App\Console\EpisodeGenerationSchedule;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * @internal
 */
class EpisodeGenerationScheduleTest extends TestCase
{
    public function testScheduleIsNotRegisteredWhenDisabled(): void
    {
        config(['radiopipe.episode_schedule.enabled' => false]);

        $schedule = new Schedule();

        (new EpisodeGenerationSchedule())->register($schedule);

        self::assertSame([], $schedule->events());
    }

    public function testScheduleIsRegisteredWithConfiguredTimeTimezoneAndLimit(): void
    {
        config([
            'radiopipe.episode_schedule.enabled' => true,
            'radiopipe.episode_schedule.time' => '06:30',
            'radiopipe.episode_schedule.timezone' => 'Asia/Tokyo',
            'radiopipe.episode_schedule.limit' => 12,
            'radiopipe.episode_schedule.character' => '',
        ]);

        $schedule = new Schedule();

        (new EpisodeGenerationSchedule())->register($schedule);

        $event = $this->singleEvent($schedule);

        self::assertStringContainsString('radiopipe:episodes:generate', (string) $event->command);
        self::assertStringContainsString('--limit=12', (string) $event->command);
        self::assertStringNotContainsString('--character=', (string) $event->command);
        self::assertSame('30 6 * * *', $event->expression);
        self::assertSame('Asia/Tokyo', $event->timezone);
        self::assertTrue($event->withoutOverlapping);
        self::assertSame('radiopipe episode generation', $event->description);
    }

    public function testScheduleIncludesConfiguredCharacterWhenSet(): void
    {
        config([
            'radiopipe.episode_schedule.enabled' => true,
            'radiopipe.episode_schedule.time' => '07:00',
            'radiopipe.episode_schedule.timezone' => 'Asia/Tokyo',
            'radiopipe.episode_schedule.limit' => 20,
            'radiopipe.episode_schedule.character' => ' neko_nyan_balanced_radio ',
        ]);

        $schedule = new Schedule();

        (new EpisodeGenerationSchedule())->register($schedule);

        $event = $this->singleEvent($schedule);

        self::assertStringContainsString('--limit=20', (string) $event->command);
        self::assertStringContainsString("--character='neko_nyan_balanced_radio'", (string) $event->command);
    }

    /**
     * 登録された scheduled event を1件だけ取り出します。
     */
    private function singleEvent(Schedule $schedule): Event
    {
        $events = $schedule->events();

        self::assertCount(1, $events);
        self::assertInstanceOf(Event::class, $events[0]);

        return $events[0];
    }
}
