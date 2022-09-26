<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Webman\Http;

use Webman\App;
use Webman\Route\Route;
use Webman\Http\UploadFile;

/**
 * Class Request
 * @package Webman\Http
 */
class Request extends \Workerman\Protocols\Http\Request
{
    /**
     * @var string
     */
    public $app = null;

    /**
     * @var string
     */
    public $controller = null;

    /**
     * @var string
     */
    public $action = null;

    /**
     * @var Route
     */
    public $route = null;

    /**
     * @return mixed|null
     */
    public function all()
    {
        return $this->post() + $this->get();
    }

    /**
     * @param string $name
     * @param string|null $default
     * @return mixed|null
     */
    public function input($name, $default = null)
    {
        $post = $this->post();
        if (isset($post[$name])) {
            return $post[$name];
        }
        $get = $this->get();
        return isset($get[$name]) ? $get[$name] : $default;
    }

    /**
     * @param array $keys
     * @return array
     */
    public function only(array $keys)
    {
        $all = $this->all();
        $result = [];
        foreach ($keys as $key) {
            if (isset($all[$key])) {
                $result[$key] = $all[$key];
            }
        }
        return $result;
    }

    /**
     * @param array $keys
     * @return mixed|null
     */
    public function except(array $keys)
    {
        $all = $this->all();
        foreach ($keys as $key) {
            unset($all[$key]);
        }
        return $all;
    }

    /**
     * @param string|null $name
     * @return null|array|UploadFile
     */
    public function file($name = null)
    {
        $files = parent::file($name);
        if (null === $files) {
            return $name === null ? [] : null;
        }
        if ($name !== null) {
            // Multi files
            if (\is_array(\current($files))) {
                return $this->parseFiles($files);
            }
            return $this->parseFile($files);
        }
        $upload_files = [];
        foreach ($files as $name => $file) {
            // Multi files
            if (\is_array(\current($file))) {
                $upload_files[$name] = $this->parseFiles($file);
            } else {
                $upload_files[$name] = $this->parseFile($file);
            }
        }
        return $upload_files;
    }

    /**
     * @param $file
     * @return UploadFile
     */
    protected function parseFile($file)
    {
        return new UploadFile($file['tmp_name'], $file['name'], $file['type'], $file['error']);
    }

    /**
     * @param array $files
     * @return array
     */
    protected function parseFiles($files)
    {
        $upload_files = [];
        foreach ($files as $key => $file) {
            if (\is_array(\current($file))) {
                $upload_files[$key] = $this->parseFiles($file);
            } else {
                $upload_files[$key] = $this->parseFile($file);
            }
        }
        return $upload_files;
    }

    /**
     * @return string
     */
    public function getRemoteIp()
    {
        return App::connection()->getRemoteIp();
    }

    /**
     * @return int
     */
    public function getRemotePort()
    {
        return App::connection()->getRemotePort();
    }

    /**
     * @return string
     */
    public function getLocalIp()
    {
        return App::connection()->getLocalIp();
    }

    /**
     * @return int
     */
    public function getLocalPort()
    {
        return App::connection()->getLocalPort();
    }

    /**
     * @param bool $safe_mode
     * @return string
     */
    public function getRealIp($safe_mode = true)
    {
        $remote_ip = $this->getRemoteIp();
        if ($safe_mode && !static::isIntranetIp($remote_ip)) {
            return $remote_ip;
        }
        return $this->header('client-ip', $this->header('x-forwarded-for',
                   $this->header('x-real-ip', $this->header('x-client-ip',
                   $this->header('via', $remote_ip)))));
    }

    /**
     * @return string
     */
    public function url()
    {
        return '//' . $this->host() . $this->path();
    }

    /**
     * @return string
     */
    public function fullUrl()
    {
        return '//' . $this->host() . $this->uri();
    }

    /**
     * @return bool
     */
    public function isAjax()
    {
        return $this->header('X-Requested-With') === 'XMLHttpRequest';
    }

    /**
     * @return bool
     */
    public function isPjax()
    {
        return (bool)$this->header('X-PJAX');
    }

    /**
     * @return bool
     */
    public function expectsJson()
    {
        return ($this->isAjax() && !$this->isPjax()) || $this->acceptJson();
    }

    /**
     * @return bool
     */
    public function acceptJson()
    {
        return false !== strpos($this->header('accept', ''), 'json');
    }

    /**
     * @param string $ip
     * @return bool
     */
    public static function isIntranetIp($ip)
    {
        $reserved_ips = [
            '167772160'  => 184549375,  /*    10.0.0.0 -  10.255.255.255 */
            '3232235520' => 3232301055, /* 192.168.0.0 - 192.168.255.255 */
            '2130706432' => 2147483647, /*   127.0.0.0 - 127.255.255.255 */
            '2886729728' => 2887778303, /*  172.16.0.0 -  172.31.255.255 */
        ];

        $ip_long = ip2long($ip);

        foreach ($reserved_ips as $ip_start => $ip_end) {
            if (($ip_long >= $ip_start) && ($ip_long <= $ip_end)) {
                return true;
            }
        }
        return false;
    }
}
