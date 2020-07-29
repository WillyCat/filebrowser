#!/bin/bash
# :Z is necessary when using selinux
# map port 80 (inside) to 9111 (outside)
docker run -d -v $PWD/conf/:/etc/filebrowser/:Z -p 9111:80 --name fb filebrowser:latest
exit $?
