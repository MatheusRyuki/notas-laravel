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
    for largura in (1440, 390, 320):
        cdp.call("Emulation.setDeviceMetricsOverride", {"width": largura, "height": 900, "deviceScaleFactor": 1, "mobile": largura < 760})
        cdp.navigate("/")
        time.sleep(0.4)
        cdp.evaluate("""(() => {
            const form = document.createElement('form');
            form.method = 'POST'; form.action = '/notas';
            const dados = {_token: document.querySelector('meta[name="csrf-token"]').content,
                titulo: 'Teste isolado do aviso', tipo_conteudo: 'texto', tipo_aparencia: 'cor', cor: 'padrao'};
            for (const [name, value] of Object.entries(dados)) {
                const input = document.createElement('input'); input.name = name; input.value = value;
                form.append(input);
            }
            document.body.append(form); form.submit();
        })()""")
        time.sleep(1)
        antes = cdp.evaluate("""(() => {
            const aviso = document.querySelector('.aviso-confirmacao');
            const r = aviso.getBoundingClientRect();
            return {topo: document.querySelector('.titulo-notas').getBoundingClientRect().top,
                fixo: getComputedStyle(aviso).position === 'fixed',
                visivel: r.height > 0, cabe: r.left >= 0 && r.right <= innerWidth};
        })()""")
        assert antes["fixo"] and antes["visivel"] and antes["cabe"], antes
        cdp.screenshot(f"padronizacao-aviso-{largura}.png")
        if largura == 1440:
            time.sleep(6.2)
        else:
            cdp.evaluate("document.querySelector('.fechar-confirmacao').click()")
            time.sleep(0.2)
        depois = cdp.evaluate("""(() => ({
            topo: document.querySelector('.titulo-notas').getBoundingClientRect().top,
            fechado: document.querySelector('.aviso-confirmacao').getBoundingClientRect().height === 0
        }))()""")
        assert depois["fechado"] and abs(antes["topo"] - depois["topo"]) < 1, (antes, depois)
        print(json.dumps({"largura": largura, "antes": antes, "depois": depois}))
finally:
    chrome.terminate()
    chrome.wait(timeout=5)
