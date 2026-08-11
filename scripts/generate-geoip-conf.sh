#!/bin/bash
# Generates GeoIP.conf for the geoipupdate binary from .env values.
# Never commit the generated file -- it contains the real license key.
set -euo pipefail

cd "$(dirname "$0")/.."

ACCOUNT_ID=$(grep '^MAXMIND_ACCOUNT_ID=' .env | cut -d '=' -f2-)
LICENSE_KEY=$(grep '^MAXMIND_LICENSE_KEY=' .env | cut -d '=' -f2-)
EDITION_IDS=$(grep '^MAXMIND_EDITION_IDS=' .env | cut -d '=' -f2- | tr -d '"')
DB_PATH=$(grep '^MAXMIND_DB_PATH=' .env | cut -d '=' -f2-)

mkdir -p "$DB_PATH"

cat > storage/app/GeoIP.conf <<EOF
AccountID ${ACCOUNT_ID}
LicenseKey ${LICENSE_KEY}
EditionIDs ${EDITION_IDS}
DatabaseDirectory ${DB_PATH}
EOF

echo "Generated storage/app/GeoIP.conf"
