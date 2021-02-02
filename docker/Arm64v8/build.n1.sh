#!/bin/sh
docker build -t iyuuplus:arm64v8 .
docker run -it -v /root/plus:/IYUU/db -v /var/lib/transmission/torrents:/torrents -v /var/lib/qbittorrent/.local/share/data/qBittorrent/BT_backup:/BT_backup -p 8787:8787 --network bridge --name IYUUPlus --restart always -d iyuuplus:arm64v8
docker exec -it IYUUPlus sh