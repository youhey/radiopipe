<?php

namespace App\Upstream;

/**
 * Upstream Items 取得のインタフェース
 */
interface UpstreamProvider
{
    /**
     * 指定条件に合う Upstream Items を取得して返す
     *
     * @return list<UpstreamArticleItem>
     *
     * @throws UpstreamProviderException
     */
    public function fetch(UpstreamArticleQuery $query): array;
}
