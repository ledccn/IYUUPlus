@echo off
:chcp 65001
title IYUUPlus
cd /d "%~dp0"
echo Docker: https://hub.docker.com/r/iyuucn/iyuuplus
echo Gitee: https://gitee.com/ledc/iyuuplus
echo Github: https://github.com/ledccn/IYUUPlus
echo QQ: 859882209 / 931954050 / 924099912
echo Token: https://iyuu.cn
echo Blog: https://www.iyuu.cn
echo Docs: http://api.iyuu.cn/docs.php
echo.
echo "正在检测源码库的git特征文件..."
if exist "%~dp0.git\config" (
    echo "正在为您自动更新..."
    git fetch --all
    git reset --hard origin/master
    echo "更新完成！"
) else (
    echo "当前IYUUPlus源码，并非通过git拉取，不支持自动更新"
)
echo.
goto :checkPHP

:checkPHP
if exist "%~dp0php\php.exe" (set PHP_BINARY=%~dp0php\php.exe) else (set PHP_BINARY=php.exe)
echo "PHP二进制程序："%PHP_BINARY%
%PHP_BINARY% -v
echo.
echo "如果您需要停止程序，请按下组合键：CTRL + C"
%PHP_BINARY% windows.php
pause
goto :end

:end
rem 结束
echo.