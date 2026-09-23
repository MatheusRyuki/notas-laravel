#!/usr/bin/env bash
set -euo pipefail
raiz="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
execucao="${1:-expansoes_mysql}"
segura="$(printf '%s' "$execucao" | tr -cd 'a-zA-Z0-9_')"
estado="/tmp/notas-verificacao/$segura/estado.env"
[[ -f "$estado" ]] || { echo 'Inicie o MySQL descartavel primeiro.' >&2; exit 2; }
source "$estado"
"$raiz/tests/e2e/preflight-mysql-isolamento.sh" "$URL" "$DATABASE"
python3 - "$raiz/phpunit.xml" "/tmp/notas-verificacao/$segura/phpunit.xml" "$DATABASE" <<'PY'
from pathlib import Path
import sys
origem, destino, database = sys.argv[1:]
xml = Path(origem).read_text()
xml = xml.replace('bootstrap="vendor/autoload.php"', 'bootstrap="/var/www/html/vendor/autoload.php"')
xml = xml.replace('<directory>tests/Unit</directory>', '<directory>/var/www/html/tests/Unit</directory>')
xml = xml.replace('<directory>tests/Feature</directory>', '<directory>/var/www/html/tests/Feature</directory>')
xml = xml.replace('<directory>app</directory>', '<directory>/var/www/html/app</directory>')
xml = xml.replace('<env name="DB_CONNECTION" value="sqlite" force="true"/>', '<env name="DB_CONNECTION" value="mysql" force="true"/>')
xml = xml.replace('<env name="DB_DATABASE" value=":memory:" force="true"/>', f'<env name="DB_DATABASE" value="{database}" force="true"/>')
Path(destino).write_text(xml)
PY
docker exec "$APP_CONTAINER" php /var/www/html/vendor/bin/phpunit   -c /verificacao/phpunit.xml /var/www/html/tests/Feature/ExpansoesProdutoTest.php
