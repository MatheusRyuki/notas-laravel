#!/usr/bin/env bash
set -euo pipefail
raiz="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
tmp="$(mktemp -d /tmp/notas-preflight-invalido.XXXXXX)"
porta="${1:-18086}"
trap 'kill "${servidor:-}" >/dev/null 2>&1 || true; rm -rf -- "$tmp"' EXIT
cat > "$tmp/resposta.json" <<'JSON'
{"ambiente":"testing","driver_modelos":"mysql","database_modelos":"banco-incompativel","sessao":"cookie","cache":"array","config_cache":true}
JSON
cat > "$tmp/servidor.py" <<'PY'
from http.server import BaseHTTPRequestHandler, HTTPServer
import pathlib, sys
payload = pathlib.Path(sys.argv[1]).read_bytes()
class H(BaseHTTPRequestHandler):
    def do_GET(self):
        self.send_response(200); self.send_header('Content-Type','application/json'); self.end_headers(); self.wfile.write(payload)
    def log_message(self, *_): pass
HTTPServer(('127.0.0.1', int(sys.argv[2])), H).serve_forever()
PY
python3 "$tmp/servidor.py" "$tmp/resposta.json" "$porta" & servidor=$!
for _ in {1..20}; do curl -s "http://127.0.0.1:$porta/" >/dev/null 2>&1 && break; sleep .1; done
if "$raiz/tests/e2e/preflight-isolamento.sh" "http://127.0.0.1:$porta" /verificacao/notas.sqlite; then
  echo 'Falha: o preflight aceitou uma conexão incompatível.' >&2; exit 1
fi
[[ ! -e "$tmp/mutacao-ocorreu" ]] || { echo 'Uma mutação ocorreu antes do aborto.' >&2; exit 1; }
echo 'ABORTO_ANTES_DE_MUTACOES_OK'