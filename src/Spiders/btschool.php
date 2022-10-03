<?php

namespace IYUU\Spiders;

use IYUU\Library\Selector;

class btschool extends mteam
{
    /**
     * 获取副标题
     * - 倒序算法
     * @param string $html
     * @return string
     */
    public static function getTitle(string $html): string
    {
        $h2StrStart = '<br/>';
        $h2StrEnd = '</td><td width="50" class="embedded"';
        $h2_offset = strpos($html, $h2StrEnd);
        $temp = substr($html, 0, $h2_offset);
        $h2_offset = strrpos($temp, $h2StrStart);
        //p($temp);
        if ($h2_offset === false) {
            $title = '';
        } else {
            $h2_len = strlen($temp) - $h2_offset - strlen($h2StrStart);
            //存在副标题
            $titleTemp = substr($temp, $h2_offset + strlen($h2StrStart), $h2_len);
            //二次过滤
            $titleTemp = Selector::remove($titleTemp, "//a");    //编码标签
            $titleTemp = Selector::remove($titleTemp, "//div");    //做种标签
            if (strpos($titleTemp, '<div ') != false) {
                $titleTemp = substr($titleTemp, 0, strpos($titleTemp, '<div '));
            }
            // 精确适配标签 begin
            $titleSpan = '';
            $title = Selector::remove($titleTemp, "//span");
            $span = Selector::select($titleTemp, '//span');
            if (!empty($span)) {
                if (is_array($span)) {
                    foreach ($span as $vv) {
                        if (empty($vv)) {
                            continue;
                        }
                        $titleSpan .= '[' . $vv . '] ';
                    }
                } else {
                    $titleSpan .= '[' . $span . '] ';
                }
            }
            // 精确适配标签 end
            $title = $titleSpan . $title;
            $title = strip_tags($title);
        }

        return $title;
    }
}
