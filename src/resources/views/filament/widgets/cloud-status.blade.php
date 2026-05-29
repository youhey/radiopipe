<x-filament-widgets::widget>
    @once
        <style>
            .cloud-status-stack {
                display: flex;
                flex-direction: column;
                gap: 1rem;
            }

            .cloud-status-summary {
                display: flex;
                flex-wrap: wrap;
                align-items: flex-start;
                justify-content: space-between;
                gap: 0.75rem;
                min-width: 0;
                padding: 1rem;
                border: 1px solid rgb(229, 231, 235);
                border-radius: 0.75rem;
                background: rgb(249, 250, 251);
            }

            .dark .cloud-status-summary {
                border-color: rgba(255, 255, 255, 0.1);
                background: rgba(255, 255, 255, 0.05);
            }

            .cloud-status-summary-main {
                min-width: 0;
            }

            .cloud-status-eyebrow {
                color: rgb(107, 114, 128);
                font-size: 0.75rem;
                font-weight: 600;
                letter-spacing: 0.025em;
                line-height: 1rem;
                text-transform: uppercase;
            }

            .cloud-status-title {
                margin-top: 0.25rem;
                overflow: hidden;
                color: rgb(17, 24, 39);
                font-size: 1rem;
                font-weight: 700;
                line-height: 1.5rem;
                overflow-wrap: anywhere;
            }

            .cloud-status-message {
                margin-top: 0.5rem;
                color: rgb(75, 85, 99);
                font-size: 0.875rem;
                line-height: 1.25rem;
            }

            .cloud-status-badge {
                flex: 0 0 auto;
                padding: 0.25rem 0.625rem;
                border-radius: 9999px;
                background: rgb(229, 231, 235);
                color: rgb(55, 65, 81);
                font-size: 0.75rem;
                font-weight: 700;
                line-height: 1rem;
            }

            .cloud-status-badge--success {
                background: rgb(220, 252, 231);
                color: rgb(22, 101, 52);
            }

            .cloud-status-badge--danger {
                background: rgb(254, 226, 226);
                color: rgb(153, 27, 27);
            }

            .cloud-status-badge--warning {
                background: rgb(254, 249, 195);
                color: rgb(133, 77, 14);
            }

            .cloud-status-grid {
                display: grid;
                grid-template-columns: repeat(1, minmax(0, 1fr));
                gap: 0.75rem;
            }

            @media (min-width: 768px) {
                .cloud-status-grid {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }

            @media (min-width: 1280px) {
                .cloud-status-grid {
                    grid-template-columns: repeat(3, minmax(0, 1fr));
                }
            }

            .cloud-status-item {
                min-width: 0;
                padding: 0.875rem 1rem;
                border: 1px solid rgb(229, 231, 235);
                border-radius: 0.75rem;
                background: rgb(255, 255, 255);
                box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            }

            .dark .cloud-status-item {
                border-color: rgba(255, 255, 255, 0.1);
                background: rgba(255, 255, 255, 0.05);
            }

            .cloud-status-label {
                color: rgb(107, 114, 128);
                font-size: 0.75rem;
                font-weight: 600;
                letter-spacing: 0.025em;
                line-height: 1rem;
                text-transform: uppercase;
            }

            .cloud-status-value {
                display: -webkit-box;
                margin-top: 0.375rem;
                overflow: hidden;
                overflow-wrap: anywhere;
                color: rgb(17, 24, 39);
                font-size: 0.875rem;
                font-weight: 500;
                line-height: 1.25rem;
                -webkit-box-orient: vertical;
                -webkit-line-clamp: 3;
            }

            .dark .cloud-status-title,
            .dark .cloud-status-value {
                color: rgb(255, 255, 255);
            }

            .dark .cloud-status-message {
                color: rgb(209, 213, 219);
            }
        </style>
    @endonce

    <x-filament::section
        description="Laravel Cloud deployment status"
        heading="Laravel Cloud Status"
    >
        <div class="cloud-status-stack">
            @if (! $status->configured)
                <div class="cloud-status-summary">
                    <div class="cloud-status-summary-main">
                        <div class="cloud-status-eyebrow">Not configured</div>
                        <div class="cloud-status-title">Laravel Cloud API is not configured.</div>
                    </div>
                    <span class="cloud-status-badge cloud-status-badge--warning">Not configured</span>
                </div>
            @elseif (! $status->available)
                <div class="cloud-status-summary">
                    <div class="cloud-status-summary-main">
                        <div class="cloud-status-eyebrow">Unavailable</div>
                        <div class="cloud-status-title">{{ $status->errorMessage }}</div>
                    </div>
                    <span class="cloud-status-badge cloud-status-badge--warning">Unavailable</span>
                </div>
            @else
                <div class="cloud-status-summary">
                    <div class="cloud-status-summary-main">
                        <div class="cloud-status-eyebrow">Last deployment</div>
                        <div class="cloud-status-title">{{ $status->commitMessage ?? 'N/A' }}</div>
                        <div class="cloud-status-message">
                            {{ $status->commitHash === null ? 'N/A' : str($status->commitHash)->limit(12, '') }}
                        </div>
                    </div>
                    <span @class([
                        'cloud-status-badge',
                        'cloud-status-badge--success' => str($status->status)->lower()->contains(['succeeded', 'completed']),
                        'cloud-status-badge--danger' => str($status->status)->lower()->contains(['failed']),
                        'cloud-status-badge--warning' => str($status->status)->lower()->contains(['running', 'unknown']),
                    ])>{{ $status->status }}</span>
                </div>

                <div class="cloud-status-grid">
                    @foreach ($rows as $row)
                        <div class="cloud-status-item">
                            <div class="cloud-status-label">{{ $row['label'] }}</div>
                            <div class="cloud-status-value">{{ $row['value'] }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
