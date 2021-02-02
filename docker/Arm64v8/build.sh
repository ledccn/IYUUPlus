#!/bin/sh
#ARM平台通用脚本
docker build -f Dockerfile -t iyuuplus:arm64v8 .
docker run -it -v /root/plus:/IYUU/db -p 8787:8787 --network bridge --name IYUUPlus --restart always -d iyuuplus:arm64v8
docker exec -it IYUUPlus sh