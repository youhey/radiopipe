<?php

namespace App\Providers;

use App\News\NewsProvider;
use App\News\NewsProviderManager;
use App\Scenarios\FakeScenarioGenerator;
use App\Scenarios\ScenarioGenerator;
use App\Topics\Editorial\FakeTopicEditorialAnalyzer;
use App\Topics\Editorial\OpenAiTopicEditorialAnalyzer;
use App\Topics\Editorial\TopicEditorialAnalyzer;
use App\Upstream\UpstreamProvider;
use App\Upstream\UpstreamProviderManager;
use App\Weather\WeatherProvider;
use App\Weather\WeatherProviderManager;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(NewsProviderManager::class);
        $this->app->singleton(UpstreamProviderManager::class);
        $this->app->singleton(WeatherProviderManager::class);

        $this->app->bind(NewsProvider::class, function (): NewsProvider {
            return $this->app->make(NewsProviderManager::class)->driver();
        });

        $this->app->bind(UpstreamProvider::class, function (): UpstreamProvider {
            return $this->app->make(UpstreamProviderManager::class)->driver();
        });

        $this->app->bind(WeatherProvider::class, function (): WeatherProvider {
            return $this->app->make(WeatherProviderManager::class)->driver();
        });

        $this->app->bind(TopicEditorialAnalyzer::class, function (): TopicEditorialAnalyzer {
            $analyzer = config('radiopipe.topic_editorial.analyzer', 'fake');
            $resolvedAnalyzer = is_string($analyzer) && $analyzer !== '' ? $analyzer : 'fake';

            return match ($resolvedAnalyzer) {
                'fake' => new FakeTopicEditorialAnalyzer(),
                'openai' => new OpenAiTopicEditorialAnalyzer(
                    $this->nullableStringConfig('radiopipe.openai.api_key'),
                    $this->stringConfig('radiopipe.topic_editorial.model', 'gpt-5.4-mini'),
                    $this->intConfig('radiopipe.openai.request_timeout', 60),
                    $this->intConfig('radiopipe.openai.max_retries', 2),
                ),
                default => throw new InvalidArgumentException("Unsupported radiopipe topic editorial analyzer [{$resolvedAnalyzer}]."),
            };
        });

        $this->app->bind(ScenarioGenerator::class, function (): ScenarioGenerator {
            $generator = config('radiopipe.scenario.generator', 'fake');
            $resolvedGenerator = is_string($generator) && $generator !== '' ? $generator : 'fake';

            return match ($resolvedGenerator) {
                'fake' => new FakeScenarioGenerator(
                    maxTopics: $this->intConfig('radiopipe.scenario.max_topics', 5),
                ),
                default => throw new InvalidArgumentException("Unsupported radiopipe scenario generator [{$resolvedGenerator}]."),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
    }

    /**
     * 文字列設定を取得します。
     */
    private function stringConfig(string $key, string $default): string
    {
        $value = config($key, $default);

        if (! is_string($value) || $value === '') {
            return $default;
        }

        return $value;
    }

    /**
     * nullable な文字列設定を取得します。
     */
    private function nullableStringConfig(string $key): ?string
    {
        $value = config($key);

        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    /**
     * 整数設定を取得します。
     */
    private function intConfig(string $key, int $default): int
    {
        $value = config($key, $default);

        if (! is_int($value)) {
            return $default;
        }

        return $value;
    }
}
