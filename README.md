# Notas — etapa 7

Aplicativo de notas com interface própria, inspirado no Google Keep, desenvolvido em Laravel com ambiente Docker/Sail independente.

- Aplicação: http://localhost:8004
- Correio local: http://localhost:8026

## Funcionalidades disponíveis

- Cadastro, login, logout e recuperação/redefinição de senha com Breeze e Blade.
- Perfil básico: nome, e-mail e senha.
- Página inicial protegida, com nome real do usuário, perfil e saída.
- Criação, listagem e edição de notas em modal.
- Fixação e desafixação com estado textual e grupos **Fixadas** e **Outras**.
- Cores suaves e opção Padrão na criação e na edição.
- Galeria de quatro fundos SVG locais na criação e na edição.
- Arquivamento e desarquivamento em uma página própria, com edição das notas arquivadas.
- Lixeira por exclusão lógica, consulta somente leitura, restauração ao destino anterior e exclusão definitiva confirmada.
- Busca por título ou descrição enquanto o usuário digita, nas seções Notas, Arquivadas e Lixeira.
- Prévia de aparência no modal, persistida somente depois de salvar.
- Cancelamento, fechamento e Escape sem alterar texto ou aparência salvos.
- Validações junto aos campos, proteção CSRF e bloqueio de envios duplicados.
- Conteúdo escapado, interface em pt-BR, layout responsivo e uso por teclado.
- Mailpit para recuperação de senha local.

Não há eliminação automática nem ação de esvaziar a lixeira.

A interface e os fundos são próprios do projeto e apenas inspirados no Google Keep. O curso não forneceu arquivos de template e não há integração externa pendente.

## Notas e segurança

O proprietário vem exclusivamente da sessão autenticada. O cliente não pode escolher ou alterar `usuario_id`. A policy impede leitura, edição, fixação, arquivamento, movimentação para a lixeira, restauração e exclusão definitiva de notas de outra conta, inclusive por URL direta ou requisição manipulada.

- Título: opcional quando há descrição, até 255 caracteres.
- Descrição: opcional quando há título, até 10.000 caracteres.
- Espaços externos são removidos; quebras de linha internas são preservadas.
- A saída Blade escapa título e descrição.
- A listagem ordena por fixação, atualização mais recente e ID mais alto.
- A fixação altera somente `fixada` e preserva toda a aparência.
- Uma edição somente de texto preserva a aparência existente.

## Aparência

O servidor aceita apenas identificadores dos enums `CorNota`, `FundoNota` e `TipoAparencia`. Caminhos livres, URLs externas e CSS enviados pelo navegador não fazem parte do contrato.

A paleta contém Padrão, Areia, Menta, Céu, Lavanda e Pêssego. Padrão grava `cor = null`. Escolher qualquer cor define `tipo_aparencia = cor` e limpa `caminho_imagem`.

| Identificador | Nome | Arquivo local |
| --- | --- | --- |
| `folhas` | Folhas tranquilas | `public/images/fundos/folhas-tranquilas.svg` |
| `ondas` | Ondas suaves | `public/images/fundos/ondas-suaves.svg` |
| `geometria` | Formas serenas | `public/images/fundos/formas-serenas.svg` |
| `constelacao` | Céu pontilhado | `public/images/fundos/ceu-pontilhado.svg` |

Escolher um fundo define `tipo_aparencia = imagem`, limpa `cor` e grava o caminho obtido pelo catálogo interno. A camada clara sobre os SVGs mantém textos e controles legíveis; o fundo neutro permanece caso um arquivo não carregue.

## Arquivamento

A ação recebe explicitamente `arquivada = true` ou `false`, valida o valor e altera somente esse campo. Repetir o mesmo estado não inverte o resultado. Título, descrição, cor, fundo, fixação, proprietário e `deleted_at` permanecem intactos.

A fixação é preservada enquanto a nota está arquivada. Ao desarquivar, uma nota fixada retorna ao grupo **Fixadas**; as demais retornam a **Outras**. A tela principal consulta somente notas ativas e `/arquivadas` consulta somente notas arquivadas, sempre a partir da relação do usuário autenticado e sem incluir soft deletes.

Notas arquivadas podem ser abertas e editadas no modal. Salvar, cancelar, fechar ou pressionar Escape mantém o usuário na seção Arquivadas e não altera o estado de arquivamento.
## Lixeira

**Mover para a lixeira** usa `SoftDeletes` e preserva título, descrição, aparência, fixação e o estado de arquivamento. As listagens normais e seus endpoints de alteração usam o escopo padrão e rejeitam notas removidas, inclusive quando uma aba antiga tenta enviar uma alteração.

`/lixeira` consulta somente notas removidas do usuário autenticado, ordenadas por `deleted_at DESC` e `id DESC`. O conteúdo abre em um modal somente leitura. Restaurar mantém o mesmo ID e devolve uma nota arquivada para **Arquivadas** ou uma nota ativa para **Minhas notas**, preservando fixação e aparência.

A exclusão definitiva só aceita notas que já estejam na lixeira. A confirmação é uma página GET autorizada, sem mutação, que identifica a nota e contém um formulário protegido por CSRF com method spoofing para `DELETE`. Cancelar, fechar ou pressionar Escape não exclui; o fluxo também funciona sem JavaScript. Os SVGs são arquivos compartilhados do catálogo e nunca são apagados com uma nota.
## Busca

A busca usa o parâmetro GET `q`, aceita até 100 caracteres e funciona por envio convencional sem JavaScript. Espaços externos são removidos; consulta vazia restaura a listagem normal. O termo permanece na URL ao recarregar, compartilhar o endereço e navegar entre **Minhas notas**, **Arquivadas** e **Lixeira**.

Título e descrição são consultados com parâmetros vinculados. `%`, `_` e `!` são escapados e tratados como caracteres literais. O `OR` fica agrupado dentro da relação do usuário e dos filtros da seção: ativas na tela principal, arquivadas em `/arquivadas` e somente removidas em `/lixeira`.

Com JavaScript, há debounce de 300 ms, cancelamento da requisição anterior e uma sequência que impede respostas antigas ou de outra seção de substituir o resultado atual. O campo mantém foco. Carregamento, nenhum resultado e falha de comunicação têm estados separados em pt-BR. Os cartões retornados são reinicializados pelo Alpine, sem duplicar manipuladores.

A consulta acompanha criação, edição, fixação, arquivamento, lixeira, restauração e exclusão. Após uma mutação, o servidor recalcula a lista; uma nota que deixou de corresponder desaparece.
As decisões do esquema estão em [docs/modelo-nota.md](docs/modelo-nota.md).

## Instalação

Requisitos: Docker Desktop com WSL2/Ubuntu, ou Docker Engine com Compose no Linux, Git e internet. Não instale PHP, Composer ou Herd no computador.

```bash
cd /home/dr_4_/clone_google_keep
cp .env.example .env

docker run --rm \
  --user "$(id -u):$(id -g)" \
  -e COMPOSER_HOME=/tmp/composer \
  -v "$PWD":/app -w /app \
  composer:2.10 install --ignore-platform-reqs --no-scripts --no-interaction

./vendor/bin/sail up -d --build
./vendor/bin/sail composer install --no-interaction
./vendor/bin/sail composer check-platform-reqs
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm ci
./vendor/bin/sail npm run build
```

A dispensa de requisitos ocorre somente no bootstrap. A instalação definitiva e sua validação usam PHP 8.5 dentro do Sail. Atualizações usam `./vendor/bin/sail artisan migrate`; não resete o banco existente.

## Portas e isolamento

| Recurso | Host | Entre containers |
| --- | --- | --- |
| Aplicação | http://localhost:8004 | laravel.test:80 |
| MySQL | 127.0.0.1:33061 | mysql:3306 |
| Vite | http://localhost:5174 | laravel.test:5174 |
| Mailpit | http://localhost:8026 | mailpit:8025 |
| SMTP | Não publicado | mailpit:1025 |

- Compose: `notas`.
- Rede: `notas-rede`.
- Volume: `notas-mysql`.
- Imagem: `notas-app:php85`.
- Banco e usuário MySQL: `notas`.
- Cookie: `notas_session`.
- Serviços publicados somente em `127.0.0.1`.

## Comandos úteis

```bash
./vendor/bin/sail up -d
./vendor/bin/sail ps
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm run dev
./vendor/bin/sail npm run build
./vendor/bin/sail artisan test
./vendor/bin/sail bin pint --test
./vendor/bin/sail composer validate --strict
./vendor/bin/sail composer check-platform-reqs
./vendor/bin/sail down
```

Não use `down -v` se quiser preservar os dados.

## Verificação segura no navegador

O navegador não deve ser iniciado com `artisan serve` para esta verificação. Esse comando cria um processo filho e permitiu que a validação do CLI divergisse da conexão usada pelas requisições HTTP. O procedimento reproduzível usa `php -S` diretamente, SQLite e caches exclusivos em `/tmp/notas-verificacao/<execução>`.

```bash
cd /home/dr_4_/clone_google_keep
./tests/e2e/testar-aborto-isolamento.sh
./tests/e2e/iniciar-isolado.sh etapa7 8006
# execute o roteiro somente depois de ISOLAMENTO CONFIRMADO
./tests/e2e/parar-isolado.sh etapa7
```

`iniciar-isolado.sh` cria uma configuração em cache própria, inicia o processo HTTP e chama `preflight-isolamento.sh` antes das migrations. O preflight consulta `/_diagnostico/ambiente-verificacao` no próprio servidor e exige `testing`, driver `sqlite`, arquivo `/verificacao/notas.sqlite`, sessão `cookie`, cache `array` e configuração em cache ativa. Qualquer divergência termina com erro antes de migrations, cadastro ou seed.

A rota de diagnóstico só é registrada em `testing`; na aplicação normal ela responde 404. O cookie também recebe nome exclusivo por execução. `testar-aborto-isolamento.sh` usa um servidor falso incompatível e comprova o aborto sem acessar o MySQL principal.
## Recuperação de senha

Cadastre uma conta, saia, clique em **Esqueci minha senha**, informe o e-mail e abra http://localhost:8026. Use o link recebido para definir uma nova senha. O link expira em 60 minutos e o envio é síncrono.

## Versões instaladas

Verificadas em 21/09/2026:

| Componente | Versão |
| --- | --- |
| Laravel Framework / esqueleto | 13.32.0 / 13.10.1 |
| Sail | 1.67.0 |
| PHP | 8.5.10 |
| MySQL | 8.4.11 |
| Breeze | 2.4.2, Blade |
| PHPUnit | 12.5.35 |
| Pint | 1.32.1 |
| Composer | 2.10.3 |
| Node.js / npm | 24.21.0 / 12.0.2 |
| Vite / plugin Laravel | 8.3.0 / 3.2.0 |
| Tailwind CSS / Alpine.js | 3.4.19 / 3.17.3 |
| Mailpit | 1.31.2 |
| Docker Engine | 29.8.0 |

As versões exatas estão em `composer.lock` e `package-lock.json`.

## Verificações desta etapa

- 89 testes e 453 asserções aprovadas em SQLite `:memory:`.
- Cobertura de título, descrição, campos nulos, termo vazio, limite, nenhum resultado e `%`, `_` e `!` literais.
- Cobertura do agrupamento por usuário e por seção, resposta JSON e preservação de `q` nas mutações.
- O preflight HTTP real confirmou `testing`, `sqlite`, `/verificacao/notas.sqlite`, sessão `cookie`, cache `array` e configuração em cache própria antes das mutações.
- Uma configuração simulada com driver MySQL foi recusada antes de qualquer mutação; a rota de diagnóstico retornou 404 na aplicação normal.
- Chromium confirmou debounce, cancelamento, respostas fora de ordem, falha de rede, limpeza, recarga pela URL e navegação com consulta ativa.
- Cartões e modal permaneceram funcionais após atualização dinâmica; uma edição que deixou de corresponder foi removida pelo servidor.
- A busca convencional funcionou com JavaScript desativado. Desktop 1440 × 1000 e celular 390 × 844 não apresentaram rolagem horizontal nem erros JavaScript.
- Pint, build de produção, validação estrita do Composer e requisitos de plataforma foram aprovados.

| Busca | Captura |
| --- | --- |
| Minhas notas — desktop | [captura](docs/capturas/busca-notas-desktop.png) |
| Arquivadas — desktop | [captura](docs/capturas/busca-arquivadas-desktop.png) |
| Lixeira — celular | [captura](docs/capturas/busca-lixeira-celular.png) |

As capturas usam somente o SQLite criado depois do preflight HTTP.

## Git e atribuições

Repositório independente em `main`, com identidade somente local: `MatheusRyuki <matheuskaiya2@gmail.com>`. Não há commit, remote ou push. Nenhuma dependência ou arquivo exclusivo de agentes foi adicionada.

Consulte [licenças e atribuições](docs/atribuicoes.md).
