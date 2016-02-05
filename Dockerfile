FROM diogok/php7

WORKDIR /var/www
CMD ["./run.sh"]

RUN apt-get update && apt-get install curl -y

COPY . /var/www
RUN chown www-data.www-data /var/www -Rf

