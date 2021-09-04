#!/bin/sh
docker build -f Dockerfile -t iyuuplus:latest .
docker run -it -v /root/plus:/IYUU/db -p 8787:8787 --network bridge --name IYUUPlus --restart always -d iyuuplus:latest
docker exec -it IYUUPlus sh