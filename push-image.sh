#!/bin/bash
read -p "Tag: " tag
IMAGE=willycat/filebrowser
docker login
docker tag filebrowser ${IMAGE}:${tag}
docker push ${IMAGE}:${tag}
docker tag filebrowser ${IMAGE}:latest
docker push ${IMAGE}:latest
exit 0
