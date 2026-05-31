#!/bin/bash

docker build -t synonim-fpm-img .

docker rm -f synonim-fpm 2>/dev/null

docker run -d \
  --name synonim-fpm \
  --network opengluckru_default \
  -p 127.0.0.1:9084:9000 \
  -v /var/www/synonim:/var/www/synonim \
  --restart unless-stopped \
  synonim-fpm-img
