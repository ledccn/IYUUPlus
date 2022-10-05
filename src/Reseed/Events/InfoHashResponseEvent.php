<?php

namespace IYUU\Reseed\Events;

/**
 * 事件：请求服务器辅种接口，返回可辅种的infoHash
 */
class InfoHashResponseEvent
{
    /**
     * 接口返回的可辅种数据
     * @var array
     */
    private $info_hash_response;

    /**
     * 用户勾选的站点
     * @var array
     */
    private $user_sites;

    /**
     * 接口返回的所有站点
     * @var array
     */
    private $sites;

    /**
     * @param array $info_hash_response 接口返回的可辅种数据
     * @param array $user_sites 用户勾选的站点
     * @param array $sites 接口返回的所有站点
     */
    public function __construct(array $info_hash_response, array $user_sites, array $sites)
    {
        $this->info_hash_response = $info_hash_response;
        $this->user_sites = $user_sites;
        $this->sites = $sites;
    }

    /**
     * @return array
     */
    public function getInfoHashResponse(): array
    {
        return $this->info_hash_response;
    }

    /**
     * @return array
     */
    public function getUserSites(): array
    {
        return $this->user_sites;
    }

    /**
     * @return array
     */
    public function getSites(): array
    {
        return $this->sites;
    }

    /**
     * @param array $info_hash_response
     */
    public function setInfoHashResponse(array $info_hash_response): void
    {
        $this->info_hash_response = $info_hash_response;
    }
}
