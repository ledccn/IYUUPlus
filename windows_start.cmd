@echo off
chcp 65001
title IYUUPlus
cd /d "%~dp0"
echo Description:
echo Docker: https://hub.docker.com/r/iyuucn/iyuuplus
echo Gitee: https://gitee.com/ledc/iyuuplus
echo Github: https://github.com/ledccn/IYUUPlus
echo QQ: 859882209 / 931954050 / 924099912
echo Wenda: http://wenda.iyuu.cn
echo Token: https://iyuu.cn
echo Blog: https://www.iyuu.cn
echo Docs: http://api.iyuu.cn/docs.php
goto :ping


:ping
rem 检查服务器
echo 正在为您检查本机网络情况，请耐心等待...
ping demo.iyuu.cn
echo.
goto :checkEnv


:checkEnv
rem 检查.env.example文件是否存在
if exist "%~dp0.env.example" goto :checkGit
rem 检查GIT程序
git --version|find "git version">nul&&goto :install
goto :installError


:install
rem 通过GIT下载源码
git clone https://gitee.com/ledc/iyuuplus.git %~dp0IYUUPlus
echo 通过GIT自动安装完成，正在准备执行程序...
cd IYUUPlus
goto :start


:installError
rem 安装错误
cls
echo 当前运行环境未检测到git程序，不支持自动安装失败。
pause
goto :end


:checkGit
rem 检查GIT程序
git --version|find "git version">nul&&goto :pull
cls
echo 当前IYUUPlus运行环境未检测到git程序，不支持自动更新。
echo 推荐您使用git来下载整个代码库！
echo 您可以在安装git程序后，在命令行内输入：
echo git clone https://gitee.com/ledc/iyuuplus.git
goto :start


:pull
rem 通过GIT更新源码
echo 正在为您自动更新...
git --version
git fetch --all
git reset --hard origin/master
echo 升级完成！
goto :start


:start
rem 运行脚本
echo.
echo 如果您需要停止程序，请按下组合键：CTRL + C
php start.php task.php
pause
goto :end


:end
rem 结束
echo.