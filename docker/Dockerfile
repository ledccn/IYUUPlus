FROM alpine:latest

LABEL maintainer="david <367013672@qq.com>" version="2.0"

##
# ---------- env settings ----------
##

# --build-arg timezone=Asia/Shanghai
ARG timezone
# prod pre test dev
ARG app_env=prod
# default use www-data user
# ARG add_user=www-data

ENV APP_ENV=${app_env:-"prod"} \
    TIMEZONE=${timezone:-"Asia/Shanghai"}

##
# ---------- building ----------
##

WORKDIR /IYUU
RUN set -ex \
        # change apk source repo
        #&& sed -i 's/dl-cdn.alpinelinux.org/mirrors.ustc.edu.cn/' /etc/apk/repositories \
        && sed -i 's/dl-cdn.alpinelinux.org/mirrors.aliyun.com/' /etc/apk/repositories \
        && apk update \
        && apk add --no-cache \
        # Install base packages ('ca-certificates' will install 'nghttp2-libs')
        ca-certificates \
        curl \
        tar \
        # xz \
        libressl \
        # openssh  \
        openssl  \
        wget \
        zip \
        unzip \
        git \
        tzdata \
        pcre \
        # install php7 and some extensions
        php7 \
        # php7-common \
        # php7-bcmath \
        php7-curl \
        # php7-ctype \
        php7-dom \
        # php7-fileinfo \
        # php7-gettext \
        # php7-gd \
        # php7-iconv \
        # php7-imagick \
        php7-json \
        php7-mbstring \
        #php7-mongodb \
        # php7-mysqlnd \
        php7-openssl \
        php7-opcache \
        php7-pdo \
        # php7-pdo_mysql \
        php7-pdo_sqlite \
        php7-phar \
        php7-pcntl \
        php7-posix \
        # php7-redis \
        php7-simplexml \
        php7-sockets \
        # php7-sodium \
        php7-session \
        # php7-sysvshm \
        # php7-sysvmsg \
        # php7-sysvsem \
        # php7-tokenizer \
        php7-zip \
        php7-zlib \
        php7-xml \
        && git clone https://github.com/ledccn/IYUUPlus.git /IYUU \
        # && git clone https://gitee.com/ledc/iyuuplus.git /IYUU \
        && git remote set-url origin https://gitee.com/ledc/iyuuplus.git \
        && git config --global pull.ff only \
        && apk del --purge *-dev \
        # install latest composer
        && wget https://getcomposer.org/composer.phar \
        && mv composer.phar /usr/local/bin/composer \
        # make it executable
        && chmod +x /usr/local/bin/composer \
        # - config PHP
        && { \
            echo "upload_max_filesize=100M"; \
            echo "post_max_size=108M"; \
            echo "memory_limit=1024M"; \
            echo "date.timezone=${TIMEZONE}"; \
        } | tee /etc/php7/conf.d/99-overrides.ini \
        # - config timezone
        && ln -sf /usr/share/zoneinfo/${TIMEZONE} /etc/localtime \
        && echo "${TIMEZONE}" > /etc/timezone \
        # ---------- some config work ----------
        # - ensure 'www-data' user exists(82 is the standard uid/gid for "www-data" in Alpine)
        # && addgroup -g 82 -S ${add_user} \
        # && adduser -u 82 -D -S -G ${add_user} ${add_user} \
        # # - create user dir
        # && mkdir -p /data \
        # && chown -R ${add_user}:${add_user} /data \
        && rm -rf /var/cache/apk/* /tmp/* /usr/share/man /usr/share/php7 \
        && cp /IYUU/docker/entrypoint.sh /entrypoint.sh \
        && echo -e "\033[42;37m Build Completed :).\033[0m\n"

EXPOSE 8787
VOLUME ["/IYUU/db", "/IYUU"]
ENTRYPOINT ["/entrypoint.sh"]
