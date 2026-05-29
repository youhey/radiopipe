<?php

namespace Tests\Feature\Cloud;

use App\Cloud\LaravelCloudDeploymentStatus;
use App\Cloud\LaravelCloudDeploymentStatusQuery;
use App\Filament\Widgets\CloudStatusWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * @internal
 */
class LaravelCloudDeploymentStatusQueryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function testStatusIsNotConfiguredWhenTokenOrEnvironmentIsMissing(): void
    {
        Http::fake();
        config([
            'services.laravel_cloud.api_token' => null,
            'services.laravel_cloud.environment_id' => null,
        ]);

        $status = app(LaravelCloudDeploymentStatusQuery::class)->status();

        self::assertFalse($status->configured);
        self::assertFalse($status->available);
        self::assertSame('Laravel Cloud API is not configured.', $status->errorMessage);
        Http::assertNothingSent();
    }

    public function testConfiguredClientCallsEnvironmentDeploymentsEndpointAndMapsAttributes(): void
    {
        config([
            'services.laravel_cloud.api_token' => 'secret-cloud-token',
            'services.laravel_cloud.environment_id' => 'env-example',
        ]);

        Http::fake([
            'https://cloud.laravel.com/api/environments/env-example/deployments' => Http::response([
                'data' => [
                    [
                        'id' => 'depl-example',
                        'type' => 'deployments',
                        'attributes' => [
                            'status' => 'deployment.succeeded',
                            'branch_name' => 'main',
                            'commit_hash' => '1680e0ccfbce2edf75cb07ebf69e44dcca2e922c',
                            'commit_message' => 'feat: add cloud status',
                            'commit_author' => 'IKEDA Youhei',
                            'started_at' => '2026-05-28T10:15:16.000000Z',
                            'finished_at' => '2026-05-28T10:16:36.000000Z',
                            'failure_reason' => null,
                        ],
                    ],
                ],
            ]),
        ]);

        $status = app(LaravelCloudDeploymentStatusQuery::class)->status();

        self::assertTrue($status->configured);
        self::assertTrue($status->available);
        self::assertSame('Succeeded', $status->status);
        self::assertSame('depl-example', $status->deploymentId);
        self::assertSame('main', $status->branch);
        self::assertSame('1680e0ccfbce2edf75cb07ebf69e44dcca2e922c', $status->commitHash);
        self::assertSame('feat: add cloud status', $status->commitMessage);
        self::assertSame('IKEDA Youhei', $status->commitAuthor);

        Http::assertSent(static fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer secret-cloud-token')
            && $request->hasHeader('Accept', 'application/json')
            && $request->url() === 'https://cloud.laravel.com/api/environments/env-example/deployments');
    }

    public function testStatusCanBeInferredFromFailureReason(): void
    {
        $status = $this->queryWithDeploymentAttributes([
            'failure_reason' => 'Build failed.',
        ]);

        self::assertSame('Failed', $status->status);
        self::assertSame('Build failed.', $status->failureReason);
    }

    public function testStatusCanBeInferredAsRunning(): void
    {
        $status = $this->queryWithDeploymentAttributes([
            'started_at' => '2026-05-28T10:15:16.000000Z',
            'finished_at' => null,
        ]);

        self::assertSame('Running', $status->status);
    }

    public function testStatusCanBeInferredAsCompleted(): void
    {
        $status = $this->queryWithDeploymentAttributes([
            'started_at' => '2026-05-28T10:15:16.000000Z',
            'finished_at' => '2026-05-28T10:16:36.000000Z',
        ]);

        self::assertSame('Completed', $status->status);
    }

    public function testStatusCanBeInferredAsUnknown(): void
    {
        $status = $this->queryWithDeploymentAttributes([]);

        self::assertSame('Unknown', $status->status);
    }

    public function testApiFailureReturnsSafeUnavailableState(): void
    {
        config([
            'services.laravel_cloud.api_token' => 'secret-cloud-token',
            'services.laravel_cloud.environment_id' => 'env-example',
        ]);
        Http::fake([
            'https://cloud.laravel.com/api/environments/env-example/deployments' => Http::response([], 500),
        ]);

        $status = app(LaravelCloudDeploymentStatusQuery::class)->status();

        self::assertTrue($status->configured);
        self::assertFalse($status->available);
        self::assertSame('Laravel Cloud API request failed.', $status->errorMessage);
    }

    public function testEmptyDeploymentsReturnsSafeEmptyState(): void
    {
        config([
            'services.laravel_cloud.api_token' => 'secret-cloud-token',
            'services.laravel_cloud.environment_id' => 'env-example',
        ]);
        Http::fake([
            'https://cloud.laravel.com/api/environments/env-example/deployments' => Http::response(['data' => []]),
        ]);

        $status = app(LaravelCloudDeploymentStatusQuery::class)->status();

        self::assertTrue($status->configured);
        self::assertFalse($status->available);
        self::assertSame('No deployments found.', $status->errorMessage);
    }

    public function testCloudStatusWidgetDoesNotRenderApiTokenOnFailure(): void
    {
        config([
            'services.laravel_cloud.api_token' => 'secret-cloud-token',
            'services.laravel_cloud.environment_id' => 'env-example',
        ]);
        Http::fake([
            'https://cloud.laravel.com/api/environments/env-example/deployments' => Http::response([], 500),
        ]);

        $component = Livewire::test(CloudStatusWidget::class);
        $component->assertSee('Laravel Cloud API request failed.');
        $component->assertDontSee('secret-cloud-token');
    }

    /**
     * 指定 attributes で query 結果を返す。
     *
     * @param array<string, mixed> $attributes
     */
    private function queryWithDeploymentAttributes(array $attributes): LaravelCloudDeploymentStatus
    {
        Cache::flush();
        config([
            'services.laravel_cloud.api_token' => 'secret-cloud-token',
            'services.laravel_cloud.environment_id' => 'env-example',
        ]);
        Http::fake([
            'https://cloud.laravel.com/api/environments/env-example/deployments' => Http::response([
                'data' => [
                    [
                        'id' => 'depl-example',
                        'type' => 'deployments',
                        'attributes' => $attributes,
                    ],
                ],
            ]),
        ]);

        return app(LaravelCloudDeploymentStatusQuery::class)->status();
    }
}
