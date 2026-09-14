#!/usr/bin/env bash
# Failure injection with fake Docker/rsync; never contacts a server.
set -euo pipefail
root=$(pwd)
temporary=$(mktemp -d)
trap 'rm -rf -- "$temporary"' EXIT
mkdir "$temporary/bin" "$temporary/backups"
cp infra/ops/backup.sh "$temporary/backup.sh"
touch "$temporary/.env.deploy" "$temporary/.env.prod" "$temporary/compose.prod.yaml"
cat > "$temporary/bin/docker" <<'SH'
#!/usr/bin/env bash
set -euo pipefail
echo "$*" >> "$CALLS"
case "$*" in
  "inspect "*) echo healthy ;;
  *" ps -q "*) echo container ;;
  *" exec -T postgres "*)
    [[ "$FAILURE" != dump ]]
    echo 'SELECT 1;' ;;
  *" --entrypoint tar "*) tar -czf - --files-from /dev/null ;;
esac
SH
cat > "$temporary/bin/rsync" <<'SH'
#!/usr/bin/env bash
set -euo pipefail
echo rsync >> "$CALLS"
[[ "$FAILURE" != transfer ]]
SH
chmod +x "$temporary/bin/"*
export PATH="$temporary/bin:$PATH" CALLS="$temporary/calls"
export BACKUP_DIR="$temporary/backups" BACKUP_REMOTE=test@example.invalid:/backups
cd "$temporary"

export FAILURE=dump
if bash backup.sh; then echo "Failed pg_dump was accepted." >&2; exit 1; fi
grep -q ' start --wait ' "$CALLS"
if grep -q '^rsync
[[ ! -e "$BACKUP_DIR/last-success" ]]
[[ -z "$(find "$BACKUP_DIR" -name 'backup-*' -print -quit)" ]]

export FAILURE=transfer
if bash backup.sh; then echo "Failed off-site copy was accepted." >&2; exit 1; fi
[[ ! -e "$BACKUP_DIR/last-success" ]]
bundle=$(find "$BACKUP_DIR" -name 'backup-*' -type d -print -quit)
[[ -n "$bundle" ]]
(cd "$bundle" && sha256sum -c SHA256SUMS)

# A pending/failed deployment must block the backup before Docker is touched.
: > "$CALLS"
touch .env.deploy.pending
if bash backup.sh; then echo "Pending deployment was ignored." >&2; exit 1; fi
[[ ! -s "$CALLS" ]]
echo "Backup failure handling passed ($root)."
 "$CALLS"; then echo 'Copied a failed dump.' >&2; exit 1; fi
[[ ! -e "$BACKUP_DIR/last-success" ]]
[[ -z "$(find "$BACKUP_DIR" -name 'backup-*' -print -quit)" ]]

export FAILURE=transfer
if bash backup.sh; then echo "Failed off-site copy was accepted." >&2; exit 1; fi
[[ ! -e "$BACKUP_DIR/last-success" ]]
bundle=$(find "$BACKUP_DIR" -name 'backup-*' -type d -print -quit)
[[ -n "$bundle" ]]
(cd "$bundle" && sha256sum -c SHA256SUMS)

# A pending/failed deployment must block the backup before Docker is touched.
: > "$CALLS"
touch .env.deploy.pending
if bash backup.sh; then echo "Pending deployment was ignored." >&2; exit 1; fi
[[ ! -s "$CALLS" ]]
echo "Backup failure handling passed ($root)."
