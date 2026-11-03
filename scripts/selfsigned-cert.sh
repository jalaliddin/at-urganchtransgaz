#!/usr/bin/env bash
# Self-signed TLS certificate for staging/tests ONLY. Production uses a CA-issued
# certificate (Let's Encrypt or the corporate CA) — see README → TLS.
# Usage: scripts/selfsigned-cert.sh [output_dir] [hostname]
set -euo pipefail
dir=${1:-docker/nginx/certs}
host=${2:-localhost}
mkdir -p "$dir"
openssl req -x509 -nodes -newkey rsa:2048 -days 30 \
  -keyout "$dir/privkey.pem" -out "$dir/fullchain.pem" \
  -subj "/CN=${host}" -addext "subjectAltName=DNS:${host},DNS:localhost,IP:127.0.0.1" 2>/dev/null
chmod 600 "$dir/privkey.pem"
echo "Self-signed certificate for ${host} written to ${dir}"
