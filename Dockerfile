FROM cncflora/apache

RUN apt-get update && \
    apt-get install supervisor -y && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

RUN mkdir /var/log/supervisord
ADD supervisor.conf /etc/supervisor/conf.d/base.conf
CMD ["supervisord"]

ADD . /var/www
RUN chown www-data.www-data /var/www -Rf

