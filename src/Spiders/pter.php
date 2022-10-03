<?php

namespace IYUU\Spiders;

use IYUU\Library\Selector;

class pter extends mteam
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
        $h2StrEnd = '</td><td class="embedded" width="6px">';
        $h2_endoffset = strpos($html, $h2StrEnd);
        $temp = substr($html, 0, $h2_endoffset);
        $h2_offset = strrpos($temp, $h2StrStart);
        //p($temp);
        if ($h2_offset === false) {
            $title = '';
        } else {
            //存在副标题
            $h2_len = strlen($temp) - $h2_offset - strlen($h2StrStart);
            $titleTemp = substr($temp, $h2_offset + strlen($h2StrStart), $h2_len);
            if (strpos($titleTemp, $h2StrStart) != false) {
                //过滤已下载、进行中等进度框
                $titleTemp = substr($titleTemp, 0, strpos($titleTemp, $h2StrStart));
            }
            // 精确适配标签 begin
            $titleSpan = '';
            $title = selector::remove($titleTemp, "//div");
            $span = selector::select($titleTemp, '//a');
            if (!empty($span)) {
                if (is_array($span)) {
                    foreach ($span as $vv) {
                        if (empty($vv)) {
                            continue;
                        }
                        $titleSpan .= '[' . trim($vv) . '] ';
                    }
                } else {
                    $titleSpan .= '[' . trim($span) . '] ';
                }
            }
            // 精确适配标签 end
            $title = $titleSpan . $title;
            //最后过滤
            $title = strip_tags($title);
        }

        return $title;
    }
}
