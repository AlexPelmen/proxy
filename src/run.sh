echo "Wake up, Neo. You obosralsya!"
sh /var/www/html/cert.sh $1

# готовим апач для работы с https
a2enmod ssl
COMMON_NAME=${2:-$1}
cat /var/www/html/apache-vhost.conf.ext | sed s/%%DOMAIN%%/$COMMON_NAME/g >> /etc/apache2/apache2.conf
mkdir /etc/apache2/logs

# запуск всего остального
/run.sh