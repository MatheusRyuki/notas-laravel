import Alpine from 'alpinejs';
import { iniciarOffline } from './offline';

const itemVazio = () => ({ chave: crypto.randomUUID(), texto: '', concluido: false });

window.notasApp = (aparenciaCriacao = {}, fundosDisponiveis = {}, configuracaoBusca = {}) => ({
    fundosDisponiveis,
    criacao: {
        tipo_aparencia: aparenciaCriacao.tipo_aparencia ?? 'cor',
        cor: aparenciaCriacao.cor ?? 'padrao',
        fundo: aparenciaCriacao.fundo ?? Object.keys(fundosDisponiveis)[0] ?? '',
        tipo_conteudo: aparenciaCriacao.tipo_conteudo ?? 'texto',
        itens: (aparenciaCriacao.itens ?? [itemVazio()]).map((item) => ({ chave: crypto.randomUUID(), ...item })),
    },
    carregandoEdicao: false,
    salvandoEdicao: false,
    erroCarregamento: '',
    errosEdicao: {},
    conflitoEdicao: null,
    urlAtualizacao: '',
    edicao: { titulo: '', descricao: '', tipo_conteudo: 'texto', itens: [], tipo_aparencia: 'cor', cor: 'padrao', fundo: Object.keys(fundosDisponiveis)[0] ?? '', revisao: 1, papel: '' },
    carregandoLeitura: false,
    erroLeitura: '',
    leitura: { titulo: '', descricao: '', tipo_conteudo: 'texto', itens: [], tipo_aparencia: 'cor', cor: 'padrao', fundo: Object.keys(fundosDisponiveis)[0] ?? '', fixada: false, arquivada: false },
    termoBusca: configuracaoBusca.termo ?? '',
    secaoBusca: configuracaoBusca.secao ?? '',
    urlBusca: configuracaoBusca.url ?? window.location.pathname,
    buscaCarregando: false,
    erroBusca: '',
    temporizadorBusca: null,
    controleBusca: null,
    sequenciaBusca: 0,
    modoSelecao: false,
    selecionadas: [],
    mensagemInterface: '',

    iniciarBusca() {
        window.addEventListener('popstate', () => {
            this.termoBusca = new URL(window.location.href).searchParams.get('q') ?? '';
            this.executarBusca(false);
        });
    },

    agendarBusca() {
        window.clearTimeout(this.temporizadorBusca);
        this.temporizadorBusca = window.setTimeout(() => this.executarBusca(), 300);
    },

    buscarAgora() {
        window.clearTimeout(this.temporizadorBusca);
        this.executarBusca();
    },

    limparBusca() {
        this.termoBusca = '';
        this.buscarAgora();
        this.$nextTick(() => this.$refs.busca?.focus());
    },

    async executarBusca(atualizarUrl = true) {
        const termo = this.termoBusca.trim();
        const url = new URL(this.urlBusca, window.location.origin);
        if (termo) url.searchParams.set('q', termo);
        else url.searchParams.delete('q');

        if (atualizarUrl) window.history.replaceState({}, '', `${url.pathname}${url.search}`);

        this.controleBusca?.abort();
        this.controleBusca = new AbortController();
        const sequencia = ++this.sequenciaBusca;
        const secao = this.secaoBusca;
        this.buscaCarregando = true;
        this.erroBusca = '';

        try {
            const resposta = await fetch(url, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
                signal: this.controleBusca.signal,
            });
            const dados = await resposta.json();

            if (resposta.status === 422) {
                const erro = new Error(Object.values(dados.errors ?? {}).flat()[0] ?? 'O termo ou filtro é inválido.');
                erro.exibirMensagem = true;
                throw erro;
            }
            if (! resposta.ok) {
                const erro = new Error('Não foi possível atualizar a busca. Tente novamente.');
                erro.exibirMensagem = true;
                throw erro;
            }
            if (sequencia !== this.sequenciaBusca || dados.secao !== secao) return;

            const resultados = this.$refs.resultados;
            Array.from(resultados.children).forEach((elemento) => window.Alpine.destroyTree(elemento));
            resultados.innerHTML = dados.html;
            Array.from(resultados.children).forEach((elemento) => window.Alpine.initTree(elemento));
            const visiveis = new Set([...resultados.querySelectorAll('[data-nota-id]')].map((elemento) => String(elemento.dataset.notaId)));
            this.selecionadas = this.selecionadas.filter((id) => visiveis.has(String(id)));
        } catch (erro) {
            if (erro.name !== 'AbortError' && sequencia === this.sequenciaBusca) {
                this.erroBusca = erro.exibirMensagem ? erro.message : 'Falha de comunicação durante a busca. Tente novamente.';
            }
        } finally {
            if (sequencia === this.sequenciaBusca) this.buscaCarregando = false;
        }
    },

    classeAparencia(aparencia) {
        if (aparencia.tipo_aparencia === 'imagem' && this.fundosDisponiveis[aparencia.fundo]) return 'aparencia-imagem';
        return `cor-nota--${aparencia.cor ?? 'padrao'}`;
    },

    estiloAparencia(aparencia) {
        const caminho = this.fundosDisponiveis[aparencia.fundo];
        return aparencia.tipo_aparencia === 'imagem' && caminho ? { '--imagem-nota': `url("${caminho}")` } : {};
    },

    adicionarItem(itens) {
        itens.push(itemVazio());
        this.$nextTick(() => document.querySelector('.linha-item:last-of-type input[type="text"]')?.focus());
    },

    moverItem(itens, indice, deslocamento) {
        const destino = indice + deslocamento;
        if (destino < 0 || destino >= itens.length) return;
        [itens[indice], itens[destino]] = [itens[destino], itens[indice]];
    },

    async abrirNota(url) {
        this.carregandoEdicao = true;
        this.erroCarregamento = '';
        this.errosEdicao = {};
        this.conflitoEdicao = null;
        this.urlAtualizacao = '';
        window.dispatchEvent(new CustomEvent('open-modal', { detail: 'editar-nota' }));

        try {
            const resposta = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (! resposta.ok) throw new Error();
            const nota = await resposta.json();

            if (nota.pode_editar === false || ! nota.url_atualizacao) {
                this.leitura = nota;
                window.dispatchEvent(new CustomEvent('close-modal', { detail: 'editar-nota' }));
                this.$nextTick(() => window.dispatchEvent(new CustomEvent('open-modal', { detail: 'consultar-nota' })));
                return;
            }

            this.edicao = {
                ...nota,
                itens: (nota.itens ?? []).map((item) => ({ chave: crypto.randomUUID(), ...item })),
            };
            this.urlAtualizacao = nota.url_atualizacao;
            this.$nextTick(() => setTimeout(() => document.getElementById('editar-titulo')?.focus(), 100));
        } catch {
            this.erroCarregamento = 'Não foi possível abrir esta nota. Tente novamente.';
        } finally {
            this.carregandoEdicao = false;
        }
    },

    async abrirLeitura(url) {
        this.carregandoLeitura = true;
        this.erroLeitura = '';
        window.dispatchEvent(new CustomEvent('open-modal', { detail: 'consultar-nota' }));
        try {
            const resposta = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (! resposta.ok) throw new Error();
            this.leitura = await resposta.json();
        } catch {
            this.erroLeitura = 'Não foi possível consultar esta nota. Tente novamente.';
        } finally {
            this.carregandoLeitura = false;
        }
    },

    async salvarEdicao() {
        if (! this.urlAtualizacao || this.salvandoEdicao) return;
        this.salvandoEdicao = true;
        this.errosEdicao = {};
        this.erroCarregamento = '';

        try {
            const resposta = await fetch(this.urlAtualizacao, {
                method: 'PATCH',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(this.edicao),
            });
            const dados = await resposta.json();

            if (resposta.status === 422) {
                this.errosEdicao = dados.errors ?? {};
                return;
            }
            if (resposta.status === 409) {
                this.conflitoEdicao = dados.conflito;
                return;
            }
            if (! resposta.ok) throw new Error();

            window.location.reload();
        } catch {
            this.erroCarregamento = 'Não foi possível salvar. Tente novamente.';
        } finally {
            this.salvandoEdicao = false;
        }
    },

    resumoConflitoAtual() {
        const atual = this.conflitoEdicao?.atual;
        if (! atual) return '';
        const itens = (atual.itens ?? []).map((item) => `${item.concluido ? '[x]' : '[ ]'} ${item.texto}`).join('\n');
        return [atual.titulo, atual.descricao, itens].filter(Boolean).join('\n\n');
    },

    usarVersaoAtual() {
        if (! this.conflitoEdicao?.atual) return;
        this.edicao = { ...this.conflitoEdicao.atual, itens: (this.conflitoEdicao.atual.itens ?? []).map((item) => ({ chave: crypto.randomUUID(), ...item })) };
        this.conflitoEdicao = null;
    },

    tentarVersaoLocal() {
        if (! this.conflitoEdicao?.atual) return;
        this.edicao.revisao = this.conflitoEdicao.atual.revisao;
        this.conflitoEdicao = null;
        this.salvarEdicao();
    },

    async copiarNota(urlTexto, urlDownload) {
        try {
            const resposta = await fetch(urlTexto, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (! resposta.ok) throw new Error();
            const dados = await resposta.json();
            if (! navigator.clipboard?.writeText) throw new Error();
            await navigator.clipboard.writeText(dados.texto);
            this.mensagemInterface = 'Nota copiada como texto.';
        } catch {
            this.mensagemInterface = 'A área de transferência não está disponível. O arquivo será baixado.';
            window.location.assign(urlDownload);
        }
    },
});

window.Alpine = Alpine;
Alpine.start();
iniciarOffline();
