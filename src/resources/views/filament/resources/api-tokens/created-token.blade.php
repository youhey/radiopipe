@php
    $textareaId = 'created-api-token-' . str()->random(8);
@endphp

<div
    class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950 shadow-sm dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100"
>
    <div class="space-y-2">
        <div class="font-semibold">API token created</div>
        <div>
            Copy this token now. It will not be shown again.
        </div>

        @if ($userEmail || $tokenName)
            <div class="text-xs text-amber-800 dark:text-amber-200">
                @if ($userEmail)
                    User: {{ $userEmail }}
                @endif

                @if ($tokenName)
                    Token: {{ $tokenName }}
                @endif
            </div>
        @endif

        <div class="flex flex-col gap-2 sm:flex-row">
            <textarea
                id="{{ $textareaId }}"
                class="min-h-24 flex-1 rounded-lg border border-amber-300 bg-white p-3 font-mono text-xs text-gray-950 shadow-sm dark:border-amber-500/40 dark:bg-gray-950 dark:text-gray-100"
                readonly
            >{{ $plainTextToken }}</textarea>
            <button
                type="button"
                class="inline-flex items-center justify-center rounded-lg border border-amber-300 bg-white px-3 py-2 text-sm font-medium text-amber-950 shadow-sm transition hover:bg-amber-100 dark:border-amber-500/40 dark:bg-gray-950 dark:text-amber-100 dark:hover:bg-amber-500/20"
                onclick="navigator.clipboard.writeText(document.getElementById('{{ $textareaId }}').value)"
            >
                Copy
            </button>
        </div>
    </div>
</div>
