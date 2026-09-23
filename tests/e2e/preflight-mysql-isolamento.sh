#!/usr/bin/env bash
set -euo pipefail
url="${1:?Informe a URL.}"
database="${2:?Informe o banco MySQL esperado.}"
resposta="$(curl --fail --silent --show-error --max-time 5 "$url/_diagnostico/ambiente-verificacao")"
python3 - "$database" "$resposta" <<'PY'
import json, sys
esperado, bruto = sys.argv[1], sys.argv[2]
dados = json.loads(bruto)
regras = {
    "ambiente": "testing", "driver_modelos": "mysql", "database_modelos": esperado,
    "sessao": "cookie", "cache": "array", "config_cache": True,
}
erros = [f"{k}: esperado {v!r}, recebido {dados.get(k)!r}" for k, v in regras.items() if dados.get(k) != v]
if erros:
    print("ISOLAMENTO MYSQL RECUSADO: " + "; ".join(erros), file=sys.stderr)
    raise SystemExit(42)
print(json.dumps({"isolamento_mysql_confirmado": True, **dados}, ensure_ascii=False))
PY
