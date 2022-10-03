<?php

namespace IYUU\Spiders;

class nanyangpt extends mteam
{
    /**
     * 获取副标题
     * - 倒序算法
     * @param string $html
     * @return string
     */
    public static function getTitle(string $html): string
    {
        $h2StrStart = '<br />';
        $h2StrEnd1 = '</td><td width="34" class="embedded"';    // 置顶
        $h2StrEnd2 = '</td><td class="embedded" width="40px"';    // 普通
        $h2_endOffset = strpos($html, $h2StrEnd1) === false ? strpos($html, $h2StrEnd2) : strpos($html, $h2StrEnd1);
        $temp = substr($html, 0, $h2_endOffset);
        $h2_offset = strrpos($temp, $h2StrStart);
        if ($h2_offset === false) {
            $title = '';
        } else {
            $h2_len = strlen($temp) - $h2_offset - strlen($h2StrStart);
            //存在副标题
            $titleTemp = substr($temp, $h2_offset + strlen($h2StrStart), $h2_len);
            if (strpos($titleTemp, '<span') != false) {
                $titleTemp = substr($titleTemp, 0, strpos($titleTemp, '<span'));
            }
            // 第二次过滤
            $title = $titleTemp;

            //最后过滤
            $title = str_replace('&nbsp;', "", $title);    // 过滤
            $title = strip_tags($title);
        }

        return $title;
    }
}
