#!/bin/sh

set -eu

cp /etc/sphinxsearch/sphinx.conf.in /etc/sphinxsearch/sphinx.conf
echo "Prepare sphinx.conf"

sed -i "s/__DB_HOST__/${DB_HOST}/g" /etc/sphinxsearch/sphinx.conf
sed -i "s/__DB_USERNAME__/${DB_USERNAME}/g" /etc/sphinxsearch/sphinx.conf
sed -i "s/__DB_PASSWORD__/${DB_PASSWORD}/g" /etc/sphinxsearch/sphinx.conf
sed -i "s/__DB_DATABASE__/${DB_DATABASE}/g" /etc/sphinxsearch/sphinx.conf
sed -i "s/__DB_PORT__/${DB_PORT}/g" /etc/sphinxsearch/sphinx.conf

echo "Prepare sphinx.conf done"
echo "Start indexer"
if ! indexer --config /etc/sphinxsearch/sphinx.conf --all; then
    echo "Initial Sphinx indexing failed; searchd will not start with stale indexes." >&2
    exit 1
fi
echo "Start indexer done"
echo "Start searchd"
searchd -c /etc/sphinxsearch/sphinx.conf

mkdir -p /opt/sphinx/trigger
chmod a+w /opt/sphinx/trigger

echo "Start watcher for trigger"
while true; do
    if ! sh /opt/sphinx/reindex.sh; then
        echo "Sphinx reindex failed; the trigger was kept for the next attempt." >&2
    fi

    sleep 30
done
