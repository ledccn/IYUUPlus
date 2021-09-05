#!/bin/sh

cd /IYUU

if [[ ! -d .git ]]; then
    #git clone https://github.com/ledccn/IYUUPlus.git /tmp/IYUU
    git clone https://gitee.com/ledc/iyuuplus.git /tmp/IYUU
    find /tmp/IYUU -mindepth 1 -maxdepth 1 | xargs -I {} cp -r {} /IYUU
    rm -rf /tmp/IYUU
else
    git fetch --all
    git reset --hard origin/master
    git pull
fi

if [[ ! -s .env ]]; then
    cp -f .env.example .env
fi

if [[ -z "${CRON_UPDATE}" ]]; then
    minute=$(($RANDOM % 60))
    hour_start=$(($RANDOM % 6))
    hour_interval=$(($RANDOM % 4 + 6))
    CRON_UPDATE="${minute} ${hour_start}-23/${hour_interval} * * *"
fi

echo "设置cron..."
echo "${CRON_UPDATE} cd /IYUU && git fetch --all && git reset --hard origin/master && git pull && php start.php restart -d" | crontab -

echo "当前crontab如下："
crontab -l

php /IYUU/start.php start -d
crond -f
