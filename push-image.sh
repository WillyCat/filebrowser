#!/bin/bash
IMAGE=willycat/filebrowser
docker login
docker tag filebrowser ${IMAGE}
docker push ${IMAGE}
exit 0
