<?php

namespace App\Scenarios;

use App\Models\CharacterProfile;

/**
 * CharacterProfile から scenario generation 用の短い指示文を組み立てる。
 */
class CharacterProfileInstructionBuilder
{
    /**
     * CharacterProfile の台本生成に必要な情報だけを指示文へ変換する。
     */
    public function build(CharacterProfile $profile): string
    {
        $profileArray = $profile->toScenarioProfileArray();
        unset($profileArray['metadata'], $profileArray['is_active'], $profileArray['sort_order']);

        return implode("\n", [
            'Character profile:',
            $this->line('character_key', $profileArray['character_key'] ?? null),
            $this->line('name', $profileArray['name'] ?? null),
            $this->line('role', $profileArray['role'] ?? null),
            $this->line('personality', $profileArray['personality'] ?? null),
            $this->line('tone', $profileArray['tone'] ?? null),
            $this->jsonLine('speech_style', $profileArray['speech_style'] ?? []),
            $this->jsonLine('catchphrases', $profileArray['catchphrases'] ?? []),
            $this->jsonLine('style_examples', $profileArray['style_examples'] ?? []),
            $this->jsonLine('banned_phrases', $profileArray['banned_phrases'] ?? []),
            $this->jsonLine('disallowed_expressions', $profileArray['disallowed_expressions'] ?? []),
            $this->jsonLine('serious_topic_behavior', $profileArray['serious_topic_behavior'] ?? []),
            $this->jsonLine('content_policy', $profileArray['content_policy'] ?? []),
            $this->jsonLine('script_preferences', $profileArray['script_preferences'] ?? []),
        ]);
    }

    private function line(string $key, mixed $value): string
    {
        return $key . ': ' . (is_scalar($value) ? (string) $value : '');
    }

    private function jsonLine(string $key, mixed $value): string
    {
        return $key . ': ' . json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
