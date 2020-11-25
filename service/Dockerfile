FROM ubuntu:18.04

RUN apt-get update

# install some extra tools for patching the service
RUN apt-get install -y vim

# setup service files and run
RUN apt-get install -y nginx php-fpm
COPY nginx.conf /etc/nginx/nginx.conf
COPY entrypoint.sh /root
RUN chmod +x /root/entrypoint.sh

ENTRYPOINT /root/entrypoint.sh
