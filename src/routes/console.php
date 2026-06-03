<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

$compileRadiopipePipeline = static function (): int {
    $nominateExitCode = Artisan::call('radiopipe:topics:nominate');

    if ($nominateExitCode !== 0) {
        return $nominateExitCode;
    }

    return Artisan::call('radiopipe:episodes:compile');
};

foreach (['09:00', '13:00', '17:00'] as $time) {
    Schedule::call($compileRadiopipePipeline)
        ->dailyAt($time)
        ->timezone('Asia/Tokyo')
        ->name("radiopipe:pipeline:compile:{$time}")
        ->withoutOverlapping(30);
}
