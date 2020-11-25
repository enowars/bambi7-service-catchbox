#!/bin/bash

# generate random pass
dd if=/dev/urandom count=4 bs=1 2>/dev/null | sha256sum | cut -d' ' -f1 > /service/internal/pass_admin

# change permissions
chown -R www-data:www-data /service/

# start services
/etc/init.d/nginx start
/etc/init.d/php7.2-fpm start

tail -f /dev/null
