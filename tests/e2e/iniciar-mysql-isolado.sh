#!/usr/bin/env bash
set -euo pipefail
raiz="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
execucao="${1:-expansoes_mysql}"
porta="${2:-8007}"
modo="${3:-completo}"
segura="$(printf '%s' "$execucao" | tr -cd 'a-zA-Z0-9_')"
[[ -n "$segura" && "$segura" == "$execucao" ]] || { echo 'Identificador invalido.' >&2; exit 2; }
app="notas-mysql-app-$segura"
mysql="notas-mysql-db-$segura"
database="notas_verificacao_$segura"
url="http://127.0.0.1:$porta"
diretorio="/tmp/notas-verificacao/$segura"
mkdir -p "$diretorio"
chmod 700 "$diretorio"
set -a
source "$raiz/.env"
set +a
docker rm -f "$app" "$mysql" >/dev/null 2>&1 || true
docker run -d --name "$mysql" --label notas.verificacao=true --network notas-rede   --tmpfs /var/lib/mysql:rw,noexec,nosuid,size=512m   -e MYSQL_ROOT_PASSWORD=notas_teste_local -e MYSQL_DATABASE="$database"   -e MYSQL_USER=notas_teste -e MYSQL_PASSWORD=notas_teste_local mysql:8.4 >/dev/null
encerrar() { docker rm -f "$app" "$mysql" >/dev/null 2>&1 || true; }
trap encerrar ERR
for _ in {1..60}; do
  docker exec "$mysql" mysqladmin ping -h 127.0.0.1 -uroot -pnotas_teste_local --silent >/dev/null 2>&1 && break
  sleep .5
done
docker exec "$mysql" mysqladmin ping -h 127.0.0.1 -uroot -pnotas_teste_local --silent >/dev/null
ambiente=(
  -e APP_ENV=testing -e APP_DEBUG=false -e APP_URL="$url" -e APP_KEY="$APP_KEY"
  -e DB_CONNECTION=mysql -e DB_HOST="$mysql" -e DB_PORT=3306 -e DB_DATABASE="$database"
  -e DB_USERNAME=notas_teste -e DB_PASSWORD=notas_teste_local -e DB_URL=
  -e SESSION_DRIVER=cookie -e SESSION_COOKIE="notas_mysql_${segura}"
  -e CACHE_STORE=array -e QUEUE_CONNECTION=sync -e MAIL_MAILER=array -e PHP_CLI_SERVER_WORKERS=4
  -e APP_CONFIG_CACHE=/verificacao/config.php -e APP_ROUTES_CACHE=/verificacao/routes.php
  -e APP_EVENTS_CACHE=/verificacao/events.php -e APP_PACKAGES_CACHE=/verificacao/packages.php
  -e APP_SERVICES_CACHE=/verificacao/services.php
)
volumes=(-v "$raiz:/var/www/html" -v "$diretorio:/verificacao")
docker run --rm --network notas-rede "${volumes[@]}" -w /var/www/html "${ambiente[@]}"   --entrypoint php notas-app:php85 artisan config:cache >/dev/null
docker run --rm -d --name "$app" --label notas.verificacao=true -p "127.0.0.1:$porta:8007"   --network notas-rede "${volumes[@]}" -w /var/www/html/public "${ambiente[@]}"   --entrypoint php notas-app:php85 -S 0.0.0.0:8007 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php >/dev/null
for _ in {1..40}; do
  curl --silent --max-time 1 "$url/_diagnostico/ambiente-verificacao" >/dev/null 2>&1 && break
  sleep .25
done
"$raiz/tests/e2e/preflight-mysql-isolamento.sh" "$url" "$database"
if [[ "$modo" == "completo" ]]; then
  docker exec "$app" php ../artisan migrate --force >/dev/null
fi
cat > "$diretorio/estado.env" <<EOF
APP_CONTAINER=$app
MYSQL_CONTAINER=$mysql
URL=$url
DATABASE=$database
EOF
chmod 600 "$diretorio/estado.env"
trap - ERR
printf 'MySQL descartavel pronto em %s\n' "$url"
