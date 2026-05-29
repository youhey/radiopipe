<x-filament-panels::page>
    @include('filament.pages.partials.analysis-styles')

    <div class="rp-analysis">
        <section class="rp-analysis__stats">
            @foreach ($this->stats() as $stat)
                <div class="rp-analysis__stat">
                    <div class="rp-analysis__stat-label">{{ $stat['label'] }}</div>
                    <div class="rp-analysis__stat-value">{{ $stat['value'] }}</div>
                </div>
            @endforeach
        </section>

        <section class="rp-analysis__grid">
            <div class="rp-analysis__panel">
                <div class="rp-analysis__panel-header">
                    <h2 class="rp-analysis__panel-title">Status distribution</h2>
                </div>
                <div class="rp-analysis__list">
                    @forelse ($this->statusDistribution() as $row)
                        <div class="rp-analysis__list-row">
                            <span class="rp-analysis__list-label">{{ $row['label'] }}</span>
                            <span class="rp-analysis__list-value">{{ $row['value'] }}</span>
                        </div>
                    @empty
                        <div class="rp-analysis__empty">No data</div>
                    @endforelse
                </div>
            </div>

            <div class="rp-analysis__panel">
                <div class="rp-analysis__panel-header">
                    <h2 class="rp-analysis__panel-title">Character distribution</h2>
                </div>
                <div class="rp-analysis__list">
                    @forelse ($this->characterDistribution() as $row)
                        <div class="rp-analysis__list-row">
                            <span class="rp-analysis__list-label">{{ $row['label'] }}</span>
                            <span class="rp-analysis__list-value">{{ $row['value'] }}</span>
                        </div>
                    @empty
                        <div class="rp-analysis__empty">No data</div>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="rp-analysis__panel">
            <div class="rp-analysis__panel-header">
                <h2 class="rp-analysis__panel-title">Recent Episodes</h2>
            </div>
            <div class="rp-analysis__table-wrap">
                <table class="rp-analysis__table">
                    <thead>
                        <tr>
                            <th>Episode key</th>
                            <th>Status</th>
                            <th>Title</th>
                            <th>Published at</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->recentEpisodes() as $episode)
                            <tr>
                                <td class="rp-analysis__mono">{{ $episode['episode_key'] }}</td>
                                <td>{{ $episode['status'] }}</td>
                                <td>{{ $episode['title'] }}</td>
                                <td class="rp-analysis__mono">{{ $episode['published_at'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="rp-analysis__empty" colspan="4">No data</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>
