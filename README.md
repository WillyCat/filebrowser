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

Built on :
* PHP 8.3 with ldap functions
* bootstrap 4.6.1
* jquery 3.6.0
* popper.js 1.6.0
