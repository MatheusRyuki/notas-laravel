#!/usr/bin/env bash
set -euo pipefail
execucao="${1:-expansoes_mysql}"
segura="$(printf '%s' "$execucao" | tr -cd 'a-zA-Z0-9_')"
[[ -n "$segura" && "$segura" == "$execucao" ]] || exit 2
docker rm -f "notas-mysql-app-$segura" "notas-mysql-db-$segura" >/dev/null 2>&1 || true
diretorio="/tmp/notas-verificacao/$segura"
case "$diretorio" in /tmp/notas-verificacao/*) rm -rf -- "$diretorio" ;; *) exit 3 ;; esac
echo 'MySQL descartavel removido.'
