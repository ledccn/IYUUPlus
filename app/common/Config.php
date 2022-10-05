<?php

namespace app\common;

/**
 * 配置文件读写类
 */
class Config
{
    /**
     * 扩展名映射表
     */
    const extMap = [
        'array' => '.php',
        'json' => '.json',
        'object' => '.data',
    ];

    /**
     * 写入配置
     * @param string $filename 文件名
     * @param mixed $data 数据
     * @param string $type 数据类型
     * @param bool $absolutePath 绝对路径
     * @return bool|int
     */
    public static function set(string $filename, $data, string $type = 'array', bool $absolutePath = false)
    {
        if (empty($filename)) {
            return false;
        }

        clearstatcache();
        $file_name = $absolutePath ? $filename : static::createFilePath($filename, $type);

        $dir = dirname($file_name);
        is_dir($dir) or mkdir($dir, 0777, true);
        if (!is_writable($dir)) {
            return false;
        }

        if (!file_exists($file_name)) {
            touch($file_name);
            chmod($file_name, 0777);
        }

        switch (strtolower($type)) {
            case 'object':
                $str = serialize($data);
                break;
            case 'json':
                $str = json_encode($data, JSON_UNESCAPED_UNICODE);
                break;
            case 'array':
                $str = '<?php' . PHP_EOL . 'return ' . var_export($data, true) . ';' . PHP_EOL;
                break;
            default:
                $str = $data;
                break;
        }

        $writeLen = file_put_contents($file_name, $str);
        return $writeLen === 0 ? false : $writeLen;
    }

    /**
     * 创建文件路径
     * @param string $name
     * @param string $type
     * @return string
     */
    public static function createFilePath(string $name = '', string $type = 'array'): string
    {
        $ext = self::extMap[$type] ?? self::extMap['object'];
        return db_path() . DIRECTORY_SEPARATOR . $name . $ext;
    }

    /**
     * 读取配置
     * @param string $filename 文件名
     * @param string $type 数据类型
     * @param null $default 默认值
     * @param bool $absolutePath 绝对路径
     * @return false|string|null|array
     */
    public static function get(string $filename, string $type = 'array', $default = null, bool $absolutePath = false)
    {
        if (empty($filename)) {
            return $default;
        }

        $file_name = $absolutePath ? $filename : static::createFilePath($filename, $type);
        clearstatcache();
        if (is_file($file_name)) {
            switch (strtolower($type)) {
                case 'object':
                    $d = @unserialize(file_get_contents($file_name, false, null));
                    break;
                case 'json':
                    $d = json_decode(file_get_contents($file_name, false, null), true);
                    break;
                case 'array':
                    $d = include $file_name;
                    break;
                default:
                    $d = file_get_contents($file_name, false, null);
                    break;
            }
            return $d;
        }

        return $default;
    }

    /**
     * 删除配置
     * @param string|null $name
     * @param bool $absolutePath
     * @return bool
     */
    public static function delete(?string $name, bool $absolutePath = false): bool
    {
        if ($name === null || $name === '') {
            return false;
        }
        $file_name = $absolutePath ? $name : db_path() . DIRECTORY_SEPARATOR . $name;
        clearstatcache();
        if (is_file($file_name)) {
            return @unlink($file_name);
        }
        return true;
    }
}
