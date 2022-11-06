FROM ubuntu:20.04

RUN apt update && apt install -y nginx php-fpm php-sqlite3 sqlite3
COPY nginx.conf /etc/nginx/nginx.conf

COPY entrypoint.sh /root
RUN chmod +x /root/entrypoint.sh

ENV TZ=UTC
RUN ln -sf /usr/share/zoneinfo/$TZ /etc/localtime
RUN echo $TZ > /etc/timezone

WORKDIR /service
ENTRYPOINT /root/entrypoint.sh
