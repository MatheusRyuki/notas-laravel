#!/usr/bin/env bash
set -euo pipefail

raiz="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
execucao="${1:-etapa7}"
porta="${2:-8006}"
segura="$(printf '%s' "$execucao" | tr -cd 'a-zA-Z0-9_-')"
[[ -n "$segura" && "$segura" == "$execucao" ]] || { echo 'Identificador de execução inválido.' >&2; exit 2; }

diretorio_host="/tmp/notas-verificacao/$segura"
diretorio_container="/verificacao"
database_container="$diretorio_container/notas.sqlite"
container="notas-verificacao-$segura"
url="http://127.0.0.1:$porta"

mkdir -p "$diretorio_host"
chmod 700 "$diretorio_host"
: > "$diretorio_host/notas.sqlite"
chmod 600 "$diretorio_host/notas.sqlite"

docker rm -f "$container" >/dev/null 2>&1 || true

ambiente=(
  -e APP_ENV=testing
  -e APP_DEBUG=false
  -e APP_URL="$url"
  -e DB_CONNECTION=sqlite
  -e DB_DATABASE="$database_container"
  -e DB_URL=
  -e SESSION_DRIVER=cookie
  -e SESSION_COOKIE="notas_verificacao_${segura}"
  -e CACHE_STORE=array
  -e QUEUE_CONNECTION=sync
  -e MAIL_MAILER=array
  -e APP_CONFIG_CACHE="$diretorio_container/config.php"
  -e APP_ROUTES_CACHE="$diretorio_container/routes.php"
  -e APP_EVENTS_CACHE="$diretorio_container/events.php"
  -e APP_PACKAGES_CACHE="$diretorio_container/packages.php"
  -e APP_SERVICES_CACHE="$diretorio_container/services.php"
)
volumes=(-v "$raiz:/var/www/html" -v "$diretorio_host:$diretorio_container")

# O cache é criado com as mesmas variáveis que o processo HTTP consumirá.
docker run --rm --network notas-rede "${volumes[@]}" -w /var/www/html \
  "${ambiente[@]}" --entrypoint php notas-app:php85 artisan config:cache >/dev/null

docker run --rm -d --name "$container" --label notas.verificacao=true \
  -p "127.0.0.1:${porta}:8006" --network notas-rede "${volumes[@]}" \
  -w /var/www/html/public "${ambiente[@]}" --entrypoint php notas-app:php85 \
  -S 0.0.0.0:8006 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php >/dev/null

encerrar() { docker rm -f "$container" >/dev/null 2>&1 || true; }
trap encerrar ERR

for _ in {1..30}; do
  if curl --silent --max-time 1 "$url/_diagnostico/ambiente-verificacao" >/dev/null 2>&1; then break; fi
  sleep 0.2
done

# Barreira obrigatória: migrations, seeds, cadastro e qualquer outra mutação vêm depois dela.
"$raiz/tests/e2e/preflight-isolamento.sh" "$url" "$database_container"

docker exec "$container" php ../artisan migrate --force >/dev/null

cat > "$diretorio_host/estado.env" <<EOF
CONTAINER=$container
URL=$url
DIRETORIO_HOST=$diretorio_host
DATABASE_CONTAINER=$database_container
EOF
chmod 600 "$diretorio_host/estado.env"
trap - ERR
printf 'Ambiente isolado pronto em %s\nEstado: %s\n' "$url" "$diretorio_host/estado.env"