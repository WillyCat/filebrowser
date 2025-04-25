#!/bin/bash
# --platform linux/arm64 ne fonctionne pas car le Dockerfile fait explicitement
# reference a des chemins qui n'existent pas sur cette plateforme
#docker buildx build --platform linux/amd64,linux/arm64 --tag filebrowser:latest .
docker buildx build --platform linux/amd64 --tag filebrowser:latest .
exit $?
