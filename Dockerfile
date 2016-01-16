FROM php:7-cli

RUN docker-php-ext-install pdo pdo_mysql

RUN mkdir -p /usr/src/uploadit

VOLUME /usr/src/uploadit
EXPOSE 8080

WORKDIR /usr/src/uploadit

CMD ["php", "-S", "0.0.0.0:8080", "-t", "web"]
