cp /etc/sphinxsearch/sphinx.conf.in /etc/sphinxsearch/sphinx.conf
echo "Prepare sphinx.conf"

sed -i "s/__DB_HOST__/${DB_HOST}/g" /etc/sphinxsearch/sphinx.conf
sed -i "s/__DB_USERNAME__/${DB_USERNAME}/g" /etc/sphinxsearch/sphinx.conf
sed -i "s/__DB_PASSWORD__/${DB_PASSWORD}/g" /etc/sphinxsearch/sphinx.conf
sed -i "s/__DB_DATABASE__/${DB_DATABASE}/g" /etc/sphinxsearch/sphinx.conf
sed -i "s/__DB_PORT__/${DB_PORT}/g" /etc/sphinxsearch/sphinx.conf

echo "Prepare sphinx.conf done"
echo "Start indexer"
indexer --config /etc/sphinxsearch/sphinx.conf --all
echo "Start indexer done"
echo "Start searchd"
searchd -c /etc/sphinxsearch/sphinx.conf

mkdir -p /opt/sphinx/trigger
chmod a+w /opt/sphinx/trigger

echo "Start watcher for trigger"
watch -n 30 "sh /opt/sphinx/reindex.sh"
