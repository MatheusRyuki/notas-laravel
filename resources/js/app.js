import Alpine from 'alpinejs';

window.notasApp = (aparenciaCriacao = {}, fundosDisponiveis = {}, configuracaoBusca = {}) => ({
    fundosDisponiveis,
    criacao: {
        tipo_aparencia: aparenciaCriacao.tipo_aparencia ?? 'cor',
        cor: aparenciaCriacao.cor ?? 'padrao',
        fundo: aparenciaCriacao.fundo ?? Object.keys(fundosDisponiveis)[0] ?? '',
    },
    carregandoEdicao: false,
    salvandoEdicao: false,
    erroCarregamento: '',
    errosEdicao: {},
    urlAtualizacao: '',
    edicao: { titulo: '', descricao: '', tipo_aparencia: 'cor', cor: 'padrao', fundo: Object.keys(fundosDisponiveis)[0] ?? '' },
    carregandoLeitura: false,
    erroLeitura: '',
leitura: { titulo: '', descricao: '', tipo_aparencia: 'cor', cor: 'padrao', fundo: Object.keys(fundosDisponiveis)[0] ?? '', fixada: false, arquivada: false },
    termoBusca: configuracaoBusca.termo ?? '',
    secaoBusca: configuracaoBusca.secao ?? '',
    urlBusca: configuracaoBusca.url ?? window.location.pathname,
    buscaCarregando: false,
    erroBusca: '',
    temporizadorBusca: null,
    controleBusca: null,
    sequenciaBusca: 0,

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
                const erroValidacao = new Error(dados.errors?.q?.[0] ?? 'O termo de busca é inválido.');
                erroValidacao.exibirMensagem = true;
                throw erroValidacao;
            }
            if (! resposta.ok) {
                const erroResposta = new Error('Não foi possível atualizar a busca. Tente novamente.');
                erroResposta.exibirMensagem = true;
                throw erroResposta;
            }
            if (sequencia !== this.sequenciaBusca || dados.secao !== secao) return;

            const resultados = this.$refs.resultados;
            Array.from(resultados.children).forEach((elemento) => window.Alpine.destroyTree(elemento));
            resultados.innerHTML = dados.html;
            Array.from(resultados.children).forEach((elemento) => window.Alpine.initTree(elemento));
        } catch (erro) {
            if (erro.name !== 'AbortError' && sequencia === this.sequenciaBusca) {
                this.erroBusca = erro.exibirMensagem
                    ? erro.message
                    : 'Falha de comunicação durante a busca. Tente novamente.';
            }
        } finally {
            if (sequencia === this.sequenciaBusca) this.buscaCarregando = false;
        }
    },

    classeAparencia(aparencia) {
        if (aparencia.tipo_aparencia === 'imagem' && this.fundosDisponiveis[aparencia.fundo]) {
            return 'aparencia-imagem';
        }

        return `cor-nota--${aparencia.cor ?? 'padrao'}`;
    },

    estiloAparencia(aparencia) {
        const caminho = this.fundosDisponiveis[aparencia.fundo];

        if (aparencia.tipo_aparencia !== 'imagem' || ! caminho) {
            return {};
        }

        return { '--imagem-nota': `url("${caminho}")` };
    },

    async abrirLeitura(url) {
        this.carregandoLeitura = true;
        this.erroLeitura = '';
        this.leitura = { titulo: '', descricao: '', tipo_aparencia: 'cor', cor: 'padrao', fundo: Object.keys(this.fundosDisponiveis)[0] ?? '', fixada: false, arquivada: false };
        window.dispatchEvent(new CustomEvent('open-modal', { detail: 'consultar-nota' }));

        try {
            const resposta = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (! resposta.ok) throw new Error('Não foi possível consultar esta nota.');
            this.leitura = await resposta.json();
        } catch (erro) {
            this.erroLeitura = 'Não foi possível consultar esta nota. Tente novamente.';
        } finally {
            this.carregandoLeitura = false;
        }
    },
    async abrirEdicao(url) {
        this.carregandoEdicao = true;
        this.erroCarregamento = '';
        this.errosEdicao = {};
        this.urlAtualizacao = '';
        this.edicao = {
            titulo: '',
            descricao: '',
            tipo_aparencia: 'cor',
            cor: 'padrao',
            fundo: Object.keys(this.fundosDisponiveis)[0] ?? '',
        };
        window.dispatchEvent(new CustomEvent('open-modal', { detail: 'editar-nota' }));

        try {
            const resposta = await fetch(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (! resposta.ok) {
                throw new Error('Não foi possível abrir esta nota.');
            }

            const nota = await resposta.json();
            this.edicao.titulo = nota.titulo ?? '';
            this.edicao.descricao = nota.descricao ?? '';
            this.edicao.tipo_aparencia = nota.tipo_aparencia ?? 'cor';
            this.edicao.cor = nota.cor ?? 'padrao';
            this.edicao.fundo = nota.fundo ?? Object.keys(this.fundosDisponiveis)[0] ?? '';
            this.urlAtualizacao = nota.url_atualizacao;
            this.$nextTick(() => setTimeout(() => document.getElementById('editar-titulo')?.focus(), 100));
        } catch (erro) {
            this.erroCarregamento = 'Não foi possível abrir esta nota. Tente novamente.';
        } finally {
            this.carregandoEdicao = false;
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

            if (resposta.status === 422) {
                const dados = await resposta.json();
                this.errosEdicao = dados.errors ?? {};
                return;
            }

            if (! resposta.ok) {
                throw new Error('Não foi possível salvar. Tente novamente.');
            }

            await resposta.json();
            window.location.reload();
        } catch (erro) {
            this.erroCarregamento = 'Não foi possível salvar. Tente novamente.';
        } finally {
            this.salvandoEdicao = false;
        }
    },
});

window.Alpine = Alpine;
Alpine.start();