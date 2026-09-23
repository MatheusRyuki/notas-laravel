#!/usr/bin/env python3
import base64
import json
import os
import pathlib
import shutil
import socket
import struct
import subprocess
import sys
import time
import urllib.parse
import urllib.request

ROOT = pathlib.Path(__file__).resolve().parents[2]
URL = sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8010"
DB = sys.argv[2] if len(sys.argv) > 2 else "/verificacao/notas.sqlite"
PROFILE = pathlib.Path("/tmp/notas-verificacao/chrome-resiliencia")
PORT = 19225


class CDP:
    def __init__(self, websocket_url):
        parsed = urllib.parse.urlparse(websocket_url)
        self.sock = socket.create_connection((parsed.hostname, parsed.port), timeout=20)
        key = base64.b64encode(os.urandom(16)).decode()
        target = parsed.path + (("?" + parsed.query) if parsed.query else "")
        request = (
            f"GET {target} HTTP/1.1\r\nHost: {parsed.hostname}:{parsed.port}\r\n"
            "Upgrade: websocket\r\nConnection: Upgrade\r\n"
            f"Sec-WebSocket-Key: {key}\r\nSec-WebSocket-Version: 13\r\n\r\n"
        )
        self.sock.sendall(request.encode())
        response = b""
        while b"\r\n\r\n" not in response:
            response += self.sock.recv(4096)
        if b" 101 " not in response.split(b"\r\n", 1)[0]:
            raise RuntimeError(response.decode(errors="replace"))
        self.ident = 0

    def _read(self, size):
        data = b""
        while len(data) < size:
            chunk = self.sock.recv(size - len(data))
            if not chunk:
                raise RuntimeError("Conexao CDP encerrada")
            data += chunk
        return data

    def send(self, data):
        payload = json.dumps(data, separators=(",", ":")).encode()
        mask = os.urandom(4)
        size = len(payload)
        head = bytearray([0x81])
        if size < 126:
            head.append(0x80 | size)
        elif size < 65536:
            head.append(0x80 | 126)
            head.extend(struct.pack("!H", size))
        else:
            head.append(0x80 | 127)
            head.extend(struct.pack("!Q", size))
        head.extend(mask)
        head.extend(bytes(value ^ mask[index % 4] for index, value in enumerate(payload)))
        self.sock.sendall(head)

    def receive(self):
        first = self._read(2)
        opcode = first[0] & 0x0F
        size = first[1] & 0x7F
        masked = first[1] & 0x80
        if size == 126:
            size = struct.unpack("!H", self._read(2))[0]
        elif size == 127:
            size = struct.unpack("!Q", self._read(8))[0]
        mask = self._read(4) if masked else None
        payload = self._read(size)
        if mask:
            payload = bytes(value ^ mask[index % 4] for index, value in enumerate(payload))
        if opcode == 8:
            raise RuntimeError("WebSocket fechado")
        if opcode == 9:
            return self.receive()
        return json.loads(payload)

    def call(self, method, params=None):
        self.ident += 1
        ident = self.ident
        self.send({"id": ident, "method": method, "params": params or {}})
        while True:
            message = self.receive()
            if message.get("id") == ident:
                if "error" in message:
                    raise RuntimeError(message["error"])
                return message.get("result", {})

    def evaluate(self, expression):
        result = self.call("Runtime.evaluate", {
            "expression": expression,
            "awaitPromise": True,
            "returnByValue": True,
        })
        if result.get("exceptionDetails"):
            raise RuntimeError(json.dumps(result["exceptionDetails"]))
        return result.get("result", {}).get("value")

    def navigate(self, path):
        self.call("Page.navigate", {"url": path if path.startswith("http") else URL + path})
        wait(self, "document.readyState === 'complete'")

    def online(self, active):
        self.call("Network.emulateNetworkConditions", {
            "offline": not active,
            "latency": 20 if active else 0,
            "downloadThroughput": 1000000 if active else 0,
            "uploadThroughput": 1000000 if active else 0,
            "connectionType": "wifi" if active else "none",
        })


def wait(page, expression, timeout=12):
    end = time.time() + timeout
    last = None
    while time.time() < end:
        try:
            last = page.evaluate(expression)
            if last:
                return last
        except Exception as error:
            last = str(error)
        time.sleep(0.12)
    raise RuntimeError(f"Tempo esgotado: {expression}; ultimo={last!r}")


def create_page(browser, context):
    target = browser.call("Target.createTarget", {
        "url": "about:blank",
        "browserContextId": context,
    })["targetId"]
    for _ in range(60):
        pages = json.load(urllib.request.urlopen(f"http://127.0.0.1:{PORT}/json", timeout=2))
        found = next((item for item in pages if item.get("id") == target), None)
        if found:
            page = CDP(found["webSocketDebuggerUrl"])
            page.call("Page.enable")
            page.call("Runtime.enable")
            page.call("Network.enable")
            return page
        time.sleep(0.1)
    raise RuntimeError("Alvo do navegador nao apareceu")


def register(page, name, email, password):
    page.navigate("/register")
    result = page.evaluate(f"""(async () => {{
        const body = new URLSearchParams({{
            _token: document.querySelector('input[name="_token"]').value,
            name: {json.dumps(name)}, email: {json.dumps(email)},
            password: {json.dumps(password)}, password_confirmation: {json.dumps(password)}
        }});
        const response = await fetch('/register', {{method:'POST', body}});
        return {{status: response.status, url: response.url}};
    }})()""")
    if result["status"] != 200 or not result["url"].endswith("/"):
        raise RuntimeError("Cadastro falhou: " + json.dumps(result))
    page.navigate("/")


def login(page, email, password):
    page.navigate("/login")
    result = page.evaluate(f"""(async () => {{
        const body = new URLSearchParams({{
            _token: document.querySelector('input[name="_token"]').value,
            email: {json.dumps(email)}, password: {json.dumps(password)}
        }});
        const response = await fetch('/login', {{method:'POST', body}});
        return {{status: response.status, url: response.url}};
    }})()""")
    if result["status"] != 200 or not result["url"].endswith("/"):
        raise RuntimeError("Login falhou: " + json.dumps(result))
    page.navigate("/")


def form(page, path, fields):
    payload = json.dumps(fields, ensure_ascii=False)
    result = page.evaluate(f"""(async () => {{
        const token = document.querySelector('meta[name="csrf-token"]')?.content
            || document.querySelector('input[name="_token"]')?.value;
        const body = new URLSearchParams([['_token', token], ...{payload}]);
        const response = await fetch({json.dumps(path)}, {{
            method:'POST', credentials:'same-origin', headers:{{Accept:'text/html'}}, body
        }});
        return {{status: response.status, url: response.url, text: await response.text()}};
    }})()""")
    if result["status"] >= 400:
        raise RuntimeError(f"{path} falhou: {result['status']} {result['text'][:300]}")
    return result


def bootstrap(page):
    return page.evaluate("(async()=>await (await fetch('/sincronizacao/bootstrap',{headers:{Accept:'application/json'}})).json())()")


def create_note(owner, title):
    owner.navigate("/")
    form(owner, "/notas", [
        ["titulo", title], ["descricao", "Conteúdo fictício"],
        ["tipo_conteudo", "texto"], ["tipo_aparencia", "cor"], ["cor", "padrao"],
    ])
    data = bootstrap(owner)
    return next(note for note in data["notas"] if note["titulo"] == title)


def share_note(owner, editor, editor_email, title):
    note = create_note(owner, title)
    form(owner, f"/notas/{note['id']}/convites", [["email", editor_email], ["papel", "editor"]])
    editor.navigate("/compartilhamentos")
    action = editor.evaluate("""(() => {
        const form = [...document.querySelectorAll('form')].find((item) =>
            item.action.includes('/convites/') && item.querySelector('button[value="aceitar"]'));
        return form?.action || null;
    })()""")
    if not action:
        raise RuntimeError("Convite nao apareceu")
    form(editor, urllib.parse.urlparse(action).path, [["_method", "PATCH"], ["resposta", "aceitar"]])
    editor.navigate("/")
    wait(editor, f"document.body.innerText.includes({json.dumps(title)})")
    return note


def open_offline(page):
    page.online(True)
    page.navigate("/offline.html")
    wait(page, "document.querySelector('#nova') && !document.querySelector('#nova').disabled")


def edit_offline(page, current_title, new_title):
    open_offline(page)
    page.online(False)
    page.evaluate(f"""(() => {{
        const card = [...document.querySelectorAll('#notas .nota')].find(
            item => item.querySelector('h3')?.textContent === {json.dumps(current_title)}
        );
        if (!card) throw new Error('Nota local nao encontrada');
        card.querySelector('button:not([disabled])').click();
        document.querySelector('#titulo').value = {json.dumps(new_title)};
        document.querySelector('#editor').requestSubmit();
    }})()""")
    wait(page, f"document.body.innerText.includes({json.dumps(new_title)})")


def db_state(page):
    return page.evaluate("""(async () => {
        const db = await new Promise((resolve, reject) => {
            const request = indexedDB.open('notas-offline-v1', 1);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
        const tx = db.transaction(['meta','notas','operacoes','conflitos'], 'readonly');
        const get = request => new Promise((resolve, reject) => {
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
        const result = {
            conta: await get(tx.objectStore('meta').get('conta_atual')),
            notas: await get(tx.objectStore('notas').getAll()),
            operacoes: await get(tx.objectStore('operacoes').getAll()),
            conflitos: await get(tx.objectStore('conflitos').getAll()),
        };
        db.close();
        return result;
    })()""")


def wait_operations_empty(page, timeout=12):
    end = time.time() + timeout
    while time.time() < end:
        if not db_state(page)["operacoes"]:
            return
        time.sleep(0.12)
    raise RuntimeError("A fila local nao foi reconciliada no prazo")


def sync(page):
    page.evaluate("document.querySelector('#sincronizar').click()")
    wait(page, "!document.querySelector('#estado').textContent.includes('Sincronizando')")


subprocess.run([str(ROOT / "tests/e2e/preflight-isolamento.sh"), URL, DB], check=True)
shutil.rmtree(PROFILE, ignore_errors=True)
PROFILE.mkdir(parents=True, exist_ok=True)
chrome = subprocess.Popen([
    "/usr/bin/google-chrome", "--headless=new", "--no-sandbox", "--disable-gpu",
    "--disable-dev-shm-usage", f"--remote-debugging-port={PORT}",
    f"--user-data-dir={PROFILE}", "--window-size=1280,900", "about:blank",
], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)

try:
    for _ in range(80):
        try:
            version = json.load(urllib.request.urlopen(f"http://127.0.0.1:{PORT}/json/version", timeout=1))
            break
        except Exception:
            time.sleep(0.1)
    else:
        raise RuntimeError("Chrome CDP nao iniciou")

    browser = CDP(version["webSocketDebuggerUrl"])
    owner_context = browser.call("Target.createBrowserContext")["browserContextId"]
    editor_context = browser.call("Target.createBrowserContext")["browserContextId"]
    owner = create_page(browser, owner_context)
    editor = create_page(browser, editor_context)
    other_tab = create_page(browser, editor_context)

    stamp = int(time.time())
    password = "Senha-ficticia-123"
    owner_email = f"dono-resiliencia-{stamp}@example.test"
    editor_email = f"editor-resiliencia-{stamp}@example.test"
    account_b_email = f"conta-b-resiliencia-{stamp}@example.test"
    register(owner, "Dono fictício", owner_email, password)
    register(editor, "Conta A fictícia", editor_email, password)
    owner_id = bootstrap(owner)["conta_id"]
    editor_id = bootstrap(editor)["conta_id"]

    # Mudança editor -> leitor enquanto a cópia está offline.
    readonly_note = share_note(owner, editor, editor_email, "Permissão offline")
    edit_offline(editor, "Permissão offline", "Alteração local recusada")
    form(owner, f"/notas/{readonly_note['id']}/participantes/{editor_id}", [["_method", "PATCH"], ["papel", "leitor"]])
    editor.online(True)
    wait(editor, "document.querySelector('#estado').textContent.includes('somente leitura')")
    text = editor.evaluate("document.body.innerText")
    state = db_state(editor)
    local = next(note for note in state["notas"] if note["uuid"] == readonly_note["uuid"])
    if "Permissão offline" not in text or local["titulo"] != "Permissão offline" or local["pode_editar"] is not False:
        raise RuntimeError("Mudanca para leitor nao reconciliou interface e IndexedDB")
    if any(op.get("nota_uuid") == readonly_note["uuid"] for op in state["operacoes"]):
        raise RuntimeError("Pendencia recusada permaneceu na fila")

    # Revogação enquanto offline.
    revoked_note = share_note(owner, editor, editor_email, "Revogar offline")
    edit_offline(editor, "Revogar offline", "Revogada local")
    form(owner, f"/notas/{revoked_note['id']}/participantes/{editor_id}", [["_method", "DELETE"]])
    editor.online(True)
    wait(editor, "document.querySelector('#estado').textContent.includes('concluída')")
    state = db_state(editor)
    if any(note["uuid"] == revoked_note["uuid"] for note in state["notas"]):
        raise RuntimeError("Nota revogada permaneceu no armazenamento local")
    if any(op.get("nota_uuid") == revoked_note["uuid"] for op in state["operacoes"]):
        raise RuntimeError("Operacao revogada permaneceu na fila")

    # Remoção da nota pelo proprietário enquanto offline.
    removed_note = share_note(owner, editor, editor_email, "Remover offline")
    edit_offline(editor, "Remover offline", "Removida local")
    form(owner, f"/notas/{removed_note['id']}/lixeira", [["_method", "DELETE"]])
    editor.online(True)
    wait(editor, "document.querySelector('#estado').textContent.includes('concluída')")
    state = db_state(editor)
    if any(note["uuid"] == removed_note["uuid"] for note in state["notas"]):
        raise RuntimeError("Nota removida reapareceu no armazenamento local")

    # Conflito apresentado, versões preservadas e resolução explícita pela interface offline.
    conflict_note = share_note(owner, editor, editor_email, "Conflito inicial")
    edit_offline(editor, "Conflito inicial", "Minha versão preservada")
    fresh = next(note for note in bootstrap(owner)["notas"] if note["id"] == conflict_note["id"])
    form(owner, f"/notas/{conflict_note['id']}", [
        ["_method", "PATCH"], ["titulo", "Versão atual do servidor"], ["descricao", "Conteúdo fictício"],
        ["tipo_conteudo", "texto"], ["tipo_aparencia", "cor"], ["cor", "padrao"],
        ["revisao", str(fresh["revisao"])],
    ])
    editor.online(True)
    wait(editor, "document.querySelectorAll('#conflitos .conflito').length === 1")
    conflict_text = editor.evaluate("document.querySelector('#conflitos').innerText")
    if not all(value in conflict_text for value in ["Sua versão", "Minha versão preservada", "Versão atual", "Versão atual do servidor"]):
        raise RuntimeError("Comparacao do conflito nao mostrou as duas versoes")
    preserved = db_state(editor)
    if not any(note["titulo"] == "Minha versão preservada" for note in preserved["notas"]):
        raise RuntimeError("Texto local nao permaneceu ate a decisao")
    editor.evaluate("""[...document.querySelectorAll('#conflitos button')]
        .find(button => button.textContent.includes('Tentar minha versão')).click()""")
    wait(editor, "document.querySelectorAll('#conflitos .conflito').length === 0")
    wait(editor, """(async()=>{const r=await fetch('/sincronizacao/bootstrap');const d=await r.json();
        return d.notas.some(n=>n.titulo==='Minha versão preservada')})()""")

    # Resposta perdida simulada no cliente: o servidor grava, a fila local só é reconciliada na repetição.
    open_offline(editor)
    editor.evaluate("""(() => {
        document.querySelector('#nova').click();
        document.querySelector('#titulo').value = 'Resposta perdida';
        document.querySelector('#descricao').value = 'Operação idempotente';
        document.querySelector('#editor').requestSubmit();
    })()""")
    wait(editor, "document.body.innerText.includes('Resposta perdida')")
    lost = editor.evaluate("""(async () => {
        const db = await new Promise((resolve,reject)=>{const r=indexedDB.open('notas-offline-v1',1);r.onsuccess=()=>resolve(r.result);r.onerror=()=>reject(r.error)});
        const tx=db.transaction(['meta','operacoes'],'readonly');
        const get=r=>new Promise((resolve,reject)=>{r.onsuccess=()=>resolve(r.result);r.onerror=()=>reject(r.error)});
        const conta=await get(tx.objectStore('meta').get('conta_atual'));
        const ops=(await get(tx.objectStore('operacoes').getAll())).filter(op=>op.conta_id===conta);
        db.close();
        const html=await (await fetch('/')).text();
        const token=html.match(/name="csrf-token" content="([^"]+)"/)[1];
        const response=await fetch('/sincronizacao',{method:'POST',credentials:'same-origin',
            headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':token},
            body:JSON.stringify({conta_id:conta,operacoes:ops})});
        return {status:response.status, quantidade:ops.length};
    })()""")
    if lost["status"] != 200 or lost["quantidade"] < 1:
        raise RuntimeError("Primeira gravacao da simulacao de resposta perdida falhou")
    if not any(op["acao"] == "criar" for op in db_state(editor)["operacoes"]):
        raise RuntimeError("Fila local foi removida apesar da resposta descartada")
    sync(editor)
    wait_operations_empty(editor)
    notes_after_replay = bootstrap(editor)["notas"]
    if sum(note["titulo"] == "Resposta perdida" for note in notes_after_replay) != 1:
        raise RuntimeError("Repeticao da operacao duplicou ou perdeu a nota")
    if db_state(editor)["operacoes"]:
        raise RuntimeError("Fila nao foi reconciliada apos repeticao idempotente")

    # Duas abas, confirmação explícita, resposta atrasada e troca para a conta B.
    other_tab.navigate("/")
    wait(other_tab, """document.querySelector('form[action$="/logout"]')""")
    open_offline(editor)
    editor.evaluate("""(() => {
        document.querySelector('#nova').click();
        document.querySelector('#titulo').value = 'Pendente da conta A';
        document.querySelector('#editor').requestSubmit();
    })()""")
    wait(editor, "document.body.innerText.includes('Pendente da conta A')")
    other_tab.evaluate("""(() => {
        window.__confirmacoes = 0;
        window.confirm = () => { window.__confirmacoes++; return false; };
        document.querySelector('form[action$="/logout"]').requestSubmit();
    })()""")
    wait(other_tab, "window.__confirmacoes === 1")
    if not db_state(editor)["operacoes"]:
        raise RuntimeError("Cancelar o logout descartou pendencias")

    editor.evaluate("""(() => {
        const original = window.fetch.bind(window);
        window.fetch = (...args) => {
            const url = String(args[0]);
            const method = String(args[1]?.method || 'GET').toUpperCase();
            const response = original(...args);
            if (url.endsWith('/sincronizacao') && method === 'POST') {
                return response.then(value => new Promise(resolve => {
                    window.__liberarRespostaSincronizacao = () => resolve(value);
                }));
            }
            return response;
        };
        document.querySelector('#sincronizar').click();
    })()""")
    wait(editor, "typeof window.__liberarRespostaSincronizacao === 'function'")

    other_tab.evaluate("""(() => {
        window.confirm = () => true;
        document.querySelector('form[action$="/logout"]').requestSubmit();
    })()""")
    wait(other_tab, "location.pathname === '/login'")
    wait(editor, "document.querySelector('#estado').textContent.includes('sessão foi encerrada')")
    editor.evaluate("window.__liberarRespostaSincronizacao()")
    time.sleep(0.8)
    cleared = db_state(editor)
    if cleared.get("conta") is not None or cleared["notas"] or cleared["operacoes"] or cleared["conflitos"]:
        raise RuntimeError("Resposta atrasada reinseriu dados da conta A")
    if "Pendente da conta A" in editor.evaluate("document.body.innerText"):
        raise RuntimeError("Outra aba continuou exibindo dados da conta A")

    register(other_tab, "Conta B fictícia", account_b_email, password)
    wait(other_tab, "location.pathname === '/'")
    wait(other_tab, """(async()=>{const db=await new Promise((resolve,reject)=>{const r=indexedDB.open('notas-offline-v1',1);r.onsuccess=()=>resolve(r.result);r.onerror=()=>reject(r.error)});
        const tx=db.transaction('meta','readonly');const r=tx.objectStore('meta').get('conta_atual');
        const value=await new Promise((resolve,reject)=>{r.onsuccess=()=>resolve(r.result);r.onerror=()=>reject(r.error)});db.close();return !!value})()""")
    b_state = db_state(other_tab)
    b_id = bootstrap(other_tab)["conta_id"]
    if b_state["conta"] != b_id or any(note.get("conta_id") == editor_id for note in b_state["notas"]):
        raise RuntimeError("Troca de conta misturou dados locais")
    if any(op.get("conta_id") == editor_id for op in b_state["operacoes"]):
        raise RuntimeError("Conta B recebeu operacoes pendentes da conta A")
    if bootstrap(editor).get("conta_id") != b_id:
        raise RuntimeError("A outra aba nao compartilhou a nova sessao sem expor dados antigos")

    print(json.dumps({
        "preflight_http": True,
        "resposta_perdida": "simulada no cliente com repeticao real no servidor",
        "mudanca_editor_leitor": True,
        "revogacao": True,
        "remocao": True,
        "conflito_interface": True,
        "logout_confirmacao": True,
        "resposta_atrasada_ignorada": True,
        "outra_aba_limpa": True,
        "troca_conta_isolada": True,
        "conta_a": editor_id,
        "conta_b": b_id,
        "dono": owner_id,
    }, ensure_ascii=False))
finally:
    chrome.terminate()
    try:
        chrome.wait(timeout=5)
    except subprocess.TimeoutExpired:
        chrome.kill()
