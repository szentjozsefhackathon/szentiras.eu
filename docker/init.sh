#!/bin/bash

cp /app/.env.example /app/.env

echo '[mysqld]' > /etc/mysql/conf.d/szentiras-hu.cnf
echo 'default_authentication_plugin=mysql_native_password' >> /etc/mysql/conf.d/szentiras-hu.cnf

service mysql start

mysql -e "CREATE USER 'homestead'@'localhost' IDENTIFIED BY 'secret';"
mysql -e "GRANT ALL PRIVILEGES ON *.* TO 'homestead'@'localhost';"
mysql < /app/tmp/database.sql

service postgresql start

su - postgres -c "psql -c \"CREATE USER homestead WITH PASSWORD 'secret';\""
su - postgres -c "createdb --owner=homestead bible"
su - postgres -c "createdb --owner=homestead bible_testing"
su - postgres -c "psql -d bible -f /app/tmp/pg_database.sql"
# This is needed only if we want an other user to own the tables. su - postgres -c "psql -d bible -c \"REASSIGN OWNED BY homestead TO youruser\""
su - postgres -c "psql -d bible -c \"CREATE EXTENSION IF NOT EXISTS vector\""
su - postgres -c "psql -d bible_testing -c \"CREATE EXTENSION IF NOT EXISTS vector\""


nvm install 22
nvm use 22
npm --no-bin-link install

php composer.phar install


php artisan migrate -n
service mysql restart


node_modules/gulp/bin/gulp.js

php artisan view:clear
php artisan config:clear
php artisan route:clear
php artisan cache:clear
php artisan twig:clean

indexer --all
service sphinxsearch start
