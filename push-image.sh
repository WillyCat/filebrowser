#!/bin/bash
read -p "Tag: " tag
IMAGE=willycat/filebrowser
docker login
docker tag filebrowser ${IMAGE}:${tag}
docker push ${IMAGE}:${tag}
exit 0
