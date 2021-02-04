@echo off
title IYUUPlus
cd /d "%~dp0"
chcp 65001

:ping
echo 正在为您检查本机网络情况耐心等待...
echo.
ping demo.iyuu.cn | find "超时"  > NUL &&  goto fail
ping demo.iyuu.cn | find "目标主机"  > NUL &&  goto fail
echo 本机网络良好……
goto :git


:git
git --version|find "git version">nul&&goto :pull
cls
echo 当前IYUUPlus运行环境未检测到git程序，不支持自动更新。
echo 推荐您使用git来下载整个代码库！
echo 您可以在安装git程序后，在命令行内输入：
echo git clone https://gitee.com/ledc/iyuuplus.git
goto :start


:pull
echo 正在为您自动更新...
git --version
git fetch --all
git reset --hard origin/master
echo 升级完成！
goto :start


:start
echo.
echo 停止程序：CTRL + C
php %~dp0start.php %~dp0task.php
pause
goto :end


:fail
cls
echo 网络状况不太好呀，无法连接API服务器...
pause


:end
echo.