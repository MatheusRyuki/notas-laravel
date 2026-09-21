#!/usr/bin/env bash
set -euo pipefail
execucao="${1:-etapa7}"
segura="$(printf '%s' "$execucao" | tr -cd 'a-zA-Z0-9_-')"
[[ -n "$segura" && "$segura" == "$execucao" ]] || { echo 'Identificador de execução inválido.' >&2; exit 2; }
diretorio="/tmp/notas-verificacao/$segura"
container="notas-verificacao-$segura"
docker rm -f "$container" >/dev/null 2>&1 || true
case "$diretorio" in /tmp/notas-verificacao/*) rm -rf -- "$diretorio" ;; *) exit 3 ;; esac
printf 'Ambiente isolado removido: %s\n' "$execucao"