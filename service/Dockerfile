FROM ubuntu:20.04

RUN apt-get update

RUN apt-get install -y nginx php-fpm php-sqlite3 sqlite3 cron
COPY nginx.conf /etc/nginx/nginx.conf
COPY entrypoint.sh /root
RUN chmod +x /root/entrypoint.sh

ENV TZ=UTC
RUN ln -sf /usr/share/zoneinfo/$TZ /etc/localtime
RUN echo $TZ > /etc/timezone

RUN echo "SHELL=/bin/bash" > /etc/cron.d/cleaner
RUN echo "* * * * * www-data /service/cleaner /service/www" >> /etc/cron.d/cleaner

WORKDIR /service
ENTRYPOINT /root/entrypoint.sh
