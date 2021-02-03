#!/bin/sh
set -e
envFile="/IYUU/.env"
if [ ! -f "$envFile" ]; then
#git clone https://github.com/ledccn/IYUUPlus.git /IYUU
git clone https://gitee.com/ledc/iyuuplus.git /IYUU
cd /IYUU && php -r "file_exists('.env') || copy('.env.example', '.env');"
fi
cd /IYUU && git fetch --all && git reset --hard origin/master
/usr/bin/php /IYUU/start.php start -d
/usr/sbin/crond -f