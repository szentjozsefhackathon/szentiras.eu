#!/bin/sh

set -eu

FILE="/opt/sphinx/trigger/indexer"

if [ -f "$FILE" ]; then
    echo "Sphinx reindex trigger found; starting indexer."

    if indexer --config /etc/sphinxsearch/sphinx.conf --all --rotate; then
        rm -f "$FILE"
        echo "Sphinx reindex completed; trigger removed."
    else
        echo "Sphinx reindex failed; trigger retained." >&2
        exit 1
    fi
fi
