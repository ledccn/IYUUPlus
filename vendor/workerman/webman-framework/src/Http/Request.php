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

use Webman\Route\Route;
use function current;
use function filter_var;
use function ip2long;
use function is_array;
use function strpos;
use const FILTER_FLAG_IPV4;
use const FILTER_FLAG_NO_PRIV_RANGE;
use const FILTER_FLAG_NO_RES_RANGE;
use const FILTER_VALIDATE_IP;

/**
 * Class Request
 * @package Webman\Http
 */
class Request extends \Workerman\Protocols\Http\Request
{
    /**
     * @var string
     */
    public $plugin = null;

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
     * Input
     * @param string $name
     * @param mixed $default
     * @return mixed|null
     */
    public function input(string $name, $default = null)
    {
        $post = $this->post();
        if (isset($post[$name])) {
            return $post[$name];
        }
        $get = $this->get();
        return $get[$name] ?? $default;
    }

    /**
     * Only
     * @param array $keys
     * @return array
     */
    public function only(array $keys): array
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
     * Except
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
     * File
     * @param string|null $name
     * @return null|UploadFile[]|UploadFile
     */
    public function file($name = null)
    {
        $files = parent::file($name);
        if (null === $files) {
            return $name === null ? [] : null;
        }
        if ($name !== null) {
            // Multi files
            if (is_array(current($files))) {
                return $this->parseFiles($files);
            }
            return $this->parseFile($files);
        }
        $uploadFiles = [];
        foreach ($files as $name => $file) {
            // Multi files
            if (is_array(current($file))) {
                $uploadFiles[$name] = $this->parseFiles($file);
            } else {
                $uploadFiles[$name] = $this->parseFile($file);
            }
        }
        return $uploadFiles;
    }

    /**
     * ParseFile
     * @param array $file
     * @return UploadFile
     */
    protected function parseFile(array $file): UploadFile
    {
        return new UploadFile($file['tmp_name'], $file['name'], $file['type'], $file['error']);
    }

    /**
     * ParseFiles
     * @param array $files
     * @return array
     */
    protected function parseFiles(array $files): array
    {
        $uploadFiles = [];
        foreach ($files as $key => $file) {
            if (is_array(current($file))) {
                $uploadFiles[$key] = $this->parseFiles($file);
            } else {
                $uploadFiles[$key] = $this->parseFile($file);
            }
        }
        return $uploadFiles;
    }

    /**
     * GetRemoteIp
     * @return string
     */
    public function getRemoteIp(): string
    {
        return $this->connection->getRemoteIp();
    }

    /**
     * GetRemotePort
     * @return int
     */
    public function getRemotePort(): int
    {
        return $this->connection->getRemotePort();
    }

    /**
     * GetLocalIp
     * @return string
     */
    public function getLocalIp(): string
    {
        return $this->connection->getLocalIp();
    }

    /**
     * GetLocalPort
     * @return int
     */
    public function getLocalPort(): int
    {
        return $this->connection->getLocalPort();
    }

    /**
     * GetRealIp
     * @param bool $safeMode
     * @return string
     */
    public function getRealIp(bool $safeMode = true): string
    {
        $remoteIp = $this->getRemoteIp();
        if ($safeMode && !static::isIntranetIp($remoteIp)) {
            return $remoteIp;
        }
        $ip = $this->header('x-real-ip', $this->header('x-forwarded-for',
            $this->header('client-ip', $this->header('x-client-ip',
                $this->header('via', $remoteIp)))));
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : $remoteIp;
    }

    /**
     * Url
     * @return string
     */
    public function url(): string
    {
        return '//' . $this->host() . $this->path();
    }

    /**
     * FullUrl
     * @return string
     */
    public function fullUrl(): string
    {
        return '//' . $this->host() . $this->uri();
    }

    /**
     * IsAjax
     * @return bool
     */
    public function isAjax(): bool
    {
        return $this->header('X-Requested-With') === 'XMLHttpRequest';
    }

    /**
     * IsPjax
     * @return bool
     */
    public function isPjax(): bool
    {
        return (bool)$this->header('X-PJAX');
    }

    /**
     * ExpectsJson
     * @return bool
     */
    public function expectsJson(): bool
    {
        return ($this->isAjax() && !$this->isPjax()) || $this->acceptJson();
    }

    /**
     * AcceptJson
     * @return bool
     */
    public function acceptJson(): bool
    {
        return false !== strpos($this->header('accept', ''), 'json');
    }

    /**
     * IsIntranetIp
     * @param string $ip
     * @return bool
     */
    public static function isIntranetIp(string $ip): bool
    {
        // Not validate ip .
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        // Is intranet ip ? For IPv4, the result of false may not be accurate, so we need to check it manually later .
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return true;
        }
        // Manual check only for IPv4 .
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }
        // Manual check .
        $reservedIps = [
            1681915904 => 1686110207, // 100.64.0.0 -  100.127.255.255
            3221225472 => 3221225727, // 192.0.0.0 - 192.0.0.255
            3221225984 => 3221226239, // 192.0.2.0 - 192.0.2.255
            3227017984 => 3227018239, // 192.88.99.0 - 192.88.99.255
            3323068416 => 3323199487, // 198.18.0.0 - 198.19.255.255
            3325256704 => 3325256959, // 198.51.100.0 - 198.51.100.255
            3405803776 => 3405804031, // 203.0.113.0 - 203.0.113.255
            3758096384 => 4026531839, // 224.0.0.0 - 239.255.255.255
        ];
        $ipLong = ip2long($ip);
        foreach ($reservedIps as $ipStart => $ipEnd) {
            if (($ipLong >= $ipStart) && ($ipLong <= $ipEnd)) {
                return true;
            }
        }
        return false;
    }

}
