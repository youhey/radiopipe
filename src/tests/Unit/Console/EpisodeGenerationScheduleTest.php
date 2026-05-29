<?php

namespace Tests\Unit\Console;

use App\Console\EpisodeGenerationSchedule;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * @internal
 */
class EpisodeGenerationScheduleTest extends TestCase
{
    public function testScheduleIsNotRegisteredWhenDisabled(): void
    {
        config(['radiopipe.pipeline.schedule_enabled' => false]);

        $schedule = new Schedule();

        (new EpisodeGenerationSchedule())->register($schedule);

        self::assertSame([], $schedule->events());
    }

    public function testScheduleRegistersPipelineCompileWithConfiguredIntervalAndTimezone(): void
    {
        config([
            'radiopipe.pipeline.schedule_enabled' => true,
            'radiopipe.pipeline.interval_minutes' => 10,
            'radiopipe.pipeline.timezone' => 'Asia/Tokyo',
        ]);

        $schedule = new Schedule();

        (new EpisodeGenerationSchedule())->register($schedule);

        $event = $this->singleEvent($schedule);

        self::assertStringContainsString('radiopipe:pipeline:compile', (string) $event->command);
        self::assertStringNotContainsString('radiopipe:episodes:generate', (string) $event->command);
        self::assertSame('*/10 * * * *', $event->expression);
        self::assertSame('Asia/Tokyo', $event->timezone);
        self::assertTrue($event->withoutOverlapping);
        self::assertSame(30, $event->expiresAt);
        self::assertSame('radiopipe:pipeline:compile', $event->description);
    }

    public function testUnsupportedIntervalFailsClearly(): void
    {
        config([
            'radiopipe.pipeline.schedule_enabled' => true,
            'radiopipe.pipeline.interval_minutes' => 7,
            'radiopipe.pipeline.timezone' => 'Asia/Tokyo',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported pipeline schedule interval.');

        (new EpisodeGenerationSchedule())->register(new Schedule());
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
