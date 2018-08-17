#!/bin/sh

php /var/www/bin/console doctrine:schema:create

/usr/local/bin/supervisord -c "/etc/supervisord.conf"