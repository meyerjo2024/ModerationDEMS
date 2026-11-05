#!/bin/sh
# Idempotent: creates the first HOD account, imports the subject list and clears any leftover sample (…@example.edu) accounts.
cd /var/www/html && php artisan db:seed --force
php artisan dems:purge-demo --force
