FROM php:7.3-apache
RUN \
    apt-get update && \
    apt-get install libldap2-dev -y && \
    rm -rf /var/lib/apt/lists/* && \
    docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu/ && \
    docker-php-ext-install ldap
COPY src/ /var/www/html/
RUN mkdir /etc/filebrowser
COPY conf.json /etc/filebrowser/conf.json
