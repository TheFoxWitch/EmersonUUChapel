#!/usr/bin/env bash
# Copy default themes/plugins from the WordPress image into ./wp-content
# (required because docker-compose bind-mounts wp-content over the container copy)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")" && pwd)"
docker run --rm \
  -v "${ROOT}/wp-content:/mnt" \
  wordpress:6.8-php8.2-apache \
  bash -c 'cp -a /usr/src/wordpress/wp-content/. /mnt/'

echo "Done. Default wp-content seeded at ${ROOT}/wp-content"
