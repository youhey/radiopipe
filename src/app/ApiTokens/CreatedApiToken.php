<?php

namespace App\ApiTokens;

use Laravel\Sanctum\PersonalAccessToken;

/**
 * Sanctum API token の発行結果。
 */
class CreatedApiToken
{
    /** @var PersonalAccessToken 発行された token metadata */
    public PersonalAccessToken $accessToken;

    /** @var string 発行直後に一度だけ表示する plain text token */
    public string $plainTextToken;

    /**
     * Constructor.
     */
    public function __construct(PersonalAccessToken $accessToken, string $plainTextToken)
    {
        $this->accessToken = $accessToken;
        $this->plainTextToken = $plainTextToken;
    }
}
