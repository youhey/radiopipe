@php
    $textareaId = 'created-api-token-' . str()->random(8);
@endphp

@once
    <style>
        .api-token-result {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            min-width: 0;
        }

        .api-token-result-notice {
            padding: 0.875rem 1rem;
            border: 1px solid rgb(245, 158, 11);
            border-radius: 0.75rem;
            background: rgb(255, 251, 235);
            color: rgb(120, 53, 15);
            font-size: 0.875rem;
            line-height: 1.35rem;
        }

        .dark .api-token-result-notice {
            border-color: rgba(245, 158, 11, 0.45);
            background: rgba(245, 158, 11, 0.12);
            color: rgb(253, 230, 138);
        }

        .api-token-result-grid {
            display: grid;
            grid-template-columns: repeat(1, minmax(0, 1fr));
            gap: 0.75rem;
        }

        @media (min-width: 768px) {
            .api-token-result-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        .api-token-result-item {
            min-width: 0;
            padding: 0.75rem 0.875rem;
            border: 1px solid rgb(229, 231, 235);
            border-radius: 0.75rem;
            background: rgb(249, 250, 251);
        }

        .dark .api-token-result-item {
            border-color: rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.05);
        }

        .api-token-result-label {
            color: rgb(107, 114, 128);
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.025em;
            line-height: 1rem;
            text-transform: uppercase;
        }

        .api-token-result-value {
            margin-top: 0.25rem;
            overflow: hidden;
            overflow-wrap: anywhere;
            color: rgb(17, 24, 39);
            font-size: 0.875rem;
            font-weight: 500;
            line-height: 1.25rem;
        }

        .dark .api-token-result-value {
            color: rgb(255, 255, 255);
        }

        .api-token-result-token-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 0.75rem;
        }

        @media (min-width: 640px) {
            .api-token-result-token-row {
                grid-template-columns: minmax(0, 1fr) auto;
                align-items: stretch;
            }
        }

        .api-token-result-token {
            width: 100%;
            min-height: 4.5rem;
            resize: vertical;
            padding: 0.875rem;
            border: 1px solid rgb(209, 213, 219);
            border-radius: 0.75rem;
            background: rgb(17, 24, 39);
            color: rgb(243, 244, 246);
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-size: 0.8125rem;
            line-height: 1.35rem;
            overflow-wrap: anywhere;
            white-space: pre-wrap;
        }

        .api-token-result-copy {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 2.75rem;
            padding: 0.625rem 1rem;
            border: 1px solid rgb(217, 119, 6);
            border-radius: 0.75rem;
            background: rgb(245, 158, 11);
            color: rgb(255, 255, 255);
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 600;
            line-height: 1.25rem;
            transition: background 0.15s ease, border-color 0.15s ease;
        }

        .api-token-result-copy:hover {
            border-color: rgb(180, 83, 9);
            background: rgb(217, 119, 6);
        }
    </style>
@endonce

<div class="api-token-result">
    <div class="api-token-result-notice">
        Copy this token now. It will not be shown again.
    </div>

    @if ($userEmail || $tokenName)
        <div class="api-token-result-grid">
            @if ($userEmail)
                <div class="api-token-result-item">
                    <div class="api-token-result-label">User</div>
                    <div class="api-token-result-value">{{ $userEmail }}</div>
                </div>
            @endif

            @if ($tokenName)
                <div class="api-token-result-item">
                    <div class="api-token-result-label">Token name</div>
                    <div class="api-token-result-value">{{ $tokenName }}</div>
                </div>
            @endif
        </div>
    @endif

    <div class="api-token-result-token-row">
        <textarea
            id="{{ $textareaId }}"
            class="api-token-result-token"
            readonly
        >{{ $plainTextToken }}</textarea>
        <button
            type="button"
            class="api-token-result-copy"
            onclick="navigator.clipboard.writeText(document.getElementById('{{ $textareaId }}').value)"
        >
            Copy
        </button>
    </div>
</div>
