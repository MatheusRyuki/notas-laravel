#!/usr/bin/env bash
set -euo pipefail
estado="${1:?Informe o arquivo estado.env.}"
source "$estado"
container="${CONTAINER:-${APP_CONTAINER:-}}"
[[ -n "$container" ]] || exit 2
for migration in   0001_01_01_000000_create_users_table.php   0001_01_01_000001_create_cache_table.php   0001_01_01_000002_create_jobs_table.php   2026_09_20_190000_create_notas_table.php
do
  docker exec "$container" php ../artisan migrate --force --path="database/migrations/$migration" >/dev/null
done
docker exec "$container" php ../artisan tinker --execute='
$agora = now();
$usuario = DB::table("users")->insertGetId(["name" => "Legado", "email" => "legado@example.test", "password" => Hash::make("Senha-ficticia-123"), "created_at" => $agora, "updated_at" => $agora]);
DB::table("notas")->insert(["usuario_id" => $usuario, "titulo" => "Nota preservada", "descricao" => "Antes da expansao", "fixada" => true, "arquivada" => false, "tipo_aparencia" => "cor", "cor" => null, "caminho_imagem" => null, "created_at" => $agora, "updated_at" => $agora]);
' >/dev/null
docker exec "$container" php ../artisan migrate --force >/dev/null
resultado="$(docker exec "$container" php ../artisan tinker --execute='
$nota = DB::table("notas")->where("titulo", "Nota preservada")->first();
echo json_encode(["titulo" => $nota?->titulo, "descricao" => $nota?->descricao, "fixada" => (bool) ($nota?->fixada), "tipo_conteudo" => $nota?->tipo_conteudo, "revisao" => $nota?->revisao, "uuid" => $nota?->uuid_sincronizacao]);
')"
json="$(printf '%s\n' "$resultado" | grep -o '{.*}' | tail -n1)"
python3 - "$json" <<'PY'
import json, re, sys
dados = json.loads(sys.argv[1])
assert dados["titulo"] == "Nota preservada"
assert dados["descricao"] == "Antes da expansao"
assert dados["fixada"] is True
assert dados["tipo_conteudo"] == "texto"
assert dados["revisao"] == 1
assert re.fullmatch(r"[0-9a-f-]{36}", dados["uuid"] or "")
print(json.dumps({"atualizacao_incremental": True, **dados}, ensure_ascii=False))
PY
