#!/usr/bin/env bash
set -euo pipefail

url="${1:?Informe a URL do servidor isolado.}"
database_esperado="${2:?Informe o caminho SQLite esperado no contêiner.}"
resposta="$(curl --fail --silent --show-error --max-time 5 "$url/_diagnostico/ambiente-verificacao")"

python3 - "$database_esperado" "$resposta" <<'PY'
import json, sys
esperado, bruto = sys.argv[1], sys.argv[2]
dados = json.loads(bruto)
erros = []
regras = {
    'ambiente': 'testing',
    'driver_modelos': 'sqlite',
    'database_modelos': esperado,
    'sessao': 'cookie',
    'cache': 'array',
    'config_cache': True,
}
for chave, valor in regras.items():
    if dados.get(chave) != valor:
        erros.append(f"{chave}: esperado {valor!r}, recebido {dados.get(chave)!r}")
if erros:
    print('ISOLAMENTO RECUSADO: ' + '; '.join(erros), file=sys.stderr)
    sys.exit(42)
print(json.dumps({'isolamento_confirmado': True, **dados}, ensure_ascii=False))
PY