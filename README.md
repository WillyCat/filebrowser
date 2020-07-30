# filebrowser

A very simple file browser with minimal but essential features

Native deployment
Note: LDAP requires PHP ldap_ functions, if using ldap, you might need to update your php configuration
1- drop the PHP file in a PHP-enabled web server,
2- drop conf.json file to /etc/filebrowser/conf.json, make sure appropriate rights are set so script can read conf
3- edit /etc/filebrowser/conf.json to suit your needs

Docker deployment :
you need a running docker daemon to perform this
1- sh make.sh to create filebrowser docker image,
2- edit run.sh to map your volumes, ports etc.
3- edit conf.json to suit your needs
4- sh run.sh to start container
(then use sh stop.sh to stop and sh restart.sh to restart)

Features :
* LDAP integration (optional),
* uses paginated display to display a large number of files,
* download dir content in CSV format,
* upload files,
* delete files,
* sort display,
* configure different roots with different parameters,
* configure timezone and charset,
* bookmark favorite directories (stored locally on browser cookie)

Built on :
* bootstrap 4.5
* jquery 3.2.1
* PHP 7.4 with ldap functions
* Linux
