<?php

namespace App\News;

/**
 * 一般ニュース項目を取得して内部形式へ正規化する provider です。
 */
interface NewsProvider
{
    /**
     * 指定条件に合うニュース項目を取得します。
     *
     * @return list<NewsItem>
     *
     * @throws NewsProviderException
     */
    public function fetch(NewsQuery $query): array;
}
