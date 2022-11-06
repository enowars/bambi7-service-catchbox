#!/bin/bash

touch /service/db.sqlite
sqlite3 /service/db.sqlite < init.sql

chmod 777 /service/{.,files,reports,db.sqlite}

/etc/init.d/nginx start
/etc/init.d/php7.4-fpm start

while true; do
	/service/cleaner
	sleep 60
done &

tail -f /var/log/nginx/error.log
