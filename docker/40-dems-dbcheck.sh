#!/bin/sh
# Diagnostic only (never fails the boot): prints which database the app is trying to reach and the real error, password masked.
cd /var/www/html || exit 0
echo "──── DEMS database check ────"
php artisan tinker --execute='
$c = DB::connection()->getConfig();
echo "driver=".($c["driver"] ?? "?")." host=".($c["host"] ?? "?")." port=".($c["port"] ?? "?")." database=".($c["database"] ?? "?")." user=".($c["username"] ?? "?")." sslmode=".($c["sslmode"] ?? "?").PHP_EOL;
echo "DB_URL set: ".(env("DB_URL") ? "yes" : "NO — add it in Render > Environment").PHP_EOL;
try { DB::select("select 1"); echo "RESULT: CONNECTED OK".PHP_EOL; }
catch (Throwable $e) { echo "RESULT: FAILED — ".preg_replace("/(password=)\S+/i", "$1***", substr($e->getMessage(), 0, 400)).PHP_EOL; }
' 2>&1 | tail -5
echo "─────────────────────────────"
exit 0
