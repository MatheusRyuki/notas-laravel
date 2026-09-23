# Modelo Nota — implementado

O model `App\Models\Nota` usa explicitamente a tabela `notas`, `SoftDeletes` e a relação `usuario()`. O model `User` expõe a relação `notas()`.

| Campo | Tipo | Regra atual |
| --- | --- | --- |
| id | bigint, chave primária | Identificador |
| usuario_id | FK de users.id | Proprietário obrigatório; exclusão da conta remove suas notas em cascata |
| titulo | varchar(255), nullable | Opcional quando há descrição |
| descricao | text, nullable | Opcional quando há título; limite da aplicação de 10.000 caracteres |
| tipo_conteudo | varchar(10) | `texto` ou `lista`; `texto` para notas existentes |
| revisao | bigint unsigned | Revisão monotônica, inicialmente 1 |
| uuid_sincronizacao | uuid, unique | Identidade estável da sincronização, preenchida também no legado |
| fixada | boolean | `false` inicialmente; alterada pela operação dedicada de fixação |
| arquivada | boolean | `false` inicialmente; alterada pela operação dedicada de arquivamento |
| tipo_aparencia | varchar(10) | `cor` ou `imagem`; `cor` inicialmente |
| cor | varchar(20), nullable | Identificador validado da paleta; `null` representa a opção Padrão |
| caminho_imagem | varchar(255), nullable | Caminho local resolvido pelo catálogo quando o tipo é `imagem` |
| created_at, updated_at | timestamps | Controle temporal |
| deleted_at | timestamp, nullable | Exclusão lógica |

## Conteúdo e limites

Título e descrição são opcionais individualmente, mas uma nota não pode ficar inteiramente vazia. Antes da validação, espaços e quebras de linha externos são removidos; as quebras internas da descrição são preservadas.

O título tem limite de 255 caracteres, igual à coluna `varchar(255)`. A descrição usa `text` e a aplicação limita a entrada a 10.000 caracteres. Os mesmos limites existem no HTML e no servidor; uma aparência não substitui a exigência de conteúdo textual.

## Aparência normalizada

O enum `CorNota` centraliza `padrao`, `areia`, `menta`, `ceu`, `lavanda` e `pessego`. A opção `padrao` é armazenada com `tipo_aparencia = cor`, `cor = null` e `caminho_imagem = null`.

O enum `FundoNota` é o catálogo único de identificadores, nomes e caminhos locais:

| Identificador | Nome | Caminho persistido |
| --- | --- | --- |
| `folhas` | Folhas tranquilas | `images/fundos/folhas-tranquilas.svg` |
| `ondas` | Ondas suaves | `images/fundos/ondas-suaves.svg` |
| `geometria` | Formas serenas | `images/fundos/formas-serenas.svg` |
| `constelacao` | Céu pontilhado | `images/fundos/ceu-pontilhado.svg` |

O navegador envia somente `tipo_aparencia`, `cor` e `fundo`. Nunca envia um caminho que seja usado pelo servidor. Ao escolher imagem, o servidor resolve o identificador em `FundoNota`, grava o caminho catalogado, define `tipo_aparencia = imagem` e limpa `cor`. Ao escolher cor ou Padrão, define `tipo_aparencia = cor` e limpa `caminho_imagem`. Identificadores, tipos, URLs, caminhos e CSS fora dos enums são rejeitados ou ignorados conforme não façam parte do contrato.

Na edição, a aparência só é normalizada quando algum campo de aparência é enviado. Uma requisição que altera somente título e descrição mantém a aparência anterior. A operação dedicada de fixação altera somente `fixada`.

## Arquivamento

A ação dedicada valida o booleano desejado e preenche somente `arquivada`. Como o estado é explícito, repetir `true` mantém a nota arquivada e repetir `false` mantém a nota ativa. A ação não aceita alterações de título, descrição, aparência, fixação, proprietário ou `deleted_at`.

A fixação é preservada durante o arquivamento. Ao desarquivar, `fixada = true` leva a nota de volta ao grupo **Fixadas**; `fixada = false` leva a **Outras**. Editar título, descrição ou aparência de uma nota arquivada também não altera `arquivada`.

A listagem principal aplica `arquivada = false`; a página Arquivadas aplica `arquivada = true`. Ambas partem da relação do usuário autenticado, usam o escopo padrão que exclui `deleted_at` e ordenam por `fixada DESC`, `updated_at DESC` e `id DESC`.
## Propriedade e autorização

O proprietário sempre vem da sessão por meio da relação de notas do usuário. `usuario_id` enviado pelo cliente é ignorado.

As listagens usam o escopo de notas acessíveis: propriedade ou participação aceita, sempre dentro da seção pedida. A tela principal separa **Fixadas** e **Outras** somente quando existe ao menos uma nota fixada.

A `NotaPolicy` distingue proprietário, editor e leitor em URLs diretas e requisições manipuladas. Estados da nota e participantes são exclusivos do proprietário. A exclusão lógica também é respeitada pelo route model binding padrão.

## Lixeira, restauração e exclusão definitiva

A ação de mover para a lixeira usa `delete()` e só aceita uma nota não removida do proprietário. O soft delete altera `deleted_at` e os timestamps normais, preservando título, descrição, aparência, fixação e `arquivada`.

As listagens e alterações comuns mantêm o escopo padrão do Eloquent, portanto registros removidos não podem ser abertos, editados, fixados ou arquivados por URLs antigas. Os fluxos da lixeira resolvem explicitamente `onlyTrashed()` e aplicam a Policy ao registro encontrado.

Restaurar usa `restore()` no mesmo registro e ID. Se `arquivada = true`, a nota retorna a **Arquivadas**; caso contrário, retorna a **Minhas notas**. Fixação e aparência não são normalizadas nem modificadas.

A exclusão definitiva usa `forceDelete()` somente depois de confirmar que a nota está removida e pertence ao usuário autenticado. A confirmação é obtida por GET sem mutação e a exclusão usa um formulário protegido por CSRF. Uma nota ativa não é encontrada nesse fluxo. Nenhum arquivo do catálogo `FundoNota` é apagado, pois os SVGs são recursos versionados e compartilhados.

## Busca

A busca normal começa no escopo `acessiveisPor()` e a Lixeira começa nas notas removidas do proprietário. Na tela principal aplica `arquivada = false`, em Arquivadas aplica `arquivada = true` e na Lixeira adiciona `onlyTrashed()`. Só depois desses limites é acrescentado um grupo entre parênteses para título, descrição ou itens, mantendo acesso e seção fora do `OR`.

O padrão usa parâmetros vinculados e `ESCAPE '!'`. Antes de acrescentar `%` nas extremidades, `!`, `%` e `_` são convertidos para `!!`, `!%` e `!_`; assim caracteres curinga enviados pelo usuário são texto literal. Colunas nulas não impedem a outra coluna de corresponder.

`q` é aparado e limitado a 100 caracteres. Um valor vazio não adiciona condição e preserva a ordenação normal. A resposta HTML e a resposta JSON reutilizam a mesma consulta e o mesmo componente de cartões.

## Conteúdo estruturado e revisão

`tipo_conteudo` aceita `texto` ou `lista`; o valor padrão preserva registros anteriores como texto. `nota_itens` guarda texto (até 500 caracteres), conclusão e posição, com unicidade entre nota e posição. O tipo não muda durante a edição.

`revisao` começa em 1 e avança em toda alteração compartilhada. Conteúdo, aparência e estados usam lock de linha quando precisam comparar ou alterar a revisão. Salvamentos HTTP e offline recusam uma revisão base antiga com conflito explícito.

`uuid_sincronizacao` é estável e único. A migration preenche UUIDs nas notas existentes. `operacoes_sincronizacao` associa cada UUID de operação ao usuário e conserva o resultado, oferecendo idempotência depois da perda de uma resposta.

## Metadados pessoais

`etiquetas` pertence a um usuário e guarda nome exibido e nome normalizado. A pivot `etiqueta_nota` inclui `usuario_id`; assim duas pessoas podem organizar a mesma nota de formas diferentes. A unicidade é por usuário, nota e etiqueta.

`lembretes` também pertence simultaneamente ao usuário e à nota, com uma linha por par. O instante fica em UTC e o fuso original é preservado. `ativo`, `suspenso_lixeira` e `processado_em` distinguem agendamento, suspensão e processamento. `notificacoes_internas.chave` é única para impedir duplicações.

`operacoes_desfazer` guarda token, usuário, tipo, revisões esperadas, retorno de navegação, expiração e uso. O conteúdo completo da nota não é copiado.

## Colaboração

`nota_participantes` vincula uma conta cadastrada a uma nota com papel `editor` ou `leitor`. `convites_notas` mantém o usuário convidado, quem convidou, papel, token e estado; o e-mail é informação do convite, enquanto a autorização usa `convidado_id`.

A propriedade continua em `notas.usuario_id` e não é transferida. Participantes aceitos entram no escopo `acessiveisPor` enquanto a nota não está removida. Editor pode atualizar conteúdo e aparência. Leitor pode consultar e exportar. Somente o proprietário gerencia fixação, arquivamento, lixeira, restauração, exclusão e participantes.

Mover à lixeira não remove pivots de participantes, itens ou metadados pessoais. O escopo de leitura oculta a nota removida dos participantes; restaurar recupera os vínculos ainda existentes. Exclusão definitiva usa cascatas para remover os dados dependentes, sem tocar nos SVGs versionados.

## Offline

O IndexedDB usa o UUID da nota e `conta_id` como fronteiras lógicas. Notas, fila e conflitos da conta anterior são removidos na troca de conta. O servidor rejeita um `conta_id` diferente da sessão e revalida Policy e revisão em cada atualização.

O service worker armazena somente `offline.html`, `offline-app.js` e o manifesto. Dados privados ficam no IndexedDB e não em Cache Storage. Revogação e exclusão são reconciliadas na próxima conexão; até lá uma cópia já obtida pode continuar no dispositivo desconectado.
