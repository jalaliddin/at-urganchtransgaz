#!/usr/bin/env sh
# Keeps web-server/PHP request limits in line with what the app lets users upload:
# up to MAX_FILES files of at most files.max_upload_mb (validation max) in one request.
set -eu
cd "$(dirname "$0")/.."

max_mb=$(grep -A4 "'files.max_upload_mb'" backend/config/settings.php | sed -n "s/.*'max:\([0-9]*\)'.*/\1/p" | head -1)
max_files=5
need=$((max_mb * max_files + 10))

mb() { # "25m" / "26M" -> 25 / 26
  echo "$1" | tr -d 'mM ' ;
}
fail=0
for f in docker/nginx/dev.conf.template docker/nginx/prod.conf.template; do
  v=$(mb "$(sed -n 's/.*client_max_body_size \([0-9]*[mM]\);.*/\1/p' "$f")")
  [ "$v" -ge "$need" ] || { echo "$f: client_max_body_size ${v}m < ${need}m"; fail=1; }
done
post=$(mb "$(sed -n 's/^post_max_size *= *\(.*\)$/\1/p' docker/php/php.ini)")
upload=$(mb "$(sed -n 's/^upload_max_filesize *= *\(.*\)$/\1/p' docker/php/php.ini)")
[ "$post" -ge "$need" ] || { echo "php.ini: post_max_size ${post}M < ${need}M"; fail=1; }
[ "$upload" -ge "$max_mb" ] || { echo "php.ini: upload_max_filesize ${upload}M < ${max_mb}M"; fail=1; }
[ "$fail" -eq 0 ] && echo "upload limits ok (${max_files} x ${max_mb} MB per request)"
exit "$fail"
