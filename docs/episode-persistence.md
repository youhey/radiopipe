# Episode Persistence

Episode persistence stores generated scenario output and the topic processing snapshots that led to it.

## Tables

`episodes` stores one generated output package for a run/date/time slot.
It contains the generated `Scenario` JSON, character profile reference and key snapshot, duration fields, run metadata, and safe error summaries.

`episode_topics` stores per-topic snapshots for the same run.
It keeps the internal topic id, upstream reference metadata, screening/editorial/selection statuses, and JSON snapshots of topic draft, screening, editorial, and scenario selection results.

## Recorder

`App\Episodes\EpisodeRecorder` persists an already generated `ScenarioGenerationResult` plus pipeline item snapshots.
It does not fetch upstream data, run screening/editorial analysis, select topics, or generate scenarios.

Episode keys are generated in this format when not provided:

```txt
episode_YYYY-MM-DD_HHmm_character-key
```

## Safety

Episode persistence must not store raw article bodies, full prompts, raw model responses, API keys, authorization headers, OAuth tokens, or other secrets.
The recorder removes common sensitive keys from stored snapshots as a defensive measure, but callers should still pass only safe summarized data.

This persistence layer does not implement generation scheduling, Filament UI, public APIs, analytics UI, retention policy, publishing, weather/headline integration, or TTS/audio behavior.
