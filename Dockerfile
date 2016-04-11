FROM diogok/php7

WORKDIR /var/www
CMD ["./run.sh"]

ADD . /var/www
RUN chown www-data.www-data /var/www -Rf

