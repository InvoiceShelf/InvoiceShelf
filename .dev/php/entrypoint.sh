#!/bin/bash

echo "############################################"
echo "### InvoiceShelf Development Environment ###"
echo "############################################"

chown -R ${UID}:${GID} /home/invoiceshelf/app
chmod +x /home/invoiceshelf/app/artisan

cd /home/invoiceshelf/app && php artisan storage:link

exec $@
