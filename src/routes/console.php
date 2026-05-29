<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(static function (): int {
    $nominateExitCode = Artisan::call('radiopipe:topics:nominate');

    if ($nominateExitCode !== 0) {
        return $nominateExitCode;
    }

    return Artisan::call('radiopipe:episodes:compile');
})
    ->everyTenMinutes()
    ->name('radiopipe:pipeline:compile')
    ->withoutOverlapping(30);
