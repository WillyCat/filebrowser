# filebrowser

A very simple file browser easy to deploy

Native deployment: drop the file in a web server, edit configuration file (/etc/filebrowser/conf) and you're done !
Docker: use make.sh to create a docker image, edit run.sh to map your volumes, edit configuration file (conf/conf), run.sh and you're done !

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

Uses bootstrap 4.5 and jquery 3.2.1

Built on PHP 7.3 on Linux

Other configurations not tested.
