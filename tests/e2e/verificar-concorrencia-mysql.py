#!/usr/bin/env python3
import concurrent.futures
import json
import re
import sys
import threading
import requests

url = sys.argv[1]
database = sys.argv[2]
preflight = sys.argv[3]
import subprocess
subprocess.run([preflight, url, database], check=True)

def csrf(session, path):
    response = session.get(url + path, timeout=10)
    response.raise_for_status()
    match = re.search(r'name="_token" value="([^"]+)"', response.text)
    if not match:
        match = re.search(r'name="csrf-token" content="([^"]+)"', response.text)
    if not match:
        raise RuntimeError("Token CSRF nao encontrado em " + path)
    return match.group(1)

email = "concorrencia@example.test"
password = "Senha-ficticia-123"
one = requests.Session()
token = csrf(one, "/register")
response = one.post(url + "/register", data={
    "_token": token, "name": "Concorrencia", "email": email,
    "password": password, "password_confirmation": password,
}, timeout=10)
response.raise_for_status()

token = csrf(one, "/")
response = one.post(url + "/notas", data={
    "_token": token, "titulo": "Versao inicial", "descricao": "Conteudo",
    "tipo_conteudo": "texto", "tipo_aparencia": "cor", "cor": "padrao",
}, timeout=10)
response.raise_for_status()
nota = one.get(url + "/sincronizacao/bootstrap", timeout=10).json()["notas"][0]

two = requests.Session()
token = csrf(two, "/login")
response = two.post(url + "/login", data={"_token": token, "email": email, "password": password}, timeout=10)
response.raise_for_status()

tokens = [csrf(one, "/"), csrf(two, "/")]
sessions = [one, two]
barrier = threading.Barrier(2)

def save(index):
    barrier.wait(timeout=5)
    return sessions[index].patch(
        url + f"/notas/{nota['id']}",
        headers={"X-CSRF-TOKEN": tokens[index], "Accept": "application/json"},
        json={
            "titulo": f"Versao concorrente {index + 1}", "descricao": "Conteudo",
            "tipo_conteudo": "texto", "tipo_aparencia": "cor", "cor": "padrao",
            "revisao": nota["revisao"],
        }, timeout=15,
    )

with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
    responses = list(pool.map(save, [0, 1]))
statuses = sorted(response.status_code for response in responses)
if statuses != [200, 409]:
    raise RuntimeError("Resultado concorrente inesperado: " + json.dumps([
        {"status": r.status_code, "body": r.text[:500]} for r in responses
    ]))
final = one.get(url + f"/notas/{nota['id']}", headers={"Accept": "application/json"}, timeout=10).json()
if final["revisao"] != nota["revisao"] + 1:
    raise RuntimeError("A revisao nao avancou exatamente uma vez")
print(json.dumps({
    "preflight": True, "workers_concorrentes": 2, "status": statuses,
    "revisao_inicial": nota["revisao"], "revisao_final": final["revisao"],
    "titulo_final": final["titulo"],
}, ensure_ascii=False))
