FROM registry.service.opg.digital/opg-php-fpm-71-ppa-1604:0.0.318

RUN groupadd supervisor

RUN apt-get update && apt-get install -y \
    make php7.1-cli php7.1-dev pdftk php7.1-mcrypt php7.1-curl php-pear

# Add application logging config(s)
ADD docker/beaver.d /etc/beaver.d

ADD . /app
RUN mkdir -p /srv/opg-lpa-pdf2/application && \
    mkdir /srv/opg-lpa-pdf2/application/releases && \
    chown -R app:app /srv/opg-lpa-pdf2/application && \
    chmod -R 755 /srv/opg-lpa-pdf2/application && \
    ln -s /app /srv/opg-lpa-pdf2/application/current

# Temporarily download composer, run it to get dependancies, then remove it
RUN cd /tmp && \
    curl -s https://getcomposer.org/installer | php && \
    cd /app && \
    mkdir -p /app/vendor && \
    chown -R app:app /app/vendor && \
    gosu app php /tmp/composer.phar install --prefer-dist --optimize-autoloader --no-suggest --no-interaction --no-scripts && \
    rm /tmp/composer.phar && \
    rm -rf docker README* LICENSE* composer.*

ADD docker/my_init/* /etc/my_init.d/
RUN chmod a+x /etc/my_init.d/*

RUN mkdir /etc/service/opg-lpa-pdf2/
ADD docker/confd /etc/confd

ENV OPG_SERVICE pdf
