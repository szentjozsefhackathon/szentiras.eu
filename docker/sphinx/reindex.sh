#!/bin/bash
FILE="/opt/sphinx/trigger/indexer"

if [ -f "$FILE" ]; then
    indexer --config /etc/sphinxsearch/sphinx.conf --all --rotate
    rm "$FILE"
fi
