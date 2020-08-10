FROM php:7.4-apache
RUN \
    apt-get update && \
    apt-get install libldap2-dev -y && \
    rm -rf /var/lib/apt/lists/* && \
    docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu/ && \
    docker-php-ext-install ldap
COPY version /var/www/html/
COPY *.php /var/www/html/
RUN mkdir /var/www/html/classes
COPY classes/*.php /var/www/html/classes/
RUN mkdir /var/www/html/css
COPY css/*.css* /var/www/html/css/
RUN mkdir /var/www/html/images
COPY images/* /var/www/html/images/
RUN mkdir /etc/filebrowser
COPY conf.json.sample /etc/filebrowser/conf.json
