#!/bin/sh
set -eu

php /app/docker/validate-environment.php
exec frankenphp run --config /etc/frankenphp/Caddyfile
