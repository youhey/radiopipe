# Pipeline

radiopipe の scheduled pipeline は topic nomination と episode compilation を分離します。

## Data Model

`CandidateTopic` は Episode 生成前に再利用する中間 topic candidate です。
`TopicDraft`、`TopicScreeningEvaluation`、`TopicEditorialEvaluation` の安全な snapshot と `candidate_fingerprint` を保存します。

`EpisodeTopic` は特定の Episode 内で CandidateTopic がどう使われたかを残す per-Episode snapshot です。
CandidateTopic は再利用可能な入力、EpisodeTopic は生成履歴側の利用記録です。

## Commands

`radiopipe:topics:nominate`:

- upstream article を取得します。
- `TopicBuilder`、`TopicScreeningEvaluator`、必要な場合だけ `TopicEditorialAnalyzer` を実行します。
- `candidate_topics` を作成または更新します。
- `candidate_fingerprint` が変わらない candidate は更新を skip します。
- 成功後に topic nomination throttle lock を設定します。

`radiopipe:episodes:export`:

- 保存済み CandidateTopic から Scenario を生成します。
- JSON を stdout に出力します。
- Episode / EpisodeTopic は保存しません。

`radiopipe:episodes:compile`:

- 保存済み CandidateTopic から Scenario を生成します。
- `compile_fingerprint` が最新 Episode と同じ場合は skip します。
- fingerprint が変わった場合だけ新しい Episode と EpisodeTopic を保存します。
- 既存 Episode は上書きしません。

scheduled pipeline `radiopipe:pipeline:compile`:

- Laravel scheduler の named callback です。Artisan command ではありません。
- `radiopipe:topics:nominate` を実行します。
- nomination が成功した場合だけ `radiopipe:episodes:compile` を実行します。

`radiopipe:episodes:ensure` は使用しません。

## Throttle Lock

Topic nomination throttle lock は `radiopipe:topics:nominate` の成功後に設定されます。
lock が有効な間の nominate 実行は skip され、exit code は 0 です。

設定:

```env
RADIOPIPE_TOPIC_NOMINATION_THROTTLE_SECONDS=3600
```

`0` 以下にすると throttle lock は無効です。
`withoutOverlapping` は同時実行防止、throttle lock は短時間の再実行抑制です。

## Scheduler

```env
RADIOPIPE_TOPIC_NOMINATION_THROTTLE_SECONDS=3600
```

Laravel scheduler が named callback `radiopipe:pipeline:compile:*` を JST 09:00 / 13:00 / 17:00 に実行します。
Laravel Cloud など hosting platform 側で scheduler を起動する設定は別途必要です。
