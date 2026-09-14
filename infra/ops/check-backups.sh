#!/usr/bin/env bash
# Local disk and backup freshness; pair with an external HTTP monitor.
set -euo pipefail
: "${BACKUP_DIR:?Set BACKUP_DIR}"
df -P / /var/lib/docker "$BACKUP_DIR" | awk '
  NR > 1 && $5 + 0 >= 85 { print "Disk usage above 85%: " $6; failed = 1 }
  END { exit failed }
'
[[ -n "$(find "$BACKUP_DIR" -maxdepth 1 -name last-success -type f -mmin -2160 -print)" ]] || {
  echo "No successful off-site backup in the last 36 hours." >&2
  exit 1
}
