#!/usr/bin/env bash
# End-to-end check of the SPA auth flow through nginx: CSRF cookie → login → me → logout.
#   SMOKE_USERNAME=... SMOKE_PASSWORD=... scripts/smoke.sh [base_url]   (default http://localhost:8095)
# Set SMOKE_INSECURE=1 to accept a self-signed certificate.
set -euo pipefail
base=${1:-http://localhost:8095}
: "${SMOKE_USERNAME:?set SMOKE_USERNAME}" "${SMOKE_PASSWORD:?set SMOKE_PASSWORD}"
jar=$(mktemp)
trap 'rm -f "$jar"' EXIT
curl_opts=(-s -c "$jar" -b "$jar" -H "Origin: $base" -H "Referer: $base/" -H 'Accept: application/json')
[ "${SMOKE_INSECURE:-0}" = "1" ] && curl_opts+=(-k)

fail() { echo "SMOKE FAIL: $*" >&2; exit 1; }

code=$(curl "${curl_opts[@]}" -o /dev/null -w '%{http_code}' "$base/api/v1/health")
[ "$code" = "200" ] || fail "health returned $code"

code=$(curl "${curl_opts[@]}" -o /dev/null -w '%{http_code}' "$base/sanctum/csrf-cookie")
[ "$code" = "204" ] || fail "csrf-cookie returned $code"
xsrf=$(awk '$6=="XSRF-TOKEN"{print $7}' "$jar" | python3 -c 'import sys,urllib.parse;print(urllib.parse.unquote(sys.stdin.read().strip()))')
[ -n "$xsrf" ] || fail "no XSRF-TOKEN cookie"

body=$(python3 -c 'import json,os;print(json.dumps({"username":os.environ["SMOKE_USERNAME"],"password":os.environ["SMOKE_PASSWORD"]}))')
login=$(curl "${curl_opts[@]}" -H "X-XSRF-TOKEN: $xsrf" -H 'Content-Type: application/json' -d "$body" "$base/api/v1/auth/login")
echo "$login" | python3 -c 'import sys,json;d=json.load(sys.stdin);assert d.get("data",{}).get("username"),d' >/dev/null 2>&1 || fail "login: $login"

me=$(curl "${curl_opts[@]}" "$base/api/v1/auth/me")
user=$(echo "$me" | python3 -c 'import sys,json;print(json.load(sys.stdin)["data"]["username"])') || fail "me: $me"

xsrf=$(awk '$6=="XSRF-TOKEN"{print $7}' "$jar" | python3 -c 'import sys,urllib.parse;print(urllib.parse.unquote(sys.stdin.read().strip()))')
code=$(curl "${curl_opts[@]}" -o /dev/null -w '%{http_code}' -X POST -H "X-XSRF-TOKEN: $xsrf" "$base/api/v1/auth/logout")
[ "$code" = "200" ] || fail "logout returned $code"

code=$(curl "${curl_opts[@]}" -o /dev/null -w '%{http_code}' "$base/api/v1/auth/me")
[ "$code" = "401" ] || fail "session still valid after logout ($code)"

echo "SMOKE OK: $user signed in and out at $base"
