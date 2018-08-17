FROM php:7.1-apache

MAINTAINER Auurélien RICHAUD <aurelien.richaud@btoweb.fr>

# Set Workdir
WORKDIR /var/www

RUN apt-get update && apt-get install -y gpg wget unzip

### Install NODE
RUN groupadd --gid 1000 node \
  && useradd --uid 1000 --gid node --shell /bin/bash --create-home node

# gpg keys listed at https://github.com/nodejs/node#release-team
#RUN set -ex \
#  && for key in \
#    94AE36675C464D64BAFA68DD7434390BDBE9B9C5 \
#    FD3A5288F042B6850C66B31F09FE44734EB7990E \
#    71DCFD284A79C3B38668286BC97EC7A07EDE3FC1 \
#    DD8F2338BAE7501E3DD5AC78C273792F7D83545D \
#    C4F0DFFF4E8C1A8236409D08E73BC641CC11F4C8 \
#    B9AE9905FFD7803F25714661B63B535A4C206CA9 \
#    56730D5401028683275BD23C23EFEFE93C4CFFFE \
#    77984A986EBC2AA786BC0F66B01FBB92821C587A \
#    8FCCA13FEF1D0C2E91008E09770F7A9A5AE15600 \
#  ; do \
#    gpg --keyserver hkp://p80.pool.sks-keyservers.net:80 --recv-keys "$key" || \
#    gpg --keyserver hkp://ipv4.pool.sks-keyservers.net --recv-keys "$key" || \
#    gpg --keyserver hkp://pgp.mit.edu:80 --recv-keys "$key" ; \
#  done

ENV NODE_VERSION 8.9.4

RUN ARCH= && dpkgArch="$(dpkg --print-architecture)" \
  && case "${dpkgArch##*-}" in \
    amd64) ARCH='x64';; \
    ppc64el) ARCH='ppc64le';; \
    s390x) ARCH='s390x';; \
    arm64) ARCH='arm64';; \
    armhf) ARCH='armv7l';; \
    i386) ARCH='x86';; \
    *) echo "unsupported architecture"; exit 1 ;; \
  esac \
  && curl -SLO "https://nodejs.org/dist/v$NODE_VERSION/node-v$NODE_VERSION-linux-$ARCH.tar.xz" \
  && curl -SLO --compressed "https://nodejs.org/dist/v$NODE_VERSION/SHASUMS256.txt.asc" \
#  && gpg --batch --decrypt --output SHASUMS256.txt SHASUMS256.txt.asc \
#  && grep " node-v$NODE_VERSION-linux-$ARCH.tar.xz\$" SHASUMS256.txt | sha256sum -c - \
  && tar -xJf "node-v$NODE_VERSION-linux-$ARCH.tar.xz" -C /usr/local --strip-components=1 --no-same-owner \
  && rm "node-v$NODE_VERSION-linux-$ARCH.tar.xz" \
  && ln -s /usr/local/bin/node /usr/local/bin/nodejs

ENV YARN_VERSION 1.3.2

RUN set -ex \
#  && for key in \
#    6A010C5166006599AA17F08146C2130DFD2497F5 \
#  ; do \
#    gpg --keyserver hkp://p80.pool.sks-keyservers.net:80 --recv-keys "$key" || \
#    gpg --keyserver hkp://ipv4.pool.sks-keyservers.net --recv-keys "$key" || \
#    gpg --keyserver hkp://pgp.mit.edu:80 --recv-keys "$key" ; \
#  done \
  && curl -fSLO --compressed "https://yarnpkg.com/downloads/$YARN_VERSION/yarn-v$YARN_VERSION.tar.gz" \
  && curl -fSLO --compressed "https://yarnpkg.com/downloads/$YARN_VERSION/yarn-v$YARN_VERSION.tar.gz.asc" \
#  && gpg --batch --verify yarn-v$YARN_VERSION.tar.gz.asc yarn-v$YARN_VERSION.tar.gz \
  && mkdir -p /opt/yarn \
  && tar -xzf yarn-v$YARN_VERSION.tar.gz -C /opt/yarn --strip-components=1 \
  && ln -s /opt/yarn/bin/yarn /usr/local/bin/yarn \
  && ln -s /opt/yarn/bin/yarn /usr/local/bin/yarnpkg \
  && rm yarn-v$YARN_VERSION.tar.gz.asc yarn-v$YARN_VERSION.tar.gz

# Install UglifyCSS/JS
RUN npm install --global uglify-js uglifycss less

### PHP modules
RUN apt-get install -y bash git curl g++ libicu-dev libcurl4-openssl-dev libjpeg62-turbo-dev  libsqlite3-dev libfreetype6-dev libmcrypt-dev libssh2-1-dev libssl-dev libxml2-dev locales openssl sqlite3 zlib1g-dev \
  && docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ \
  && docker-php-ext-install curl intl mysqli pcntl pdo pdo_mysql pdo_sqlite mcrypt pcntl sysvmsg zip

### Composer
RUN curl -o /tmp/composer-setup.php https://getcomposer.org/installer \
  && curl -o /tmp/composer-setup.sig https://composer.github.io/installer.sig \
  && php -r "if (hash('SHA384', file_get_contents('/tmp/composer-setup.php')) !== trim(file_get_contents('/tmp/composer-setup.sig'))) { unlink('/tmp/composer-setup.php'); echo 'Invalid installer' . PHP_EOL; exit(1); }" \
  && php /tmp/composer-setup.php --no-ansi --install-dir=/usr/local/bin --filename=composer && rm -rf /tmp/composer-setup.php

# EasyInstall & SupervisorD
RUN apt-get install -y python-setuptools \
	&& easy_install supervisor

### Apache configuration
RUN sed -i "s/DocumentRoot \/var\/www\/html/DocumentRoot \/var\/www\/public/" /etc/apache2/sites-available/000-default.conf \
  && a2enmod rewrite
EXPOSE 80

### Installation des sources
RUN wget https://github.com/BtoWeb/docker_cron/archive/master.zip -O /tmp/master.zip \
	&& unzip /tmp/master.zip -d /tmp \
	&& mv /tmp/docker_cron-master/docker/supervisord.conf /etc/supervisord.conf \
	&& mv /tmp/docker_cron-master/docker/run.sh /run.sh \
	&& mv /tmp/docker_cron-master/* /var/www/ \
	&& mv /tmp/docker_cron-master/.env /var/www/ \
	&& rm -rf /tmp/docker_cron-master/ /tmp/master.zip \
	&& chmod 0755 /run.sh

RUN chown -R www-data: /var/www/ \
    && su www-data -s /bin/bash -c "composer install"

# Container is just a supervisord running
CMD ["/run.sh"]