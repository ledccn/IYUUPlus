@echo off
title IYUUPlus
cd /d "%~dp0"
chcp 65001


:ping
echo 正在为您检查本机网络情况，请耐心等待...
echo.
ping demo.iyuu.cn
echo.
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
echo 如果您需要停止程序，请按下组合键：CTRL + C
php %~dp0start.php %~dp0task.php
pause
goto :end


:end
echo.