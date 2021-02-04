@echo off
chcp 65001
title IYUUPlus
cd /d "%~dp0"
echo Docker: https://hub.docker.com/r/iyuucn/iyuuplus
echo Gitee: https://gitee.com/ledc/iyuuplus
echo Github: https://github.com/ledccn/IYUUPlus
echo QQ: 859882209 / 931954050 / 924099912
echo Wenda: http://wenda.iyuu.cn
echo Token: https://iyuu.cn
echo Blog: https://www.iyuu.cn
echo Docs: http://api.iyuu.cn/docs.php
echo.
goto :ping

:ping
echo "正在为您检查本机网络，请耐心等待..."
ping demo.iyuu.cn
echo.
goto :checkEnv

:checkEnv
echo "检查.env.example文件是否存在..."
if exist "%~dp0.env.example" goto :checkGit
echo "检查GIT，尝试安装源码..."
git --version|find "git version">nul&&goto :install
goto :installError

:install
echo "通过GIT下载源码..."
git clone https://gitee.com/ledc/iyuuplus.git %~dp0IYUUPlus
echo "通过GIT自动安装完成，开始检测php执行程序..."
cd IYUUPlus
goto :checkPHP

:installError
cls
echo "当前运行环境未检测到git程序，自动安装失败。"
pause
goto :end

:checkGit
echo "检查GIT执行程序..."
git --version|find "git version">nul&&goto :pull
cls
echo "当前IYUUPlus运行环境未检测到git程序，不支持自动更新。"
echo "推荐您使用git来下载整个代码库！"
echo "您可以在安装git程序后，在命令行内输入："
echo "git clone https://gitee.com/ledc/iyuuplus.git"
goto :checkPHP

:pull
rem 通过GIT更新源码
if exist "%~dp0.git\config" (
    echo "正在为您自动更新..."
    git fetch --all
    git reset --hard origin/master
    echo "升级完成！"
) else (
    echo "当前IYUUPlus源码，并非通过git拉取，不支持自动更新"
)
echo.
goto :checkPHP

:checkPHP
if exist "%~dp0php\php.exe" (set PHP_BINARY=%~dp0php\php.exe) else (set PHP_BINARY=php.exe)
echo PHP二进制程序：%PHP_BINARY%
%PHP_BINARY% -v|find "PHP Group">nul&&goto :start
cls
echo "没有检测到PHP执行程序！！！"
echo "如果您已下载过php程序，请在解压缩之后，把php文件夹添加进系统的环境变量。"
echo "或者把php执行程序，解压缩到当前目录下的‘php’文件夹。"
echo "脚本运行终止！！！"
pause
goto :end

:start
%PHP_BINARY% -v
echo.
echo "如果您需要停止程序，请按下组合键：CTRL + C"
%PHP_BINARY% start.php task.php
pause
goto :end

:end
rem 结束
echo.