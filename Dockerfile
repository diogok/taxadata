FROM diogok/apache

WORKDIR /var/www
CMD ["./run.sh"]

COPY . /var/www
RUN chown www-data.www-data /var/www -Rf

