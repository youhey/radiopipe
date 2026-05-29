<style>
    .rp-analysis {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .rp-analysis__stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr));
        gap: 1rem;
    }

    .rp-analysis__stat,
    .rp-analysis__panel {
        overflow: hidden;
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        background: #ffffff;
        box-shadow: 0 1px 2px rgb(15 23 42 / 0.06);
    }

    .rp-analysis__stat {
        padding: 1rem;
    }

    .rp-analysis__stat-label {
        overflow: hidden;
        color: #6b7280;
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        line-height: 1rem;
        text-overflow: ellipsis;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .rp-analysis__stat-value {
        margin-top: 0.5rem;
        overflow: hidden;
        color: #111827;
        font-size: 1.5rem;
        font-weight: 700;
        line-height: 2rem;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .rp-analysis__grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(17rem, 1fr));
        gap: 1.5rem;
    }

    .rp-analysis__panel-header {
        border-bottom: 1px solid #e5e7eb;
        padding: 1rem;
    }

    .rp-analysis__panel-title {
        margin: 0;
        color: #111827;
        font-size: 1rem;
        font-weight: 700;
        line-height: 1.5rem;
    }

    .rp-analysis__list {
        display: flex;
        flex-direction: column;
    }

    .rp-analysis__list-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        border-top: 1px solid #f3f4f6;
        padding: 0.875rem 1rem;
    }

    .rp-analysis__list-row:first-child {
        border-top: 0;
    }

    .rp-analysis__list-label {
        min-width: 0;
        overflow: hidden;
        color: #374151;
        font-size: 0.875rem;
        line-height: 1.25rem;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .rp-analysis__list-value {
        flex: 0 0 auto;
        color: #111827;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 0.875rem;
        font-weight: 700;
        line-height: 1.25rem;
    }

    .rp-analysis__empty {
        padding: 1rem;
        color: #6b7280;
        font-size: 0.875rem;
    }

    .rp-analysis__table-wrap {
        overflow-x: auto;
    }

    .rp-analysis__table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
        text-align: left;
    }

    .rp-analysis__table thead {
        background: #f9fafb;
        color: #6b7280;
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .rp-analysis__table th,
    .rp-analysis__table td {
        border-top: 1px solid #f3f4f6;
        padding: 0.75rem 1rem;
        vertical-align: top;
    }

    .rp-analysis__table thead th {
        border-top: 0;
    }

    .rp-analysis__mono {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 0.75rem;
        line-height: 1.25rem;
        white-space: nowrap;
    }

    @media (prefers-color-scheme: dark) {
        .rp-analysis__stat,
        .rp-analysis__panel {
            border-color: rgb(255 255 255 / 0.1);
            background: #111827;
        }

        .rp-analysis__stat-label,
        .rp-analysis__empty,
        .rp-analysis__table thead {
            color: #9ca3af;
        }

        .rp-analysis__stat-value,
        .rp-analysis__panel-title,
        .rp-analysis__list-value {
            color: #f9fafb;
        }

        .rp-analysis__list-label {
            color: #e5e7eb;
        }

        .rp-analysis__panel-header,
        .rp-analysis__list-row,
        .rp-analysis__table th,
        .rp-analysis__table td {
            border-color: rgb(255 255 255 / 0.1);
        }

        .rp-analysis__table thead {
            background: rgb(255 255 255 / 0.05);
        }
    }
</style>
