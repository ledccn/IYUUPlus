<?php

namespace IYUU\Reseed\Events;

/**
 * 事件：支持站点列表获取成功之后
 */
class SupportSitesSuccessEvent
{
    /**
     * @var array
     */
    private $sites;

    /**
     * @param array $sites
     */
    public function __construct(array $sites)
    {
        $this->sites = $sites;
    }

    /**
     * @return array
     */
    public function getSites(): array
    {
        return $this->sites;
    }
}
