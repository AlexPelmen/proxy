#создаем самоподписанный сертификат

DOMAIN=$1
COMMON_NAME=${2:-$1}
SUBJECT="/C=CA/ST=None/L=NB/O=None/CN=$COMMON_NAME"
NUM_OF_DAYS=999

cd /etc/ssl

if [ -z "$1" ]
then
  echo "Please supply a subdomain to create a certificate for";
  echo "e.g. mysite.localhost"
  exit;
fi

# Так как ключ мы всегда генерим заранее
KEY_OPT="-key"

echo "step 1"
openssl genrsa -out rootCA.key 2048
echo "step 2"
openssl req -x509 -new -nodes -key rootCA.key -sha256 -days 1024 -out rootCA.pem -subj "$SUBJECT"
echo "step 3"
openssl req -new -newkey rsa:2048 -sha256 -nodes $KEY_OPT rootCA.key -subj "$SUBJECT" -out device.csr

cat /var/www/html/v3.ext | sed s/%%DOMAIN%%/$COMMON_NAME/g > /tmp/__v3.ext
echo "step 4"
openssl x509 -req -in device.csr -CA rootCA.pem -CAkey rootCA.key -CAcreateserial -out device.crt -days $NUM_OF_DAYS -sha256 -extfile /tmp/__v3.ext