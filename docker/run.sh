#!/bin/sh

su www-data -s /bin/bash -c "php /var/www/bin/console doctrine:schema:create"

touch /var/www/var/log/prod.log
chown -R www-data:www-data  /var/www/var/

/usr/local/bin/supervisord -c "/etc/supervisord.conf"