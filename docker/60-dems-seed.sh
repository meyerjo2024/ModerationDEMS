#!/bin/sh
# Idempotent: creates the first HOD account (and nothing else) unless DEMS_DEMO_DATA=true.
cd /var/www/html && php artisan db:seed --force
