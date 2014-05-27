FROM ubuntu:14.04

RUN apt-get update && apt-get upgrade -y
RUN apt-get install apache2 libapache2-mod-php5 php5 php5-cli php5-curl php5-common php5-sqlite -y 
RUN a2enmod rewrite 

RUN sed -i -e 's/memory_limit.*/memory_limit=512M/g' /etc/php5/apache2/php.ini && \
    sed -i -e 's/upload_max_filesize.*/upload_max_filesize=128M/g' /etc/php5/apache2/php.ini && \
    sed -i -e 's/post_max_size.*/post_max_size=128M/g' /etc/php5/apache2/php.ini && \
    sed -i -e 's/display_errors.*/display_erros=On/g' /etc/php5/apache2/php.ini

ADD . /var/www
ADD default.conf /etc/apache2/sites-available/000-default.conf

ENV APACHE_RUN_USER www-data
ENV APACHE_RUN_GROUP www-data
ENV APACHE_LOG_DIR /var/log/apache2
ENV APACHE_LOCK_DIR /var/lock/apache2
ENV APACHE_PID_FILE /var/apache2.pid

EXPOSE 80

CMD ["/usr/sbin/apache2","-D", "FOREGROUND"]

