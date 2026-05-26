<?php

namespace App\News;

/**
 * 一般ニュースを内部形式へ正規化する Provider
 */
interface NewsProvider
{
    /**
     * 指定条件に合うニュースを取得して返す
     *
     * @return list<NewsItem>
     *
     * @throws NewsProviderException
     */
    public function fetch(NewsQuery $query): array;
}
