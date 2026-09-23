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
URL = sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8006"
DB = sys.argv[2] if len(sys.argv) > 2 else "/verificacao/notas.sqlite"
OUT = ROOT / "docs" / "capturas"
OUT.mkdir(parents=True, exist_ok=True)
PROFILE = pathlib.Path("/tmp/notas-verificacao/chrome-expansoes")
shutil.rmtree(PROFILE, ignore_errors=True)
PROFILE.mkdir(parents=True, exist_ok=True)
PORT = 19224

subprocess.run([str(ROOT / "tests/e2e/preflight-isolamento.sh"), URL, DB], check=True)
chrome = subprocess.Popen([
    "/usr/bin/google-chrome", "--headless=new", "--no-sandbox", "--disable-gpu",
    "--disable-dev-shm-usage", f"--remote-debugging-port={PORT}",
    f"--user-data-dir={PROFILE}", "--window-size=1440,1000", "about:blank",
], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)

class CDP:
    def __init__(self, websocket_url):
        parsed = urllib.parse.urlparse(websocket_url)
        self.sock = socket.create_connection((parsed.hostname, parsed.port), timeout=10)
        key = base64.b64encode(os.urandom(16)).decode()
        target = parsed.path + (("?" + parsed.query) if parsed.query else "")
        request = (
            f"GET {target} HTTP/1.1\r\n"
            f"Host: {parsed.hostname}:{parsed.port}\r\n"
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

    def _read(self, size):
        data = b""
        while len(data) < size:
            chunk = self.sock.recv(size - len(data))
            if not chunk:
                raise RuntimeError("Conexao CDP encerrada")
            data += chunk
        return data

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
            "expression": expression, "awaitPromise": True, "returnByValue": True,
        })
        if result.get("exceptionDetails"):
            raise RuntimeError(result["exceptionDetails"])
        return result.get("result", {}).get("value")

    def navigate(self, path):
        self.call("Page.navigate", {"url": URL + path})
        for _ in range(100):
            time.sleep(0.1)
            if self.evaluate("document.readyState") == "complete":
                return
        raise RuntimeError("Tempo esgotado ao navegar para " + path)

    def screenshot(self, name):
        data = self.call("Page.captureScreenshot", {"format": "png", "captureBeyondViewport": False})["data"]
        (OUT / name).write_bytes(base64.b64decode(data))

try:
    for _ in range(80):
        try:
            pages = json.load(urllib.request.urlopen(f"http://127.0.0.1:{PORT}/json", timeout=1))
            break
        except Exception:
            time.sleep(0.1)
    else:
        raise RuntimeError("Chrome CDP nao iniciou")

    page = next(page for page in pages if page.get("type") == "page")
    cdp = CDP(page["webSocketDebuggerUrl"])
    cdp.call("Page.enable")
    cdp.call("Runtime.enable")
    cdp.call("Network.enable")

    email = f"e2e-expansoes-{int(time.time())}@example.test"
    password = "Senha-ficticia-123"
    cdp.navigate("/register")
    registered = cdp.evaluate(f"""(async () => {{
        const token = document.querySelector('input[name="_token"]').value;
        const body = new URLSearchParams({{
            _token: token, name: 'Pessoa de teste', email: {json.dumps(email)},
            password: {json.dumps(password)}, password_confirmation: {json.dumps(password)}
        }});
        const response = await fetch('/register', {{method:'POST', body}});
        return {{status: response.status, url: response.url}};
    }})()""")
    if registered["status"] != 200 or not registered["url"].endswith("/"):
        raise RuntimeError("Cadastro isolado falhou: " + json.dumps(registered))

    cdp.navigate("/")
    def post(path, entries, method="POST"):
        pairs = json.dumps(entries)
        result = cdp.evaluate(f"""(async () => {{
            const token = document.querySelector('meta[name="csrf-token"]').content;
            const body = new URLSearchParams({pairs});
            const response = await fetch({json.dumps(path)}, {{
                method: {json.dumps(method)}, credentials: 'same-origin',
                headers: {{'X-CSRF-TOKEN': token, 'Accept': 'text/html'}}, body
            }});
            return {{status: response.status, url: response.url}};
        }})()""")
        if result["status"] >= 400:
            raise RuntimeError(f"{path} falhou: {result}")
        return result

    post("/etiquetas", [["_token", ""], ["nome", "Projeto"]])
    post("/notas", [
        ["titulo", "Planejamento colaborativo"], ["descricao", "Conteudo ficticio para validar a interface."],
        ["tipo_conteudo", "texto"], ["tipo_aparencia", "imagem"], ["fundo", "folhas"], ["cor", "padrao"],
    ])
    post("/notas", [
        ["titulo", "Lista de entrega"], ["descricao", "Itens ordenados e acessiveis"],
        ["tipo_conteudo", "lista"], ["tipo_aparencia", "cor"], ["cor", "menta"],
        ["itens[0][texto]", "Revisar requisitos"], ["itens[0][concluido]", "1"],
        ["itens[1][texto]", "Executar verificacoes"], ["itens[1][concluido]", "0"],
    ])
    cdp.navigate("/")
    time.sleep(1.5)
    bootstrap = cdp.evaluate("(async()=>await (await fetch('/sincronizacao/bootstrap')).json())()")
    if len(bootstrap["notas"]) != 2:
        raise RuntimeError("Notas de teste nao foram criadas")

    cdp.screenshot("expansoes-notas-desktop.png")

    cdp.call("Emulation.setDeviceMetricsOverride", {
        "width": 390, "height": 844, "deviceScaleFactor": 1, "mobile": True,
    })
    cdp.navigate("/?ordem=titulo")
    time.sleep(0.5)
    horizontal = cdp.evaluate("document.documentElement.scrollWidth > document.documentElement.clientWidth")
    if horizontal:
        raise RuntimeError("Rolagem horizontal detectada em 390px")
    cdp.screenshot("expansoes-notas-celular.png")

    cdp.call("Emulation.clearDeviceMetricsOverride")
    cdp.navigate("/")
    post(f"/notas/{bootstrap['notas'][0]['id']}/lembrete", [
        ["agendado_local", "2099-12-31T18:00"], ["fuso_horario", "America/Sao_Paulo"],
    ])
    cdp.navigate("/lembretes")
    time.sleep(0.5)
    cdp.screenshot("expansoes-lembretes-desktop.png")

    cdp.navigate("/")
    time.sleep(1)
    controlado = cdp.evaluate("(async()=>{await navigator.serviceWorker.ready; return !!navigator.serviceWorker.controller})()")
    if not controlado:
        cdp.navigate("/")
        time.sleep(1)
        controlado = cdp.evaluate("!!navigator.serviceWorker.controller")
    if not controlado:
        raise RuntimeError("Service worker nao assumiu o cliente")
    cdp.call("Emulation.setDeviceMetricsOverride", {"width": 390, "height": 844, "deviceScaleFactor": 1, "mobile": True})
    cdp.call("Network.emulateNetworkConditions", {
        "offline": True, "latency": 0, "downloadThroughput": 0, "uploadThroughput": 0,
        "connectionType": "none",
    })
    cdp.navigate("/offline.html")
    time.sleep(1)
    if not cdp.evaluate("!!document.querySelector('#sincronizar')"):
        raise RuntimeError("Shell offline nao foi aberto")
    cdp.evaluate("""(() => {
        document.querySelector('#notas .nota button:not([disabled])').click();
        document.querySelector('#titulo').value += ' editada offline';
        document.querySelector('#editor').requestSubmit();
    })()""")
    time.sleep(0.5)
    cdp.evaluate("""(() => {
        document.querySelector('#nova').click();
        document.querySelector('#titulo').value = 'Criada offline';
        document.querySelector('#descricao').value = 'Permanece pendente apos recarga';
        document.querySelector('#editor').requestSubmit();
    })()""")
    time.sleep(0.5)
    cdp.evaluate("""(() => {
        document.querySelector('#nova').click();
        document.querySelector('#tipo').value = 'lista';
        document.querySelector('#tipo').dispatchEvent(new Event('change'));
        document.querySelector('#titulo').value = 'Lista offline';
        document.querySelector('#itens').value = '[x] Primeiro item\\n[ ] Segundo item';
        document.querySelector('#editor').requestSubmit();
    })()""")
    time.sleep(1)
    cdp.navigate("/offline.html")
    time.sleep(1)
    offline_text = cdp.evaluate("document.body.innerText")
    if any(texto not in offline_text for texto in ['Criada offline', 'Lista offline', 'editada offline', '[x] Primeiro item']):
        raise RuntimeError("Criacao ou edicao offline nao persistiu apos recarga")
    if "pendente" not in offline_text.lower():
        raise RuntimeError("Estado pendente offline nao foi apresentado")
    cdp.screenshot("expansoes-offline-celular.png")

    cdp.call("Network.emulateNetworkConditions", {
        "offline": False, "latency": 20, "downloadThroughput": 1000000,
        "uploadThroughput": 1000000, "connectionType": "wifi",
    })
    cdp.evaluate("document.querySelector('#sincronizar').click()")
    for _ in range(80):
        time.sleep(0.15)
        text = cdp.evaluate("document.querySelector('#estado').textContent")
        if "conclu" in text.lower() or "nenhuma" in text.lower():
            break
    synced = cdp.evaluate("(async()=>await (await fetch('/sincronizacao/bootstrap')).json())()")
    titulos = [note.get("titulo") or "" for note in synced["notas"]]
    if "Criada offline" not in titulos or "Lista offline" not in titulos or not any("editada offline" in titulo for titulo in titulos):
        raise RuntimeError("Criacao ou edicao offline nao foi sincronizada")

    print(json.dumps({
        "preflight": True,
        "conta": email,
        "notas_online": len(bootstrap["notas"]),
        "offline_sincronizada": True,
        "rolagem_horizontal_390": horizontal,
        "capturas": [
            "expansoes-notas-desktop.png", "expansoes-notas-celular.png",
            "expansoes-lembretes-desktop.png", "expansoes-offline-celular.png",
        ],
    }, ensure_ascii=False))
finally:
    chrome.terminate()
    try:
        chrome.wait(timeout=5)
    except subprocess.TimeoutExpired:
        chrome.kill()
