# Topic Screening

This document describes Stage 1 deterministic screening rules for `TopicDraft`.
The target implementation is `App\Topics\Screening\TopicScreeningEvaluator`.
This layer does not include AI evaluation, translation, summarization, editorial evaluation, final scenario inclusion, or TTS/audio processing.

## 入力

入力は `TopicDraft` です。

主に screening に使う値:

| 値 | 用途 |
|---|---|
| `url` | 既知 URL 一覧との完全一致による重複判定 |
| `published_at` | freshness score の算出 |
| `importance` | digestpipe の trusted importance を 0-100 に変換 |
| `confidence` | digestpipe の trusted confidence を 0-100 に変換 |
| `content_type` | content type score の算出 |
| `limitations` | source quality penalty と uncertainty 判定 |
| `tags` | sensitive keyword 判定 |
| `title` / `summary_seed` / `why_it_matters_seed` | sensitive keyword 判定 |
| `upstream_selection.status` | 小さい selection bonus/penalty |
| `upstream_selection.score` | 小さい bounded bonus/penalty |

## 出力

出力は `TopicScreeningEvaluation` です。

| フィールド | 意味 |
|---|---|
| `screening_status` | Stage 1 の内部 screening status |
| `screening_score` | 0-100 に clamp された screening score |
| `signals` | screening に使った数値・真偽値 signal |
| `flags` | `is_duplicate` / `is_uncertain` / `is_sensitive` |
| `reasons` | debug 用の短い理由文字列 |

## Signals

現在の `signals` は次の値です。

| signal | 型 | 説明 |
|---|---|---|
| `is_duplicate_url` | boolean | `url` が evaluator に渡された既知 URL 一覧と一致するか |
| `freshness_score` | integer | `published_at` から算出する鮮度スコア |
| `upstream_importance_score` | integer | digestpipe `importance` を 0-100 に変換した値 |
| `upstream_confidence_score` | integer | digestpipe `confidence` を 0-100 に変換した値 |
| `content_type_score` | integer | `content_type` の有用度スコア |
| `limitation_penalty` | integer | `limitations` の弱い情報源キーワードによる減点 |
| `selection_bonus` | integer | upstream selection による -5 から +5 の弱い補正 |

## 数値化ルール

### Freshness

`published_at` と評価時刻の差から算出します。

| 条件 | `freshness_score` |
|---|---:|
| 6 時間以内 | 100 |
| 24 時間以内 | 85 |
| 3 日以内 | 60 |
| 7 日以内 | 30 |
| それより古い | 10 |
| `published_at` なし | 10 |

### Upstream Importance

digestpipe の `importance` は trusted structured field として扱い、次の map を直接使います。

| `importance` | `upstream_importance_score` |
|---:|---:|
| 5 | 100 |
| 4 | 80 |
| 3 | 60 |
| 2 | 30 |
| 1 | 10 |
| missing / invalid | 40 |

### Upstream Confidence

digestpipe の `confidence` は `0.0` から `1.0` の trusted structured field として扱います。

```txt
upstream_confidence_score = round(confidence * 100)
```

例:

| `confidence` | `upstream_confidence_score` |
|---:|---:|
| 0.96 | 96 |
| 0.80 | 80 |
| 0.45 | 45 |
| missing / invalid | 40 |

### Content Type

`content_type` は現在、upstream から渡される provisional metadata として扱います。
digestpipe はまだ stable な content type taxonomy を定義・強制していないため、radiopipe は既定では upstream の free-form `content_type` を正規化しません。

radiopipe は `radiopipe.topic_screening.content_type_scores` に明示設定された値だけを個別スコアとして扱い、それ以外の upstream content type はすべて `unknown` と同じ扱いにします。

現在の既定値:

| `content_type` | `content_type_score` |
|---|---:|
| `unknown` | 50 |

未定義の `content_type` は `unknown` と同じ扱いです。
例えば `news/article`、`news_report`、`blog post`、`how-to`、`opinion/essay` のような値も、明示設定がない限り `50` になります。

将来的には、digestpipe 側で stable な content type taxonomy が定義・強制された後に、この scoring map をその taxonomy と揃えます。

### Limitations

`limitations` に弱い情報源を示すキーワードが含まれる場合、`limitation_penalty` は `30` になります。
該当しない場合は `0` です。

現在の対象キーワード:

```txt
headline only
title only
only a headline
no body
missing body
incomplete
truncated
not independently verified
unverified
speculative
subjective
promotional
landing page
extraction failed
insufficient context
```

`limitation_penalty >= 30` は uncertainty 判定にも使います。

### Upstream Selection

upstream selection は digestpipe 側の deterministic keyword gate です。
radiopipe では弱い eligibility/debug signal としてのみ扱います。

`selection_bonus` は必ず `-5..+5` に clamp されます。

現在の挙動:

| 入力 | 補正 |
|---|---:|
| `upstream_selection.status = selected` | +5 |
| `upstream_selection.status = skipped` | -5 |
| `upstream_selection.status = pending` / `needs_content` / missing | 0 |
| `upstream_selection.score > 0` | 追加で最大 +2 |
| `upstream_selection.score <= 0` | 追加で -2 |
| `upstream_selection.score = null` | 追加補正なし |

status と score の合計後に `-5..+5` へ clamp します。
そのため `selection_bonus` は `screening_score` を支配しません。

## Sensitive Flag

次の値を結合した文字列に sensitive keyword が含まれる場合、`is_sensitive = true` になります。

- `title`
- `summary_seed`
- `why_it_matters_seed`
- `content_type`
- `limitations`
- `tags`

現在の対象キーワード:

```txt
disaster
accident
crime
war
military
terrorism
politics
election
medical
health
finance
investment
self-harm
sexual
abuse
violence
hate
discrimination
personal data
credential leak
security breach
exploit
```

この判定は低コストな deterministic flag であり、policy-heavy な moderation ではありません。

## Screening Score

`screening_score` は次の式で計算します。

```txt
screening_score =
  freshness_score * 0.25
+ upstream_importance_score * 0.35
+ upstream_confidence_score * 0.25
+ content_type_score * 0.15
- limitation_penalty
+ selection_bonus
```

最後に整数へ丸め、`0..100` に clamp します。

weights は `config/radiopipe.php` の `radiopipe.topic_screening.weights` で管理します。

## Flags

| flag | 条件 |
|---|---|
| `is_duplicate` | `url` が既知 URL 一覧に完全一致 |
| `is_uncertain` | `upstream_confidence_score < 45` または `limitation_penalty >= 30` |
| `is_sensitive` | sensitive keyword に一致 |

## Screening Status

`screening_status` は次の優先順で決定します。

| 優先順 | 条件 | `screening_status` |
|---:|---|---|
| 1 | `is_duplicate = true` | `rejected_duplicate` |
| 2 | `is_sensitive = true` | `rejected_sensitive` |
| 3 | `is_uncertain = true` | `rejected_uncertain` |
| 4 | `screening_score < 45` | `rejected_low_value` |
| 5 | 上記に該当しない | `passed` |

`45` は `radiopipe.topic_screening.thresholds.low_value_score` の既定値です。

## 注意点

- Topic Screening は最終的な `Topic` status ではありません。
- `passed` はシナリオ採用を意味しません。
- 重複判定は evaluator に渡された既知 URL 一覧との完全一致のみです。
- semantic duplicate、embedding、AI 評価、翻訳、要約、ランキング、editorial evaluation、scenario inclusion はこの層では行いません。
- upstream selection は弱い eligibility/debug signal としてのみ扱います。
- `selection_bonus` は小さい bounded adjustment であり、`screening_score` を支配してはいけません。
