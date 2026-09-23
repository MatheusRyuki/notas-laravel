const DB_NOME = 'notas-offline-v1';
const CANAL_NOME = 'notas-conta-v1';
const origemCanal = crypto.randomUUID();
const canalConta = 'BroadcastChannel' in window ? new BroadcastChannel(CANAL_NOME) : null;
const $ = (seletor) => document.querySelector(seletor);
let contaId = null;
let editando = null;

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
async function transacao(nomes, modo, executar) {
    const db = await abrirBanco();
    const tx = db.transaction(nomes, modo);
    const lojas = Object.fromEntries(nomes.map((nome) => [nome, tx.objectStore(nome)]));
    const resultado = await executar(lojas);
    await new Promise((resolve, reject) => {
        tx.oncomplete = resolve;
        tx.onerror = () => reject(tx.error);
        tx.onabort = () => reject(tx.error);
    });
    db.close();
    return resultado;
}
function estado(texto) { $('#estado').textContent = texto; }
function payloadFormulario() {
    const aparencia = $('#aparencia').value.split(':');
    return {
        titulo: $('#titulo').value.trim() || null,
        descricao: $('#descricao').value.trim() || null,
        tipo_conteudo: $('#tipo').value,
        itens: $('#itens').value.split('\n').map((linha) => {
            const texto = linha.trim();
            const concluido = /^\[x\]\s*/i.test(texto);
            return { texto: texto.replace(/^\[[x ]\]\s*/i, '').trim(), concluido };
        }).filter((item) => item.texto),
        tipo_aparencia: aparencia[0],
        cor: aparencia[0] === 'cor' ? aparencia[1] : 'padrao',
        fundo: aparencia[0] === 'imagem' ? aparencia[1] : 'folhas',
    };
}
function preencher(nota = null) {
    editando = nota;
    $('#titulo-editor').textContent = nota ? 'Editar nota offline' : 'Nova nota offline';
    $('#tipo').value = nota?.tipo_conteudo || 'texto';
    $('#tipo').disabled = Boolean(nota);
    $('#titulo').value = nota?.titulo || '';
    $('#descricao').value = nota?.descricao || '';
    $('#itens').value = (nota?.itens || []).map((item) => (item.concluido ? '[x] ' : '[ ] ') + item.texto).join('\n');
    $('#aparencia').value = nota?.tipo_aparencia === 'imagem' ? 'imagem:' + nota.fundo : 'cor:' + (nota?.cor || 'padrao');
    $('#campo-itens').hidden = $('#tipo').value !== 'lista';
    $('#editor').classList.remove('oculto');
    $('#titulo').focus();
}
async function salvar(evento) {
    evento.preventDefault();
    const payload = payloadFormulario();
    if (payload.tipo_conteudo === 'texto' && !payload.titulo && !payload.descricao) {
        estado('Informe título ou descrição.');
        return;
    }
    if (payload.tipo_conteudo === 'lista' && !payload.itens.length) {
        estado('Adicione ao menos um item.');
        return;
    }
    try {
        await transacao(['notas', 'operacoes'], 'readwrite', async ({ notas, operacoes }) => {
            if (editando) {
                const atual = { ...editando, ...payload, conta_id: contaId, pendente: true };
                notas.put(atual);
                const todas = await requisicao(operacoes.getAll());
                const criacao = todas.find((op) => op.conta_id === contaId && op.acao === 'criar' && op.id_local === editando.uuid);
                const atualizacao = todas.find((op) => op.conta_id === contaId && op.acao === 'atualizar' && op.nota_uuid === editando.uuid && op.estado !== 'erro');
                if (criacao) {
                    operacoes.put({ ...criacao, payload });
                } else if (atualizacao) {
                    operacoes.put({ ...atualizacao, payload });
                } else {
                    operacoes.put({ uuid: crypto.randomUUID(), conta_id: contaId, acao: 'atualizar', nota_uuid: editando.uuid, revisao_base: editando.revisao, payload, sequencia: Date.now() });
                }
            } else {
                const idLocal = crypto.randomUUID();
                const nota = { uuid: idLocal, ...payload, revisao: 1, papel: 'proprietario', proprietario: 'Você', pode_editar: true, conta_id: contaId, pendente: true };
                notas.put(nota);
                operacoes.put({ uuid: crypto.randomUUID(), conta_id: contaId, acao: 'criar', id_local: idLocal, payload, sequencia: Date.now() });
            }
        });
        $('#editor').classList.add('oculto');
        estado('Salvo neste dispositivo · alteração pendente.');
        await renderizar();
    } catch {
        estado('Falha ou limite do armazenamento local. A alteração não foi salva.');
    }
}
async function carregarDados() {
    const db = await abrirBanco();
    const tx = db.transaction(['meta', 'notas', 'conflitos'], 'readonly');
    contaId = await requisicao(tx.objectStore('meta').get('conta_atual'));
    const nome = await requisicao(tx.objectStore('meta').get('conta_nome'));
    const notas = (await requisicao(tx.objectStore('notas').getAll())).filter((nota) => nota.conta_id === contaId);
    const conflitos = (await requisicao(tx.objectStore('conflitos').getAll())).filter((conflito) => conflito.conta_id === contaId);
    db.close();
    return { nome, notas, conflitos };
}
function textoNota(nota) {
    return [nota.titulo, nota.descricao, (nota.itens || []).map((item) => (item.concluido ? '[x] ' : '[ ] ') + item.texto).join('\n')].filter(Boolean).join('\n\n');
}
async function renderizar() {
    const dados = await carregarDados();
    $('#conta').textContent = dados.nome ? 'Conta: ' + dados.nome : '';
    if (!contaId) {
        estado('Faça o primeiro login com conexão antes de usar o modo offline.');
        $('#nova').disabled = true;
        return;
    }
    $('#notas').innerHTML = '';
    dados.notas.forEach((nota) => {
        const artigo = document.createElement('article');
        artigo.className = 'nota ' + (nota.pendente ? 'pendente' : '');
        const titulo = document.createElement('h3');
        titulo.textContent = nota.titulo || 'Sem título';
        const descricao = document.createElement('p');
        descricao.textContent = nota.descricao || '';
        const itens = document.createElement('pre');
        itens.className = 'itens';
        itens.textContent = (nota.itens || []).map((item) => (item.concluido ? '[x] ' : '[ ] ') + item.texto).join('\n');
        const editar = document.createElement('button');
        editar.textContent = nota.pode_editar === false ? 'Somente leitura' : 'Editar';
        editar.disabled = nota.pode_editar === false;
        editar.onclick = () => preencher(nota);
        artigo.append(titulo, descricao, itens, editar);
        if (nota.pendente) {
            const pendente = document.createElement('small');
            pendente.textContent = 'Alteração pendente';
            artigo.append(pendente);
        }
        $('#notas').append(artigo);
    });
    $('#conflitos').innerHTML = '';
    dados.conflitos.forEach((conflito) => {
        const artigo = document.createElement('article');
        artigo.className = 'conflito';
        const titulo = document.createElement('h3');
        titulo.textContent = 'Conflito em ' + (conflito.atual.titulo || 'nota sem título');
        const comparacao = document.createElement('pre');
        comparacao.textContent = 'Sua versão:\n' + textoNota(conflito.local) + '\n\nVersão atual:\n' + textoNota(conflito.atual);
        const servidor = document.createElement('button');
        servidor.textContent = 'Manter versão atual';
        servidor.onclick = () => resolver(conflito, false);
        const local = document.createElement('button');
        local.textContent = 'Tentar minha versão';
        local.onclick = () => resolver(conflito, true);
        artigo.append(titulo, comparacao, servidor, local);
        $('#conflitos').append(artigo);
    });
}
async function tokenCsrf() {
    const resposta = await fetch('/', { credentials: 'same-origin' });
    if (!resposta.ok || resposta.url.includes('/login')) throw new Error();
    const html = await resposta.text();
    const encontrado = html.match(/name="csrf-token" content="([^"]+)"/);
    if (!encontrado) throw new Error();
    return encontrado[1];
}
async function sincronizar() {
    if (!navigator.onLine) {
        estado('Sem conexão. As alterações continuam pendentes.');
        return;
    }
    try {
        const contaDaRequisicao = contaId;
        const csrf = await tokenCsrf();
        const db = await abrirBanco();
        const tx = db.transaction('operacoes', 'readonly');
        const operacoes = (await requisicao(tx.objectStore('operacoes').getAll()))
            .filter((op) => op.conta_id === contaDaRequisicao && op.estado !== 'erro')
            .sort((a, b) => (a.sequencia || 0) - (b.sequencia || 0));
        db.close();
        if (!operacoes.length) {
            estado('Nenhuma alteração pendente.');
            return;
        }
        estado('Sincronizando…');
        const resposta = await fetch('/sincronizacao', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ conta_id: contaDaRequisicao, operacoes }),
        });
        if (!resposta.ok) throw new Error();
        const dados = await resposta.json();
        let permissaoAlterada = false;
        const aplicada = await transacao(['meta', 'notas', 'operacoes', 'conflitos'], 'readwrite', async ({ meta, notas, operacoes: fila, conflitos }) => {
            const contaAtual = await requisicao(meta.get('conta_atual'));
            if (contaAtual !== contaDaRequisicao) return false;

            for (const resultado of dados.resultados) {
                if (resultado.status === 'sincronizado') {
                    notas.put({ ...resultado.nota, conta_id: contaDaRequisicao, pendente: false });
                    fila.delete(resultado.operacao_uuid);
                } else if (resultado.status === 'somente_leitura') {
                    notas.put({ ...resultado.nota, conta_id: contaDaRequisicao, pendente: false });
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
        estado(permissaoAlterada
            ? 'Sua permissão mudou para somente leitura. A alteração local não foi aplicada.'
            : 'Sincronização concluída.');
        await renderizar();
    } catch {
        estado('Não foi possível sincronizar. Entre novamente online; as alterações locais foram preservadas.');
    }
}
async function resolver(conflito, usarLocal) {
    await transacao(['notas', 'operacoes', 'conflitos'], 'readwrite', async ({ notas, operacoes, conflitos }) => {
        if (usarLocal) {
            operacoes.put({ uuid: crypto.randomUUID(), conta_id: contaId, acao: 'atualizar', nota_uuid: conflito.nota_uuid, revisao_base: conflito.atual.revisao, payload: conflito.local, sequencia: Date.now() });
            notas.put({ ...conflito.atual, ...conflito.local, conta_id: contaId, pendente: true });
        } else {
            notas.put({ ...conflito.atual, conta_id: contaId, pendente: false });
        }
        conflitos.delete(conflito.nota_uuid);
    });
    await renderizar();
    if (usarLocal) sincronizar();
}

$('#nova').onclick = () => preencher();
$('#cancelar').onclick = () => $('#editor').classList.add('oculto');
$('#editor').onsubmit = salvar;
$('#sincronizar').onclick = sincronizar;
$('#voltar').onclick = () => {
    if (navigator.onLine) location.href = '/';
    else estado('Ainda sem conexão. A sessão do servidor não foi encerrada.');
};
$('#tipo').onchange = () => { $('#campo-itens').hidden = $('#tipo').value !== 'lista'; };
window.addEventListener('online', () => { estado('Conexão restaurada.'); sincronizar(); });
window.addEventListener('offline', () => estado('Offline · alterações pendentes permanecem neste dispositivo.'));
canalConta?.addEventListener('message', async (evento) => {
    if (evento.data?.origem === origemCanal) return;
    if (evento.data?.tipo === 'sessao-encerrada') {
        contaId = null;
        $('#notas').innerHTML = '';
        $('#conflitos').innerHTML = '';
        $('#conta').textContent = '';
        $('#nova').disabled = true;
        estado('A sessão foi encerrada em outra aba. Os dados locais desta conta foram removidos.');
    } else if (evento.data?.tipo === 'conta-alterada') {
        await renderizar();
        const removidas = Number(evento.data?.pendenciasDescartadas || 0);
        estado(removidas > 0
            ? 'A conta mudou em outra aba. As alterações pendentes da conta anterior foram removidas deste dispositivo.'
            : 'A conta mudou em outra aba. Os dados exibidos foram atualizados.');
    }
});
renderizar()
    .then(() => estado(navigator.onLine ? 'Online · pronto para sincronizar.' : 'Offline · dados locais disponíveis.'))
    .catch(() => estado('O armazenamento local está indisponível; nenhuma alteração será marcada como salva.'));
