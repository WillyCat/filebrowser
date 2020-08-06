#!/usr/bin/env bash
IMAGE=willycat/filebrowser
docker pull $IMAGE
# derived from https://stackoverflow.com/questions/26423515/how-to-automatically-update-your-docker-containers-if-base-images-are-updated
set -e
NAME=fb
CID=$(docker ps | grep ${NAME} | awk '{print $1}')
#docker login

for im in $CID
do
    LATEST=$(docker inspect --format "{{.Id}}" ${IMAGE})
    RUNNING=$(docker inspect --format "{{.Image}}" ${im})
    NAME=$(docker inspect --format '{{.Name}}' ${im} | sed "s/\///g")
    echo "Latest:" ${LATEST}
    echo "Running:" ${RUNNING}
    if [ "${RUNNING}" != "${LATEST}" ];then
        echo "upgrading ${NAME}"
	sh stop.sh
	sh prune.sh
	sh run.sh
    else
        echo "${NAME} up to date"
    fi
done

exit 0
