#!/usr/bin/env bash
set -euo pipefail
raiz="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
execucao="${1:-expansoes}"
estado="/tmp/notas-verificacao/$execucao/estado.env"
source "$estado"
"$raiz/tests/e2e/preflight-isolamento.sh" "$URL" "$DATABASE_CONTAINER"
saida="$(docker exec "$CONTAINER" php ../artisan tinker --execute='
$usuario = App\Models\User::latest("id")->firstOrFail();
$nota = $usuario->notas()->latest("id")->firstOrFail();
$nota->lembretes()->updateOrCreate(
    ["usuario_id" => $usuario->id],
    ["agendado_em" => now()->subMinute(), "fuso_horario" => "America/Sao_Paulo", "enviar_email" => true, "ativo" => true, "suspenso_lixeira" => false, "processado_em" => null]
);
echo "MAIL_RECIPIENT=".$usuario->email;
')"
email="$(printf '%s\n' "$saida" | sed -n 's/.*MAIL_RECIPIENT=//p' | tail -n1)"
[[ "$email" == *@example.test ]] || { echo 'Destinatario ficticio nao identificado.' >&2; exit 3; }
docker exec "$CONTAINER" php ../artisan lembretes:processar
resposta="$(curl --fail --silent --show-error --max-time 10 http://127.0.0.1:8026/api/v1/messages)"
python3 - "$email" "$resposta" <<'PY'
import json, sys
email, bruto = sys.argv[1], sys.argv[2]
dados = json.loads(bruto)
mensagens = dados.get("messages", [])
def enderecos(mensagem):
    valores = mensagem.get("To", [])
    if isinstance(valores, str):
        return [valores]
    return [item.get("Address", "") if isinstance(item, dict) else str(item) for item in valores]
correspondentes = [m for m in mensagens if email in enderecos(m) and str(m.get("Subject", "")).startswith("Lembrete:")]
if not correspondentes:
    print(json.dumps(dados, ensure_ascii=False), file=sys.stderr)
    raise SystemExit("Mensagem ficticia nao encontrada no Mailpit.")
print(json.dumps({"mailpit_confirmado": True, "destinatario": email, "quantidade": len(correspondentes)}, ensure_ascii=False))
PY
