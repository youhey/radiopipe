<?php

namespace App\Topics\Ratings;

use RuntimeException;

/**
 * digestpipe rating forwarding の upstream 失敗を表す例外。
 */
class TopicRatingUpstreamException extends RuntimeException
{
    private int $httpStatus;

    /**
     * Constructor.
     */
    public function __construct(string $message, int $httpStatus)
    {
        parent::__construct($message);

        $this->httpStatus = $httpStatus;
    }

    /**
     * upstream API が利用できない状態を返す。
     */
    public static function unavailable(): self
    {
        return new self('Upstream rating API is unavailable.', 503);
    }

    /**
     * upstream API の失敗を返す。
     */
    public static function failed(): self
    {
        return new self('Upstream rating API request failed.', 502);
    }

    /**
     * upstream API response の shape 不正を返す。
     */
    public static function invalidResponse(): self
    {
        return new self('Upstream rating API response was invalid.', 502);
    }

    /**
     * local API として返す HTTP status を返す。
     */
    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}
