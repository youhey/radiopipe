<x-filament-panels::page>
    <div class="space-y-6">
        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            @foreach ($this->stats() as $stat)
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ $stat['label'] }}
                    </div>
                    <div class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">
                        {{ $stat['value'] }}
                    </div>
                </div>
            @endforeach
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="border-b border-gray-200 p-4 dark:border-white/10">
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">Status distribution</h2>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-white/10">
                    @forelse ($this->statusDistribution() as $row)
                        <div class="flex items-center justify-between gap-4 p-4">
                            <span class="text-sm text-gray-700 dark:text-gray-200">{{ $row['label'] }}</span>
                            <span class="font-mono text-sm font-semibold text-gray-950 dark:text-white">{{ $row['value'] }}</span>
                        </div>
                    @empty
                        <div class="p-4 text-sm text-gray-500">No data</div>
                    @endforelse
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="border-b border-gray-200 p-4 dark:border-white/10">
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">Character distribution</h2>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-white/10">
                    @forelse ($this->characterDistribution() as $row)
                        <div class="flex items-center justify-between gap-4 p-4">
                            <span class="text-sm text-gray-700 dark:text-gray-200">{{ $row['label'] }}</span>
                            <span class="font-mono text-sm font-semibold text-gray-950 dark:text-white">{{ $row['value'] }}</span>
                        </div>
                    @empty
                        <div class="p-4 text-sm text-gray-500">No data</div>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="border-b border-gray-200 p-4 dark:border-white/10">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Recent Episodes</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-white/5 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3">Episode key</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Title</th>
                            <th class="px-4 py-3">Published at</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @forelse ($this->recentEpisodes() as $episode)
                            <tr>
                                <td class="px-4 py-3 font-mono text-xs">{{ $episode['episode_key'] }}</td>
                                <td class="px-4 py-3">{{ $episode['status'] }}</td>
                                <td class="px-4 py-3">{{ $episode['title'] }}</td>
                                <td class="px-4 py-3 font-mono text-xs">{{ $episode['published_at'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-4 py-3 text-gray-500" colspan="4">No data</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>
