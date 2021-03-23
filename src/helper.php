<?php
use IYUU\Library\IFile;
use IYUU\Library\Table;

/**
 * 微信推送 爱语飞飞
 * @param string $site
 * @param array $torrent 种子数组
 * Array
    (
        [id] => 118632
        [h1] => CCTV5+ 2019 ATP Men's Tennis Final 20191115B HDTV 1080i H264-HDSTV
        [title] => 央视体育赛事频道 2019年ATP男子网球年终总决赛 单打小组赛 纳达尔VS西西帕斯 20191115[优惠剩余时间：4时13分]
        [details] => https://xxx.me/details.php?id=118632
        [download] => https://xxx.me/download.php?id=118632
        [filename] => 118632.torrent
        [type] => 0
        [sticky] => 1
        [time] => Array
            (
                [0] => "2019-11-16 20:41:53">4时13分
                [1] => "2019-11-16 14:41:53">1时<br />46分
            )
        [comments] => 0
        [size] => 5232.64MB
        [seeders] => 69
        [leechers] => 10
        [completed] => 93
        [percentage] => 100%
        [owner] => 匿名
    )
 * @return false|string
 */
function send($site = '', $torrent = array())
{
    $br = "\r\n";
    $text = $site. ' 免费：' .$torrent['filename']. '，添加成功';
    $desp = '主标题：'.$torrent['h1'] . $br;

    if (isset($torrent['title'])) {
        $desp .= '副标题：'.$torrent['title']. $br;
    }
    if (isset($torrent['size'])) {
        $desp .= '大小：'.$torrent['size']. $br;
    }
    if (isset($torrent['seeders'])) {
        $desp .= '做种数：'.$torrent['seeders']. $br;
    }
    if (isset($torrent['leechers'])) {
        $desp .= '下载数：'.$torrent['leechers']. $br;
    }
    if (isset($torrent['owner'])) {
        $desp .= '发布者：'.$torrent['owner']. $br;
    }
    return ff($text, $desp);
}

/**
 * @brief 下载种子
 * @param string $url 种子URL
 * @param string $cookies 模拟登陆的cookie
 * @param $useragent
 * @param string $method
 * @return mixed 返回的数据
 */
function download($url, $cookies='', $useragent='', $method = 'GET')
{
    $header = array(
        "Content-Type:application/x-www-form-urlencoded",
        'User-Agent: '.$useragent);
    $ch = curl_init();
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
    }
    if (stripos($url, 'https://') === 0) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSLVERSION, 1);
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_COOKIE, $cookies);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
    curl_setopt($ch, CURLOPT_TIMEOUT, 600);
    $data = curl_exec($ch);
    $status = curl_getinfo($ch);
    curl_close($ch);
    if (isset($status['http_code']) && $status['http_code'] == 200) {
        return $data;
    }
    if (isset($status['http_code']) && $status['http_code'] == 302) {
        return download($status['redirect_url'], $cookies, $useragent);
    }
    return $data;
}

/**
 * @brief 文件大小格式化为MB
 * @param string $from 文件大小
 * @return int 单位MB
 */
function convertToMB($from)
{
    $number=substr($from, 0, -2);
    $number = $number + 0;
    switch (strtoupper(substr($from, -2))) {
        case "KB":
            return $number/1024;
        case "MB":
            return $number;
        case "GB":
            return $number*pow(1024, 1);
        case "TB":
            return $number*pow(1024, 2);
        case "PB":
            return $number*pow(1024, 3);
        default:
            return $from;
    }
}

/**
 * 字节数Byte转换为KB、MB、GB、TB
 * @param $num
 * @return string
 */
function getFilesize($num)
{
    $p = 0;
    $format='bytes';
    if ($num>0 && $num<1024) {
        return number_format($num).' '.$format;
    }
    if ($num>=1024 && $num<pow(1024, 2)) {
        $p = 1;
        $format = 'KB';
    }
    if ($num>=pow(1024, 2) && $num<pow(1024, 3)) {
        $p = 2;
        $format = 'MB';
    }
    if ($num>=pow(1024, 3) && $num<pow(1024, 4)) {
        $p = 3;
        $format = 'GB';
    }
    if ($num>=pow(1024, 4) && $num<pow(1024, 5)) {
        $p = 3;
        $format = 'TB';
    }
    $num /= pow(1024, $p);
    return number_format($num, 2).$format;
}

/**
 * @brief 种子过滤器
 * @param array $filter 站点标识
 * @param array  $torrent 种子数组
 * 	Array
    (
        [id] => 118632
        [h1] => CCTV5+ 2019 ATP Men's Tennis Final 20191115B HDTV 1080i H264-HDSTV
        [title] => 央视体育赛事频道 2019年ATP男子网球年终总决赛 单打小组赛 纳达尔VS西西帕斯 20191115[优惠剩余时间：4时13分]
        [details] => https://xxx.me/details.php?id=118632
        [download] => https://xxx.me/download.php?id=118632
        [filename] => 118632.torrent
        [type] => 0
        [sticky] => 1
        [time] => Array
        (
        [0] => "2019-11-16 20:41:53">4时13分
        [1] => "2019-11-16 14:41:53">1时<br />46分
        )
        [comments] => 0
        [size] => 5232.64MB
        [seeders] => 69
        [leechers] => 10
        [completed] => 93
        [percentage] => 100%
        [owner] => 匿名
    )
 * @return bool 或 string 	false不过滤
 */
function filter($filter = [], $torrent = array())
{
    if (empty($filter)) {
        return false;
    }
    $filename = $torrent['filename'];

    // 大小过滤
    if (!empty($torrent['size'])) {
        $size = convertToMB($torrent['size']);
        $min = isset($filter['size']['min']) ? convertToMB($filter['size']['min']) : 0;
        $max = isset($filter['size']['max']) ? convertToMB($filter['size']['max']) : 2097152;	//默认 2097152MB = 2TB
        if ($size < $min || $size > $max) {
            return $filename. ' ' .$size. 'MB，被大小过滤';
        }
    }

    // 种子数过滤
    if (!empty($torrent['seeders'])) {
        $seeders = $torrent['seeders'];
        $min = isset($filter['seeders']['min']) ? $filter['seeders']['min'] : 1;	//默认 1
        $max = isset($filter['seeders']['max']) ? $filter['seeders']['max'] : 3;	//默认 3
        if ($seeders < $min || $seeders > $max) {
            return $filename. ' 当前做种' .$seeders. '人，被过滤';
        }
    }

    // 下载数过滤
    if (!empty($torrent['leechers'])) {
        $leechers = $torrent['leechers'];
        $min = isset($filter['leechers']['min']) ? $filter['leechers']['min'] : 0;		//默认
        $max = isset($filter['leechers']['max']) ? $filter['leechers']['max'] : 30000;	//默认
        if ($leechers < $min || $leechers > $max) {
            return $filename. ' 当前下载' .$leechers. '人，被过滤';
        }
    }

    // 完成数过滤
    if (!empty($torrent['completed'])) {
        $completed = $torrent['completed'];
        $min = isset($filter['completed']['min']) ? $filter['completed']['min'] : 0;		//默认
        $max = isset($filter['completed']['max']) ? $filter['completed']['max'] : 30000;	//默认
        if ($completed < $min || $completed > $max) {
            return $filename. ' 已完成数' .$completed. '人，被过滤';
        }
    }

    // 标题副标题过滤
    if (!empty($torrent['h1']) || !empty($torrent['title'])) {
        $h1 = !empty($torrent['h1']) ? $torrent['h1'] : '';
        $title = !empty($torrent['title']) ? $torrent['title'] : '';
        $subject = $h1 . $title;

        // 正则表达式过滤
        if (!empty($filter['regex'])) {
            $pattern = '/'.$filter['regex'].'/';
            if (!preg_match($pattern, $subject, $matchs)) {
                return $filename. ' 未匹配到正则表达式[ '. $filter['regex']. ' ]，被过滤';
            } else {
                echo '匹配到正则表达式：[ '. $filter['regex'] .' ]'. PHP_EOL;
            }
        }

        // 关键字匹配
        if (!empty($filter['keyword'])) {
            $keyword = trim($filter['keyword']);
            $mode = isset($filter['keyword_mode']) ? 'AND' : 'OR';
            // NO1:查找特征
            if (stripos($keyword, ',') !== false) {
                // 匹配数组
                // NO2:分隔
                $keywords = explode(',', $keyword);
                // NO3:移除空白字符和预定义字符
                array_walk($keywords, function (&$v, $k){
                    $v = trim($v);
                });
                // NO4:过滤空
                $keywords = array_filter($keywords, function ($v, $k) {
                    return !empty($v);
                }, ARRAY_FILTER_USE_BOTH);
                // 非空才匹配
                if (count($keywords)) {
                    $count = 0;
                    $yes = [];
                    $no = [];
                    foreach ($keywords as $item) {
                        if (stripos($subject, $item) !== false) {
                            $count++;
                            $yes[] = $item;
                        } else {
                            $no[] = $item;
                        }
                    }
                    // 匹配后判断
                    if ($count) {
                        $msg = count($keywords).'个关键字：[ '. join(' ,' ,$keywords) .' ]， 匹配到'.$count.'个；';
                        if ($mode === 'AND' && $count < count($keywords)) {
                            return $msg.'未匹配：[ '. join(' ,', $no) .' ]，被过滤';
                        } else {
                            echo $msg.'匹配到关键字：[ '. join(' ,',$yes) .' ]'. PHP_EOL;
                        }
                    } else {
                        return '未匹配到关键字：[ '. join(' ,', $keywords) .' ]，被过滤';
                    }
                }
            } else {
                // 匹配字符串
                if (stripos($subject, $keyword) === false) {
                    return '未匹配到关键字：[ '. $keyword .' ]，被过滤';
                } else {
                    echo '匹配到关键字：[ '. $keyword .' ]'. PHP_EOL;
                }
            }
        }
    }

    // 满足上述所有条件，不过滤
    return false;
}

/**
 * 日志记录函数(追加式写入)
 * @param string $data
 * @param string $name
 * @param string $path
 * @return bool|int
 */
function wlog($data='', $name = '', $path = '')
{
    if (is_bool($data)) {
        $show_data=$data ? 'true' : 'false';
    } elseif (is_null($data)) {
        $show_data='null';
    } else {
        $show_data=print_r($data, true);
    }
    // 写入日志
    $dir = $path==='' ? TORRENT_PATH . DS . 'cache' . DS : $path;
    IFile::mkdir($dir);
    $file = $dir . $name . '.txt';
    $pointer = @fopen($file, "a");
    $worldsnum = @fwrite($pointer, $show_data);
    @fclose($pointer);
    return $worldsnum;
}

//PHP stdClass Object转array
function object_array($array)
{
    if (is_object($array)) {
        $array = (array)$array;
    }
    if (is_array($array)) {
        foreach ($array as $key=>$value) {
            $array[$key] = object_array($value);
        }
    }
    return $array;
}

/**
 * 奇数
 * @param $var
 * @return int
 */
function oddFilter($var)
{
    // 返回$var最后一个二进制位，
    // 为1则保留（奇数的二进制的最后一位肯定是1）
    return ($var & 1);
}

/**
 * 偶数
 * @param $var
 * @return bool
 */
function evenFilter($var)
{
    // 返回$var最后一个二进制位，
    // 为0则保留（偶数的二进制的最后一位肯定是0）
    return (!($var & 1));
}

/**
 * 显示支持的站点列表
 * @param string $dir
 * @param array $filter
 */
function ShowTableSites($dir = 'Protocols', $filter = array())
{
    // 过滤的文件
    switch ($dir) {
        case 'Protocols':
            $filter = ['axxxx','decodeBase'];
            break;
        case 'Rss':
            $filter = ['AbstractRss'];
            break;
        default:
            # code...
            break;
    }
    $data = [];
    $i = $j = $k = 0;   //i列、j序号、k行
    foreach (glob(__DIR__.DS.$dir.DS.'*.php') as $key => $start_file) {
        $start_file = str_replace("\\", "/", $start_file);
        $offset = strripos($start_file, '/');
        if ($offset===false) {
            $start_file = substr($start_file, 0, -4);
        } else {
            $start_file = substr($start_file, $offset+1, -4);
        }
        // 过滤示例、过滤解码接口
        if (in_array($start_file, $filter)) {
            continue;
        }
        // 控制多少列
        if ($i > 4) {
            $k++;
            $i = 0;
        }
        $i++;
        $j++;
        $data[$k][] = $j.". ".$start_file;
    }
    //输出表格
    $table = new Table();
    $table->setRows($data);
    echo($table->render());
}

/**
 * @param $t
 * @param $msg
 */
function sleepIYUU($t, $msg)
{
    echo $msg . PHP_EOL;
    do {
        echo microtime(true).$msg.' '.$t.'秒后继续...'.PHP_EOL;
        sleep(1);
    } while (--$t > 0);
}
