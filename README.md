# filebrowser

A very simple file browser with minimal but essential features

Preferred deployment:
use docker image on docker hub at https://hub.docker.com/repository/docker/willycat/filebrowser

Configuration:
conf.json - see details in readme.txt

Features :
* LDAP integration (optional),
* uses paginated display to display a large number of files,
* download dir content in CSV format,
* upload files,
* delete files,
* sort and filter display,
* configure different roots with different parameters,
* configure timezone and charset,
* bookmark favorite directories (stored locally on browser cookie)
* log all actions
* when using ldap authent, volumes can be shown/hidden to a list of groups
* container healthcheck

Built on :
* apache (https://httpd.apache.org/)
* PHP 8.4 (https://www.php.net/) with ldap functions (https://www.php.net/manual/en/book.ldap.php)
* bootstrap 4.6.2 (https://getbootstrap.com/)
* jquery 3.7.1 (https://jquery.com/)
* Feather icons (https://feathericons.com/)
