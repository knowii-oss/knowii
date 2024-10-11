#!/usr/bin/env bash

echo "Loading the environment variables."
# Load the environment variables
# Reference: https://gist.github.com/mihow/9c7f559807069a03e302605691f85572
set -o allexport; source .env; set +o allexport

KNOWII_BROWSERLESS_CONTAINER_NAME=knowii_browserless_chromium

echo "Creating the Knowii Browserless container"
podman run --detach --name ${KNOWII_BROWSERLESS_CONTAINER_NAME} -p ${BROWSERLESS_CHROME_PORT}:${BROWSERLESS_CHROME_PORT} -e CONCURRENT=${BROWSERLESS_CONCURRENT_SESSIONS} -e TOKEN=${BROWSERLESS_TOKEN} -e TIMEOUT=${BROWSERLESS_TIMEOUT} -e QUEUED=${BROWSERLESS_MAX_QUEUE_LENGTH} ghcr.io/browserless/chromium
