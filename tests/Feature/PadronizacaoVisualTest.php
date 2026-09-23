<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PadronizacaoVisualTest extends TestCase
{
    use RefreshDatabase;

    public function test_filtros_sem_etiquetas_exibem_estado_util_e_acesso_ao_gerenciamento(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->get(route('notas.inicio'))
            ->assertOk()
            ->assertSee('Nenhuma etiqueta criada.')
            ->assertSee('Criar ou gerenciar')
            ->assertSee('id="gerenciar-etiquetas"', false)
            ->assertDontSee('<legend>Filtrar por qualquer etiqueta</legend>', false);

        $this->get(route('notas.arquivadas'))
            ->assertOk()
            ->assertSee('Nenhuma etiqueta criada.')
            ->assertDontSee('<legend>Filtrar por qualquer etiqueta</legend>', false);

        $this->get(route('lixeira.index'))
            ->assertOk()
            ->assertSee('Nenhuma etiqueta criada.')
            ->assertSee(route('notas.inicio').'#gerenciar-etiquetas', false)
            ->assertDontSee('<legend>Filtrar por qualquer etiqueta</legend>', false);
    }

    public function test_cartao_mantem_acoes_adicionais_recolhidas_e_nao_oferece_salvar_etiquetas_vazias(): void
    {
        $usuario = User::factory()->create(['fuso_horario' => 'America/Sao_Paulo']);
        $nota = $usuario->notas()->create(['titulo' => 'Nota curta']);

        $resposta = $this->actingAs($usuario)->get(route('notas.inicio'))
            ->assertOk()
            ->assertSee('class="mais-acoes-nota"', false)
            ->assertSee('Mais ações')
            ->assertSee('Copiar como texto')
            ->assertSee('Baixar .txt')
            ->assertSee('Adicionar lembrete')
            ->assertSee('Horário interpretado no fuso America/Sao_Paulo.')
            ->assertSee('Criar ou gerenciar etiquetas')
            ->assertDontSee('Salvar etiquetas');

        $this->assertStringContainsString('x-on:keydown.escape.stop.prevent', $resposta->getContent());
        $this->assertStringContainsString('const atual = $event.currentTarget.parentElement', $resposta->getContent());

        $etiqueta = $usuario->etiquetas()->create(['nome' => 'Projeto']);
        $nota->etiquetas()->attach($etiqueta->id, ['usuario_id' => $usuario->id]);

        $this->get(route('notas.inicio'))
            ->assertOk()
            ->assertSee('Etiquetas pessoais')
            ->assertSee('Salvar etiquetas')
            ->assertSee('Projeto');
    }

    public function test_editor_de_lista_usa_controles_padronizados_e_icones_locais(): void
    {
        $usuario = User::factory()->create();

        $resposta = $this->actingAs($usuario)->get(route('notas.inicio'))
            ->assertOk()
            ->assertSee('class="estado-item-lista"', false)
            ->assertSee('class="campo-item-lista"', false)
            ->assertSee('class="acoes-item-lista"', false)
            ->assertSee('class="botao-icone-lista"', false)
            ->assertSee('class="botao-remover-item"', false)
            ->assertSee('class="botao-adicionar-item"', false)
            ->assertDontSee('>↑<', false)
            ->assertDontSee('>↓<', false);

        $this->assertStringContainsString('aria-label="Mover item para cima"', $resposta->getContent());
        $this->assertStringContainsString('aria-label="Mover item para baixo"', $resposta->getContent());
    }

    public function test_cartao_de_lista_usa_marcadores_svg_alinhados_ao_texto(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->post(route('notas.store'), [
            'titulo' => 'Lista visual',
            'tipo_conteudo' => 'lista',
            'tipo_aparencia' => 'cor',
            'cor' => 'padrao',
            'itens' => [
                ['texto' => 'Item concluído', 'concluido' => 1],
                ['texto' => 'Item pendente', 'concluido' => 0],
            ],
        ])->assertSessionHasNoErrors();

        $this->get(route('notas.inicio'))
            ->assertOk()
            ->assertSee('class="marcador-item-lista"', false)
            ->assertSee('class="texto-item-lista"', false)
            ->assertDontSee('☑')
            ->assertDontSee('☐');
    }

    public function test_lembretes_usam_cabecalhos_contadores_e_estados_vazios_padronizados(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->get(route('lembretes.index'))
            ->assertOk()
            ->assertSee('class="grade-lembretes"', false)
            ->assertSee('id="titulo-agendamentos"', false)
            ->assertSee('id="titulo-notificacoes"', false)
            ->assertSee('class="contador-secao"', false)
            ->assertSee('class="estado-secao-vazio estado-lembretes-vazio"', false)
            ->assertSee('Nenhum lembrete agendado')
            ->assertSee('Nenhuma notificação')
            ->assertSee('Use “Mais ações” em uma nota')
            ->assertSee('Os avisos gerados pelos seus lembretes aparecerão aqui.');
    }

    public function test_compartilhamentos_usam_rotulos_associados_e_hierarquia_de_acoes(): void
    {
        $usuario = User::factory()->create();
        $nota = $usuario->notas()->create(['titulo' => 'Planejamento']);

        $this->actingAs($usuario)->get(route('compartilhamentos.index'))
            ->assertOk()
            ->assertSee('for="email-convite-'.$nota->id.'"', false)
            ->assertSee('id="email-convite-'.$nota->id.'"', false)
            ->assertSee('for="papel-convite-'.$nota->id.'"', false)
            ->assertSee('class="form-convite"', false)
            ->assertSee('class="botao-principal"', false)
            ->assertSee('Nenhum participante. Use o formulário acima para convidar alguém.');
    }
}
