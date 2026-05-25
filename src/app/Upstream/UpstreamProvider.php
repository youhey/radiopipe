<?php

namespace App\Upstream;

/**
 * upstream digest provider から完了済み記事項目を取得します。
 */
interface UpstreamProvider
{
    /**
     * 指定条件に合う upstream 記事項目を取得します。
     *
     * @return list<UpstreamArticleItem>
     *
     * @throws UpstreamProviderException
     */
    public function fetch(UpstreamArticleQuery $query): array;
}
