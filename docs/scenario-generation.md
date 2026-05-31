# Scenario Generation

この文書は scenario generation foundation の現在の実装範囲を説明します。

## Purpose

Scenario generation は、editorial evaluation 済み topic をラジオ風台本の構造へ変換する層です。
現時点では downstream shape を確認するための local fake 実装だけを提供します。

## Current Components

| Class | Role |
|---|---|
| `App\Scenarios\Scenario` | 生成済みラジオ風台本の値オブジェクト。 |
| `App\Scenarios\ScenarioSection` | opening / topic / closing などの section 値オブジェクト。 |
| `App\Scenarios\ScenarioGenerationInput` | scenario generation 入力。 |
| `App\Scenarios\ScenarioGenerationResult` | scenario と topic selection を含む生成結果。 |
| `App\Scenarios\ScenarioTopicSelector` | `pending` editorial topic を score 順に選ぶ最小 selector。 |
| `App\Scenarios\FakeScenarioGenerator` | 外部 API を呼ばない deterministic fake generator。 |
| `App\Scenarios\OpenAiScenarioGenerator` | OpenAI Responses API を使う本番向け scenario generator。 |

## Topic Selection

`ScenarioTopicSelector` は `TopicEditorialEvaluation` のうち `status = pending` のものだけを対象にします。
`editorial_score` の降順で並べ、`radiopipe.scenario.max_topics` 件までを `used_in_scenario` にします。
残りの pending topic は `selected_not_used` になります。

`skipped_*` は Topic Screening または Topic Editorial Evaluation の責務なので、Scenario Topic Selection では扱いません。

## Fake Generator

`FakeScenarioGenerator` は local development と tests 用です。
OpenAI、外部 API、network access は使いません。

生成内容:

- opening section
- 選択 topic ごとの topic section
- closing section
- section text を空行で連結した `script_text`
- 簡易的な推定読み上げ秒数

Fake text は最終的な character style ではありません。

`Scenario.title` は固定の番組名ではなく、その回の選択 topic から生成される episode 固有のタイトルです。
一覧、API、admin 画面、downstream playback app で内容を識別しやすいよう、主要 topic を短く示す名前にします。
有用な topic 情報がない場合だけ、汎用的な fallback title を使います。

## OpenAI Generator

`OpenAiScenarioGenerator` は `CharacterProfile` を執筆指示に変換し、選択済み topic の compact data だけを OpenAI Responses API に渡します。
raw upstream article body、provider raw response、API key、secret は送信・保存・ログ出力しません。

OpenAI generator は `pending` editorial topic から selector が `used_in_scenario` にした topic だけを spoken scenario に使います。
`Scenario.title` も選択済み topic と scenario 内容に基づく episode 固有の日本語タイトルとして生成します。
固定の `今日のギークニュース` のような generic title は、topic 情報が不足している場合の fallback としてだけ使います。
出力は `Scenario` / `ScenarioSection` の構造へ変換され、必須 field、duration、section type、参照 topic id を検証します。
invalid output の場合は fake に fallback せず、scenario generator failure として扱います。

既定 driver は安全のため `fake` です。
OpenAI を使う場合は `RADIOPIPE_SCENARIO_GENERATOR=openai`、`RADIOPIPE_SCENARIO_MODEL`、`OPENAI_API_KEY` を設定します。

Episode generation、weather/headline integration、persistence、public API、TTS/audio generation は未実装です。
