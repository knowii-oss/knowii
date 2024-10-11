#!/bin/bash

# Creates a Quadlet container unit file for the Knowii Browserless Chromium container
# References:
# https://docs.podman.io/en/stable/markdown/podman-systemd.unit.5.html
# https://www.redhat.com/sysadmin/quadlet-podman

echo "Loading the environment variables."
# Load the environment variables
# Reference: https://gist.github.com/mihow/9c7f559807069a03e302605691f85572
set -o allexport; source .env; set +o allexport

echo "Creating the Podman Quadlet container unit file."
KNOWII_BROWSERLESS_CONTAINER_NAME=knowii_browserless_chromium
USER_ID=1000
CONTAINER_QUADLET_FILE_NAME=${KNOWII_BROWSERLESS_CONTAINER_NAME}.container
CONTAINER_QUADLET_FOLDER_PATH=~/.config/containers/systemd
KNOWII_BROWSERLESS_SERVICE_NAME=${KNOWII_BROWSERLESS_CONTAINER_NAME}.service

# Making sure that the folder exists
mkdir -p ${CONTAINER_QUADLET_FOLDER_PATH}

cat << EOF | tee ${CONTAINER_QUADLET_FOLDER_PATH}/${CONTAINER_QUADLET_FILE_NAME}
[Unit]
Description=Knowii Browserless Chromium container
After=network-online.target

[Container]
ContainerName=${KNOWII_BROWSERLESS_CONTAINER_NAME}
Environment=CONCURRENT=${BROWSERLESS_CONCURRENT_SESSIONS} TOKEN=${BROWSERLESS_TOKEN} TIMEOUT=${BROWSERLESS_TIMEOUT} QUEUED=${BROWSERLESS_MAX_QUEUE_LENGTH}
Image=ghcr.io/browserless/chromium
PublishPort=${BROWSERLESS_CHROME_PORT}:${BROWSERLESS_CHROME_PORT}

[Service]
Restart=always
# Extend Timeout to allow time to pull the image
TimeoutStartSec=900

[Install]
# Start by default on boot
WantedBy=multi-user.target default.target

EOF

echo "Reloading systemd daemon to recognize the new unit file."
systemctl --user daemon-reload

echo "Starting the Knowii Browserless Chromium service."
systemctl --user start ${KNOWII_BROWSERLESS_SERVICE_NAME}

echo "The Knowii Browserless Chromium service has been created and started. Checking the status."
systemctl --no-pager --user status ${KNOWII_BROWSERLESS_SERVICE_NAME}
