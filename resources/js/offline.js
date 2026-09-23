const DB_NOME = 'notas-offline-v1';
const CANAL_NOME = 'notas-conta-v1';
const origemCanal = crypto.randomUUID();
const canalConta = 'BroadcastChannel' in window ? new BroadcastChannel(CANAL_NOME) : null;

function avisarAbas(tipo, detalhes = {}) {
    canalConta?.postMessage({ tipo, origem: origemCanal, ...detalhes });
}

function abrirBanco() {
    return new Promise((resolve, reject) => {
        const pedido = indexedDB.open(DB_NOME, 1);
        pedido.onupgradeneeded = () => {
            const db = pedido.result;
            if (!db.objectStoreNames.contains('meta')) db.createObjectStore('meta');
            if (!db.objectStoreNames.contains('notas')) db.createObjectStore('notas', { keyPath: 'uuid' });
            if (!db.objectStoreNames.contains('operacoes')) db.createObjectStore('operacoes', { keyPath: 'uuid' });
            if (!db.objectStoreNames.contains('conflitos')) db.createObjectStore('conflitos', { keyPath: 'nota_uuid' });
        };
        pedido.onsuccess = () => resolve(pedido.result);
        pedido.onerror = () => reject(pedido.error);
    });
}

function requisicao(pedido) {
    return new Promise((resolve, reject) => {
        pedido.onsuccess = () => resolve(pedido.result);
        pedido.onerror = () => reject(pedido.error);
    });
}

async function transacao(lojas, modo, executar) {
    const db = await abrirBanco();
    const tx = db.transaction(lojas, modo);
    const resultado = await executar(Object.fromEntries(lojas.map((nome) => [nome, tx.objectStore(nome)])));
    await new Promise((resolve, reject) => {
        tx.oncomplete = resolve;
        tx.onerror = () => reject(tx.error);
        tx.onabort = () => reject(tx.error);
    });
    db.close();
    return resultado;
}

async function bootstrap() {
    const resposta = await fetch('/sincronizacao/bootstrap', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    if (!resposta.ok) return;
    const dados = await resposta.json();

    const troca = await transacao(['meta', 'notas', 'operacoes', 'conflitos'], 'readwrite', async ({ meta, notas, operacoes, conflitos }) => {
        const contaAnterior = await requisicao(meta.get('conta_atual'));
        let pendenciasDescartadas = 0;
        if (contaAnterior && contaAnterior !== dados.conta_id) {
            for (const nota of await requisicao(notas.getAll())) {
                if (nota.conta_id !== dados.conta_id) notas.delete(nota.uuid);
            }
            for (const operacao of await requisicao(operacoes.getAll())) {
                if (operacao.conta_id !== dados.conta_id) {
                    pendenciasDescartadas++;
                    operacoes.delete(operacao.uuid);
                }
            }
            for (const conflito of await requisicao(conflitos.getAll())) {
                if (conflito.conta_id !== dados.conta_id) conflitos.delete(conflito.nota_uuid);
            }
        }

        meta.put(dados.conta_id, 'conta_atual');
        meta.put(dados.conta_nome, 'conta_nome');
        const existentes = await requisicao(notas.getAll());
        const locais = new Map(existentes.filter((nota) => nota.conta_id === dados.conta_id).map((nota) => [nota.uuid, nota]));
        const permitidas = new Set(dados.notas.map((nota) => nota.uuid));
        existentes
            .filter((nota) => nota.conta_id === dados.conta_id && !nota.pendente && !permitidas.has(nota.uuid))
            .forEach((nota) => notas.delete(nota.uuid));
        dados.notas.forEach((nota) => {
            if (!locais.get(nota.uuid)?.pendente) notas.put({ ...nota, conta_id: dados.conta_id, pendente: false });
        });
        return { contaAnterior, pendenciasDescartadas };
    });

    if (troca.contaAnterior && troca.contaAnterior !== dados.conta_id) {
        avisarAbas('conta-alterada', {
            contaId: dados.conta_id,
            pendenciasDescartadas: troca.pendenciasDescartadas,
        });
    }
    if (troca.pendenciasDescartadas > 0) {
        atualizarEstado('A conta mudou. Dados pendentes da conta anterior foram removidos deste dispositivo.');
    }
}

async function sincronizar() {
    const db = await abrirBanco();
    const tx = db.transaction(['meta', 'operacoes'], 'readonly');
    const contaId = await requisicao(tx.objectStore('meta').get('conta_atual'));
    const operacoes = (await requisicao(tx.objectStore('operacoes').getAll()))
        .filter((op) => op.conta_id === contaId && op.estado !== 'erro')
        .sort((a, b) => (a.sequencia ?? 0) - (b.sequencia ?? 0));
    db.close();
    if (!contaId || operacoes.length === 0) return;

    atualizarEstado('Sincronizando alterações…');
    const resposta = await fetch('/sincronizacao', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
        body: JSON.stringify({ conta_id: contaId, operacoes }),
    });
    if (!resposta.ok) throw new Error('Sessão indisponível para sincronizar.');
    const dados = await resposta.json();

    let permissaoAlterada = false;
    const aplicada = await transacao(['meta', 'notas', 'operacoes', 'conflitos'], 'readwrite', async ({ meta, notas, operacoes: fila, conflitos }) => {
        const contaAtual = await requisicao(meta.get('conta_atual'));
        if (contaAtual !== contaId) return false;

        for (const resultado of dados.resultados) {
            if (resultado.status === 'sincronizado') {
                notas.put({ ...resultado.nota, conta_id: contaId, pendente: false });
                fila.delete(resultado.operacao_uuid);
            } else if (resultado.status === 'somente_leitura') {
                notas.put({ ...resultado.nota, conta_id: contaId, pendente: false });
                fila.delete(resultado.operacao_uuid);
                permissaoAlterada = true;
            } else if (resultado.status === 'revogado_ou_excluido') {
                if (resultado.nota_uuid) notas.delete(resultado.nota_uuid);
                fila.delete(resultado.operacao_uuid);
            } else if (resultado.status === 'conflito') {
                conflitos.put({ nota_uuid: resultado.nota_uuid, conta_id: contaId, local: resultado.local, atual: resultado.atual });
                fila.delete(resultado.operacao_uuid);
            } else {
                const op = await requisicao(fila.get(resultado.operacao_uuid));
                if (op) fila.put({ ...op, estado: 'erro', erros: resultado.erros });
            }
        }
        return true;
    });
    if (!aplicada) return;
    atualizarEstado(permissaoAlterada
        ? 'Sua permissão mudou para somente leitura. A alteração local não foi aplicada.'
        : 'Sincronizado.');
    await bootstrap();
}

function atualizarEstado(texto) {
    const elemento = document.getElementById('estado-offline');
    if (!elemento) return;
    elemento.hidden = false;
    elemento.textContent = texto;
    if (texto === 'Sincronizado.') window.setTimeout(() => { elemento.hidden = true; }, 2500);
}

async function pendencias() {
    const db = await abrirBanco();
    const tx = db.transaction(['meta', 'operacoes'], 'readonly');
    const contaId = await requisicao(tx.objectStore('meta').get('conta_atual'));
    const quantidade = (await requisicao(tx.objectStore('operacoes').getAll()))
        .filter((operacao) => operacao.conta_id === contaId).length;
    db.close();
    return quantidade;
}

async function limparPrivado() {
    await new Promise((resolve, reject) => {
        const pedido = indexedDB.deleteDatabase(DB_NOME);
        pedido.onsuccess = resolve;
        pedido.onerror = () => reject(pedido.error);
        pedido.onblocked = () => reject(new Error('O armazenamento está em uso por outra aba.'));
    });
}

export async function iniciarOffline() {
    if (!('indexedDB' in window) || !('serviceWorker' in navigator)) return;

    try {
        await navigator.serviceWorker.register('/sw.js');
        if (navigator.onLine) {
            await bootstrap();
            await sincronizar();
        } else {
            atualizarEstado('Offline · alterações locais permanecem pendentes.');
        }
    } catch {
        atualizarEstado('O armazenamento offline está indisponível; nada foi marcado como salvo offline.');
    }

    window.addEventListener('online', async () => {
        try {
            atualizarEstado('Conexão restaurada.');
            await bootstrap();
            await sincronizar();
        } catch {
            atualizarEstado('Erro ao sincronizar. As alterações continuam pendentes.');
        }
    });
    window.addEventListener('offline', () => atualizarEstado('Offline · alterações locais permanecem pendentes.'));

    document.querySelectorAll('form[action$="/logout"]').forEach((form) => {
        form.addEventListener('submit', async (evento) => {
            evento.preventDefault();
            if (!navigator.onLine) {
                atualizarEstado('Não foi possível encerrar a sessão do servidor sem conexão.');
                return;
            }
            const quantidade = await pendencias();
            if (quantidade > 0 && !window.confirm('Há alterações offline pendentes. Sair descartará esses dados locais. Continuar?')) {
                return;
            }

            try {
                const resposta = await fetch(form.action, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: new FormData(form),
                    headers: { Accept: 'text/html' },
                });
                if (!resposta.ok) throw new Error();
                await limparPrivado();
                avisarAbas('sessao-encerrada');
                window.location.assign(resposta.url || '/login');
            } catch {
                atualizarEstado('Não foi possível encerrar a sessão. Os dados locais foram preservados.');
            }
        });
    });

    canalConta?.addEventListener('message', (evento) => {
        if (evento.data?.origem === origemCanal) return;
        if (evento.data?.tipo === 'sessao-encerrada' || evento.data?.tipo === 'conta-alterada') {
            window.location.reload();
        }
    });
}
