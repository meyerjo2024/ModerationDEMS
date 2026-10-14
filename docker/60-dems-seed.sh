#!/bin/sh
# Idempotent: creates the first HOD account and imports the subject list.
cd /var/www/html && php artisan db:seed --force
# One-off clean-up of the sample accounts from earlier test deployments: set DEMS_PURGE_DEMO=true for one deploy, then remove it.
if [ "$DEMS_PURGE_DEMO" = "true" ]; then php artisan dems:purge-demo --force; fi
