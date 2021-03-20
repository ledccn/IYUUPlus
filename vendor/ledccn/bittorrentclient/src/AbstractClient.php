<?php
/**
 * BitTorrent下载服务器抽象类
 * Created by PhpStorm
 * User: David <367013672@qq.com>
 * Date: 2020-1-11
 */
namespace IYUU\Client;

abstract class AbstractClient
{
    /**
     * 完整的下载服务器地址
     * @var string
     */
    protected $url = '';

    /**
     * 下载服务器用户名
     * @var string
     */
    protected $username = '';

    /**
     * 下载服务器密码
     * @var string
     */
    protected $password = '';
    /**
     * 下载服务器调试开关
     * @var bool
     */
    public $debug = false;

    /**
     * 创建客户端实例
     * @access public
     * @param array $config
     * array(
     *  'type'  => '',
     *  'host'  => '',
     *  'endpoint'  =>  '',
     *  'username'  =>  '',
     *  'password'  =>  '',
     * )
     * @return mixed    客户端实例
     * @throws \IYUU\Client\ClientException
     */
    public static function create($config = [])
    {
        // 下载服务器类型
        $type = isset($config['type']) ? $config['type'] : '';
        $file = __DIR__ . DIRECTORY_SEPARATOR . $type . DIRECTORY_SEPARATOR . $type .'.php';
        if (!is_file($file)) {
            throw new ClientException($file.' 文件不存在');
        }
        $className = "IYUU\\Client\\" . $type . "\\" . $type;
        if (class_exists($className)) {
            echo $type." 客户端正在实例化！".PHP_EOL;
            return new $className($config);
        } else {
            throw new ClientException($className.' 客户端class不存在');
        }
    }

    /**
     * 初始化下载服务器参数
     * @descr 子类调用
     * @param array $config
     */
    protected function initialize($config = [])
    {
        $host   = isset($config['host']) ? $config['host'] : '';            // 地址端口
        $endpoint = isset($config['endpoint']) ? $config['endpoint'] : '';  // 接入点
        $username = isset($config['username']) ? $config['username'] : '';  // 用户名
        $password = isset($config['password']) ? $config['password'] : '';  // 密码
        $debug    = isset($config['debug']) ? $this->booleanParse($config['debug']) : false;    // 调试开关

        $this->url = rtrim($host, '/') . $endpoint;
        $this->username = $username;
        $this->password = $password;
        $this->debug = $debug;
    }

    /**
     * 对布尔型进行格式化
     * @param mixed $value 变量值
     * @return boolean/string 格式化后的变量
     */
    protected function booleanParse($value)
    {
        $rs = $value;

        if (!is_bool($value)) {
            if (is_numeric($value)) {
                $rs = $value > 0 ? true : false;
            } elseif (is_string($value)) {
                $rs = in_array(strtolower($value), ['ok', 'true', 'success', 'on', 'yes', '(ok)', '(true)', '(success)', '(on)', '(yes)']) ? true : false;
            } else {
                $rs = $value ? true : false;
            }
        }

        return $rs;
    }

    /**
     * 判断传入的种子参数是否为url下载链接
     * @param string $torrent   种子的url或元数据
     * @return bool
     */
    protected function is_url($torrent):bool
    {
        return (strpos($torrent, 'http://')===0) || (strpos($torrent, 'https://')===0) || (strpos($torrent, 'magnet:?xt=urn:btih:')===0);
    }

    /**
     * 向下载服务器添加种子
     * @param string $torrent 种子的url或元数据
     * @param string $save_path 保存路径
     * @param array $extra_options 附加参数
     * @return array
     */
    public function add_torrent($torrent, $save_path = '', $extra_options = array())
    {
        if ($this->is_url($torrent)) {
            $result = $this->add($torrent, $save_path, $extra_options);			    // URL添加
        } else {
            $result = $this->add_metainfo($torrent, $save_path, $extra_options);	// 元数据添加
        }

        return $this->response($result);
    }

    /**
     * 解析结果
     * @param mixed $result
     * @return array
     */
    abstract protected function response($result);

    /**
     * 查询下载服务器状态
     * @return string
     */
    abstract public function status();

    /**
     * 从下载服务器获取所有种子的列表
     * @param array $torrents
     * @return array(
     * 'hash'       => string json,
     * 'sha1'       => string,
     * 'hashString '=> array
     * )
     */
    abstract public function all(&$torrents = array());

    /**
     * 向下载服务器添加种子连接
     * @param string $torrent_url
     * @param string $save_path
     * @param array $extra_options
     */
    abstract public function add($torrent_url, $save_path = '', $extra_options = array());

    /**
     * 向下载服务器添加种子源数据
     * @param string $torrent_metainfo
     * @param string $save_path
     * @param array $extra_options
     */
    abstract public function add_metainfo($torrent_metainfo, $save_path = '', $extra_options = array());

    /**
     * 删除下载服务器中的种子
     * @param mixed $torrent
     * @param bool $deleteFiles
     */
    abstract public function delete($torrent, $deleteFiles = false);
}
