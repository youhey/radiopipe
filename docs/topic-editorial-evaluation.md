# Topic Editorial Evaluation

This document defines the planned Phase 6 `Topic Editorial Evaluation` terminology and boundaries.
The planned implementation will prepare screened topics for later scenario selection.
It is not implemented yet.

## Purpose

Phase 6 performs higher-precision topic preparation after `TopicScreening`.
It may use AI in a later implementation, but this phase should remain a structured evaluation step with explicit output.

The planned implementation will cover:

- Japanese localization.
- Summary generation.
- Score recalculation.
- Scenario fitness evaluation.
- Topic ordering and flow hints.
- User preference fit.
- General importance.
- Certainty and uncertainty.
- Sensitive-topic reassessment.
- Semantic duplicate candidate detection.

This phase prepares topics for later scenario selection.
It does not decide final scenario inclusion.

## Planned Components

| Class | Role |
|---|---|
| `App\Topics\Editorial\TopicLocalizer` | Localizes topic text into Japanese. |
| `App\Topics\Editorial\TopicSummarizer` | Produces concise Japanese summary text. |
| `App\Topics\Editorial\TopicEditorialEvaluator` | Evaluates editorial usefulness and risks. |
| `App\Topics\Editorial\TopicEditorialAnalyzer` | May combine localization, summarization, and evaluation into one AI call for cost efficiency. |
| `App\Topics\Editorial\TopicEditorialEvaluation` | Structured output of this phase. |
| `App\Topics\Editorial\TopicEditorialStatus` | Status for this phase only. |

These names are planned future implementation names, not current classes.

## Input

The primary input is a screened topic, conceptually:

```txt
TopicDraft
TopicScreeningEvaluation
User preferences / editorial preferences, when available
Other candidate topics, when duplicate detection is needed
```

Exact PHP input structures may evolve.
By default, this phase should operate only on topics that passed Stage 1 screening unless explicitly configured otherwise.

## Output

The output is intended to be `TopicEditorialEvaluation`.

Conceptual shape:

```json
{
  "status": "pending",
  "editorial_score": 86,
  "localized": {
    "title": "AIチップの部品コストでHBMの比率が上昇",
    "summary": "Epoch AIの分析によると、AIチップの部品コストに占めるHBMの割合が大きく伸びている。",
    "why_it_matters": "AIアクセラレータの供給網、価格、クラウド事業者の設備投資に影響する可能性がある。"
  },
  "scores": {
    "preference": 90,
    "general_importance": 85,
    "freshness": 80,
    "certainty": 88,
    "scenario_fitness": 82,
    "flow_flexibility": 70
  },
  "flags": {
    "is_duplicate_candidate": false,
    "is_uncertain": false,
    "is_sensitive": false
  },
  "duplicate": {
    "canonical_key": "ai-chip-hbm-component-costs",
    "similar_topic_ids": [],
    "duplicate_of": null,
    "confidence": null,
    "reason": null
  },
  "scenario_notes": {
    "suggested_role": "top_story",
    "tone": "serious_but_accessible",
    "transition_hint": "AIインフラのコスト構造という流れで紹介できる",
    "avoid": []
  },
  "reasons": [],
  "metadata": {}
}
```

The exact PHP value objects may be implemented later.
This shape establishes the intended phase boundary and vocabulary.

## Status Values

`TopicEditorialStatus` values:

| Status | Meaning |
|---|---|
| `pending` | Passed editorial evaluation and awaits later scenario selection. |
| `skipped_low_value` | Removed because it lacks enough editorial value after high-precision evaluation. |
| `skipped_duplicate` | Removed because it is a high-confidence duplicate of another topic. |
| `skipped_uncertain` | Removed because the content is too uncertain for scenario use. |
| `skipped_sensitive` | Removed because the topic is too sensitive for the current scenario policy. |

`pending` does not mean `used_in_scenario`.

Phase 7 will decide:

```txt
used_in_scenario
selected_not_used
```

## Scores

Scores are `0..100` integers.

| Score | Meaning |
|---|---|
| `preference` | Fit with the user's interests and configured editorial preferences. |
| `general_importance` | Broad objective importance independent of personal preference. |
| `freshness` | How timely the topic is for the target episode. |
| `certainty` | How safe the topic is to present as a factual news item. |
| `scenario_fitness` | How well the topic works in a radio-style news script. |
| `flow_flexibility` | How easy it is to place the topic in different positions in the episode flow. |

`editorial_score` is an aggregate score derived from these values and other penalties.
The final aggregate formula is intentionally not defined here.
It should be defined in implementation documentation when the evaluator is added.

## Semantic Duplicate Candidates

Phase 6 should detect semantic duplicate candidates.

It may use:

- Normalized title similarity.
- Localized title similarity.
- Summary or brief similarity.
- Topics overlap.
- Tags overlap.
- Entities overlap.
- Source URL or canonical URL.
- Discussion URL.
- AI duplicate assessment in a later implementation.

The output should distinguish between high-confidence duplicates and weak duplicate candidates.
High-confidence duplicates may become `skipped_duplicate`.
Weak duplicate candidates should usually remain `pending` and be left for Phase 7.

Phase 6 should avoid aggressively skipping weak duplicate candidates.

## Scenario Notes

`scenario_notes` are hints for later scenario selection and writing.

Suggested fields:

```txt
suggested_role
tone
transition_hint
avoid
```

Example `suggested_role` values:

```txt
top_story
main_story
quick_mention
background_context
human_interest
technical_deep_dive
```

These are hints only.
They are not final scenario placement decisions.

## Relationship to Topic Screening

| Stage | Name | Type | Main Purpose |
|---|---|---|---|
| Stage 1 | Topic Screening | deterministic / cheap | Reject obvious duplicates, low-quality, uncertain, or sensitive topics. |
| Phase 6 | Topic Editorial Evaluation | higher precision / AI-assisted later | Localize, summarize, rescore, detect semantic duplicate candidates, and prepare for scenario selection. |
| Phase 7 | Scenario Topic Selection | episode-level selection | Decide what actually appears in the scenario. |

## Boundaries

Out of scope for Phase 6:

- Final scenario inclusion.
- Episode-level topic balancing.
- Final topic ordering.
- Final adoption or rejection reason generation.
- Full scenario script generation.
- TTS/audio generation.
- Database persistence, unless added in a later task.
- Public API output, unless added in a later task.

Later phases:

```txt
Phase 7: Scenario Topic Selection
  Decide which editorially pending topics are used in the scenario.

Phase 8: Topic Decision Reason Generation
  Generate user-facing reasons for used, selected-not-used, or skipped topics.
```
