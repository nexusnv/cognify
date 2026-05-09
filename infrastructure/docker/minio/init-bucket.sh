#!/bin/sh
set -eu

mc alias set cognify-local http://minio:9000 "$MINIO_ROOT_USER" "$MINIO_ROOT_PASSWORD"
mc mb --ignore-existing "cognify-local/$MINIO_BUCKET"
mc anonymous set none "cognify-local/$MINIO_BUCKET"
