#!/bin/bash
echo "Tag: \c"
read tag
IMAGE=willycat/filebrowser
docker login
docker tag filebrowser ${IMAGE}:${tag}
docker push ${IMAGE}
exit 0
