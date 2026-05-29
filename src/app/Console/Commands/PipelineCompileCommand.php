<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Topic nomination と Episode compilation を順に実行する scheduled pipeline。
 */
class PipelineCompileCommand extends Command
{
    protected $signature = 'radiopipe:pipeline:compile';

    protected $description = 'Run topic nomination and episode compilation as one scheduled pipeline.';

    /**
     * scheduled pipeline を実行する。
     */
    public function handle(): int
    {
        $limit = $this->limit();

        $nominateExitCode = Artisan::call('radiopipe:topics:nominate', [
            '--limit' => $limit,
        ]);
        $this->output->write(Artisan::output());

        if ($nominateExitCode !== self::SUCCESS) {
            return $nominateExitCode;
        }

        $compileOptions = [
            '--limit' => $limit,
        ];
        $character = $this->character();

        if ($character !== null) {
            $compileOptions['--character'] = $character;
        }

        $compileExitCode = Artisan::call('radiopipe:episodes:compile', $compileOptions);
        $this->output->write(Artisan::output());

        return $compileExitCode;
    }

    private function limit(): int
    {
        $limit = config('radiopipe.pipeline.limit', 20);

        return is_numeric($limit) ? max(1, (int) $limit) : 20;
    }

    private function character(): ?string
    {
        $character = config('radiopipe.pipeline.character');

        if (! is_string($character)) {
            return null;
        }

        $character = trim($character);

        return $character !== '' ? $character : null;
    }
}
