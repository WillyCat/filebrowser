FROM php:8.4-apache
RUN \
    apt-get update && \
    apt-get install libldap2-dev -y && \
    rm -rf /var/lib/apt/lists/* && \
    docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu/ && \
    docker-php-ext-install ldap
RUN mkdir /etc/filebrowser /var/www/html/js /var/www/html/classes /var/www/html/css /var/www/html/images
COPY conf.json.sample /etc/filebrowser/conf.json
COPY images/* /var/www/html/images/
COPY js/*.js* /var/www/html/js/
COPY css/*.css* /var/www/html/css/
COPY classes/*.php /var/www/html/classes/
COPY *.php version /var/www/html/

HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 CMD curl -f http://localhost/?action=health || exit 1
