#!/bin/sh

docker-compose up -d catchbox-mongo

pushd src
export MONGO_ENABLED=1
export MONGO_HOST=localhost
export MONGO_PORT=9093
export MONGO_USER=catchbox_checker
export MONGO_PASSWORD=catchbox_checker
gunicorn -c gunicorn.conf.py checker:app
