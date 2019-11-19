#!/bin/sh

su www-data -s /bin/bash -c "php /var/www/bin/console doctrine:schema:update --force"

touch /var/www/var/log/prod.log
chown -R www-data:www-data  /var/www/var/

/usr/bin/supervisord -c "/etc/supervisord.conf"