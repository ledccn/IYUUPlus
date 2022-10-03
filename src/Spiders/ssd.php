<?php

namespace IYUU\Spiders;

use IYUU\Library\Selector;

class ssd extends mteam
{
    /**
     * 获取副标题
     * - 正序算法
     * @param string $html
     * @return string
     */
    public static function getTitle(string $html): string
    {
        $h2_offset = strpos($html, '<br />') + strlen('<br />');
        $h2_len = strpos($html, '</td><td width="60" class="embedded"', $h2_offset) - $h2_offset;
        if ($h2_len > 0) {
            //存在副标题
            $title = substr($html, $h2_offset, $h2_len);
            //二次过滤
            $title = Selector::remove($title, "//a");
            $title = strip_tags($title);
        } else {
            $title = '';
        }

        return $title;
    }
}
