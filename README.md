# filebrowser

A very simple file browser with minimal but essential features

Native deployment
1- drop the PHP file in a PHP-enabled web server,
2- drop conf file to /etc/filebrowser/conf
3- edit configuration file
you're done !

Docker deployment :
1- run make.sh to create a docker image,
2- edit run.sh to map your volumes, ports etc.
3- drop conf file to conf directory (conf/conf)
4- edit configuration file
5- run.sh to start container

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

Notes :
* LDAP requires PHP ldap_ functions, you might need to update your php configuration

Uses bootstrap 4.5 and jquery 3.2.1

Built on PHP 7.3 on Linux

Other configurations not tested.
