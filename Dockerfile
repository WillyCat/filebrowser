FROM php:7.4-apache
RUN \
    apt-get update && \
    apt-get install libldap2-dev -y && \
    rm -rf /var/lib/apt/lists/* && \
    docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu/ && \
    docker-php-ext-install ldap
RUN echo "SetEnv VER ${VER}" > /etc/apache2/conf-enabled/environment.conf
COPY index.php /var/www/html/
COPY filebrowser.css /var/www/html/
RUN mkdir /etc/filebrowser
COPY conf.json.sample /etc/filebrowser/conf.json
