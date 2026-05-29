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
                    <h2 class="rp-analysis__panel-title">Screening status distribution</h2>
                </div>
                <div class="rp-analysis__list">
                    @forelse ($this->screeningStatusDistribution() as $row)
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
                    <h2 class="rp-analysis__panel-title">Editorial status distribution</h2>
                </div>
                <div class="rp-analysis__list">
                    @forelse ($this->editorialStatusDistribution() as $row)
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
                    <h2 class="rp-analysis__panel-title">Source distribution</h2>
                </div>
                <div class="rp-analysis__list">
                    @forelse ($this->sourceDistribution() as $row)
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
                <h2 class="rp-analysis__panel-title">Recent Candidate Topics</h2>
            </div>
            <div class="rp-analysis__table-wrap">
                <table class="rp-analysis__table">
                    <thead>
                        <tr>
                            <th>Topic ID</th>
                            <th>Title</th>
                            <th>Screening</th>
                            <th>Editorial</th>
                            <th>Processed at</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->recentCandidateTopics() as $topic)
                            <tr>
                                <td class="rp-analysis__mono">{{ $topic['topic_id'] }}</td>
                                <td>{{ $topic['title'] }}</td>
                                <td>{{ $topic['screening_status'] }}</td>
                                <td>{{ $topic['editorial_status'] }}</td>
                                <td class="rp-analysis__mono">{{ $topic['processed_at'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="rp-analysis__empty" colspan="5">No data</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>
