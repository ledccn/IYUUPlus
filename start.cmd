@echo off
chcp 65001
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
goto :checkPHP

:checkPHP
if exist "%~dp0php\php.exe" (set PHP_BINARY=%~dp0php\php.exe) else (set PHP_BINARY=php.exe)
echo "PHP二进制程序："%PHP_BINARY%
%PHP_BINARY% -v|find "PHP Group">nul&&goto :start
cls
echo "没有检测到PHP执行程序！！！"
echo "如果您已下载过php程序，请在解压缩后，把php文件夹添加进系统的环境变量。"
echo "或者把php执行程序，解压缩到当前目录下的php文件夹。"
echo "脚本运行终止！！！"
pause
goto :end

:start
%PHP_BINARY% -v
echo.
echo "如果您需要停止程序，请按下组合键：CTRL + C"
%PHP_BINARY% windows.php
pause
goto :end

:end
rem 结束
echo.