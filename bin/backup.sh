#!/usr/bin/env bash
# ----------------------------------------------------------------------------
# Mister Co. Reports — backup diario
# ----------------------------------------------------------------------------
# Genera:
#   - mysqldump comprimido (.sql.gz) de la BD configurada en .env
#   - tar.gz de storage/reportes (PDFs históricos)
# Retiene N días locales. Subida a DO Spaces es opcional (ver DEPLOY.md).
#
# Uso:
#   ./bin/backup.sh             # ejecuta el backup
#   ./bin/backup.sh --no-pdfs   # omite PDFs (más rápido, útil para test)
#
# Pensado para correr desde cron como usuario `www-data` o el dueño de la app.
# ----------------------------------------------------------------------------
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="${APP_DIR}/.env"
BACKUP_DIR="${APP_DIR}/storage/backups"
RETENCION_DIAS=30
INCLUIR_PDFS=true

for arg in "$@"; do
    case "$arg" in
        --no-pdfs) INCLUIR_PDFS=false ;;
        --retencion=*) RETENCION_DIAS="${arg#*=}" ;;
        -h|--help)
            sed -n '2,15p' "${BASH_SOURCE[0]}"
            exit 0
            ;;
    esac
done

if [[ ! -f "$ENV_FILE" ]]; then
    echo "ERROR: no se encuentra .env en $ENV_FILE" >&2
    exit 1
fi

# Carga variables del .env de forma segura.
# Soporta KEY=valor, KEY="con espacios", líneas con comentarios y vacías.
load_env() {
    local line key val
    while IFS= read -r line || [[ -n "$line" ]]; do
        # Skip vacías y comentarios
        [[ -z "$line" || "$line" =~ ^[[:space:]]*# ]] && continue
        # Strip prefijo "export "
        line="${line#export }"
        # Debe tener formato KEY=VALUE
        [[ "$line" =~ ^[A-Z_][A-Z0-9_]*= ]] || continue
        key="${line%%=*}"
        val="${line#*=}"
        # Strip comillas alrededor del valor (mantiene espacios internos)
        if [[ "$val" =~ ^\".*\"$ ]] || [[ "$val" =~ ^\'.*\'$ ]]; then
            val="${val:1:${#val}-2}"
        fi
        export "$key=$val"
    done < "$ENV_FILE"
}
load_env

: "${DB_HOST:=127.0.0.1}"
: "${DB_PORT:=3306}"
: "${DB_DATABASE:?DB_DATABASE no definido en .env}"
: "${DB_USERNAME:?DB_USERNAME no definido en .env}"
: "${DB_PASSWORD:=}"

mkdir -p "$BACKUP_DIR"
STAMP="$(date +%Y%m%d-%H%M%S)"

# --- Dump BD ---
DB_FILE="${BACKUP_DIR}/db-${DB_DATABASE}-${STAMP}.sql.gz"
echo "[backup] BD → $(basename "$DB_FILE")"
MYSQL_PWD="$DB_PASSWORD" mysqldump \
    --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USERNAME" \
    --single-transaction --quick --routines --triggers --no-tablespaces \
    "$DB_DATABASE" | gzip -9 > "$DB_FILE"
DB_BYTES=$(stat -f%z "$DB_FILE" 2>/dev/null || stat -c%s "$DB_FILE")
echo "[backup] BD OK (${DB_BYTES} bytes)"

# --- Tar de PDFs ---
if $INCLUIR_PDFS; then
    PDF_DIR="${APP_DIR}/storage/reportes"
    if [[ -d "$PDF_DIR" ]] && [[ -n "$(ls -A "$PDF_DIR" 2>/dev/null | grep -v '^\.gitkeep$')" ]]; then
        PDF_FILE="${BACKUP_DIR}/reportes-${STAMP}.tar.gz"
        echo "[backup] PDFs → $(basename "$PDF_FILE")"
        tar -czf "$PDF_FILE" -C "$APP_DIR/storage" reportes
        PDF_BYTES=$(stat -f%z "$PDF_FILE" 2>/dev/null || stat -c%s "$PDF_FILE")
        echo "[backup] PDFs OK (${PDF_BYTES} bytes)"
    else
        echo "[backup] PDFs: directorio vacío, skip"
    fi
fi

# --- Limpieza retención ---
echo "[backup] Limpiando backups > ${RETENCION_DIAS} días..."
find "$BACKUP_DIR" -maxdepth 1 -type f \( -name 'db-*.sql.gz' -o -name 'reportes-*.tar.gz' \) \
    -mtime "+${RETENCION_DIAS}" -print -delete

# --- Subida opcional a S3-compatible (DO Spaces) ---
# Descomenta y configura si querés mirror remoto.
# Requiere `s3cmd` o `rclone` instalado + credenciales.
#
# if command -v s3cmd >/dev/null; then
#     s3cmd sync "$BACKUP_DIR/" "s3://mister-backups/reports/" --delete-removed
# fi

echo "[backup] OK"
