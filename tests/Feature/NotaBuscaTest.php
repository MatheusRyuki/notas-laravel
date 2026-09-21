<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotaBuscaTest extends TestCase
{
    use RefreshDatabase;

    public function test_busca_por_titulo_e_descricao_inclui_campos_nulos(): void
    {
        $usuario = User::factory()->create();
        $titulo = $usuario->notas()->create(['titulo' => 'Reunião do produto', 'descricao' => null]);
        $descricao = $usuario->notas()->create(['titulo' => null, 'descricao' => "Pauta da reunião\ncom detalhes"]);
        $usuario->notas()->create(['titulo' => 'Compras', 'descricao' => null]);

        $resposta = $this->actingAs($usuario)->get(route('notas.inicio', ['q' => 'reunião']));

        $resposta->assertOk()->assertSee($titulo->titulo)->assertSee('Pauta da reunião')->assertDontSee('Compras');
    }

    public function test_or_da_busca_nao_ultrapassa_proprietario_ou_secao(): void
    {
        $usuario = User::factory()->create();
        $outro = User::factory()->create();
        $ativa = $usuario->notas()->create(['titulo' => 'Projeto isolado']);
        $arquivada = $usuario->notas()->create(['titulo' => 'Projeto arquivado']);
        $arquivada->forceFill(['arquivada' => true])->save();
        $removida = $usuario->notas()->create(['descricao' => 'Projeto removido']);
        $removida->delete();
        $alheia = $outro->notas()->create(['titulo' => 'Projeto alheio']);

        $this->actingAs($usuario)->get(route('notas.inicio', ['q' => 'Projeto']))->assertOk()
            ->assertSee($ativa->titulo)->assertDontSee($arquivada->titulo)->assertDontSee('Projeto removido')->assertDontSee($alheia->titulo);
        $this->actingAs($usuario)->get(route('notas.arquivadas', ['q' => 'Projeto']))->assertOk()
            ->assertSee($arquivada->titulo)->assertDontSee($ativa->titulo)->assertDontSee('Projeto removido');
        $this->actingAs($usuario)->get(route('lixeira.index', ['q' => 'Projeto']))->assertOk()
            ->assertSee('Projeto removido')->assertDontSee($ativa->titulo)->assertDontSee($arquivada->titulo);
    }

    public function test_percentual_sublinhado_e_escape_sao_texto_literal(): void
    {
        $usuario = User::factory()->create();
        $usuario->notas()->create(['titulo' => 'Taxa 100% real']);
        $usuario->notas()->create(['titulo' => 'Código_item']);
        $usuario->notas()->create(['titulo' => 'Sinal!forte']);
        $usuario->notas()->create(['titulo' => 'Taxa 1000 real']);
        $usuario->notas()->create(['titulo' => 'CódigoXitem']);

        $this->actingAs($usuario)->get(route('notas.inicio', ['q' => '%']))->assertOk()
            ->assertSee('Taxa 100% real')->assertDontSee('Taxa 1000 real');
        $this->actingAs($usuario)->get(route('notas.inicio', ['q' => '_']))->assertOk()
            ->assertSee('Código_item')->assertDontSee('CódigoXitem');
        $this->actingAs($usuario)->get(route('notas.inicio', ['q' => '!']))->assertOk()->assertSee('Sinal!forte');
    }

    public function test_termo_vazio_restaura_listagem_e_termo_longo_e_rejeitado(): void
    {
        $usuario = User::factory()->create();
        $usuario->notas()->create(['titulo' => 'Primeira']);
        $usuario->notas()->create(['titulo' => 'Segunda']);

        $this->actingAs($usuario)->get(route('notas.inicio', ['q' => '   ']))->assertOk()
            ->assertSee('Primeira')->assertSee('Segunda');
        $this->actingAs($usuario)->getJson(route('notas.inicio', ['q' => str_repeat('a', 101)]))
            ->assertUnprocessable()->assertJsonValidationErrors('q');
    }

    public function test_resposta_json_identifica_secao_termo_e_html_atual(): void
    {
        $usuario = User::factory()->create();
        $usuario->notas()->create(['titulo' => 'Fixada buscável'])->forceFill(['fixada' => true])->save();
        $usuario->notas()->create(['descricao' => 'Texto buscável']);

        $this->actingAs($usuario)->getJson(route('notas.inicio', ['q' => 'buscável']))->assertOk()
            ->assertJsonPath('secao', 'ativas')->assertJsonPath('termo', 'buscável')->assertJsonPath('total', 2)
            ->assertJson(fn ($json) => $json->whereType('html', 'string')->etc());
    }

    public function test_nenhum_resultado_e_formulario_convencional_em_portugues(): void
    {
        $usuario = User::factory()->create();
        $usuario->notas()->create(['titulo' => 'Existente']);

        $this->actingAs($usuario)->get(route('notas.inicio', ['q' => 'ausente']))->assertOk()
            ->assertSee('Nenhuma nota encontrada.')
            ->assertSee('value="ausente"', false)
            ->assertSee('action="'.route('notas.inicio').'"', false);
    }

    public function test_acoes_preservam_consulta_ativa(): void
    {
        $usuario = User::factory()->create();
        $nota = $usuario->notas()->create(['titulo' => 'Contexto']);

        $this->actingAs($usuario)->patch(route('notas.fixacao', $nota), ['fixada' => true, 'q' => 'Contexto'])
            ->assertRedirect(route('notas.inicio', ['q' => 'Contexto']));
        $this->actingAs($usuario)->delete(route('notas.mover-lixeira', $nota), ['q' => 'Contexto'])
            ->assertRedirect(route('notas.inicio', ['q' => 'Contexto']));
        $this->actingAs($usuario)->patch(route('lixeira.restaurar', $nota->id), ['q' => 'Contexto'])
            ->assertRedirect(route('lixeira.index', ['q' => 'Contexto']));
    }

    public function test_diagnostico_http_existe_no_ambiente_de_testes(): void
    {
        $this->get('/_diagnostico/ambiente-verificacao')->assertOk()
            ->assertJsonPath('ambiente', 'testing')
            ->assertJsonPath('driver_modelos', 'sqlite')
            ->assertJsonPath('database_modelos', ':memory:')
            ->assertJsonPath('sessao', 'array')
            ->assertJsonPath('cache', 'array')
            ->assertJsonPath('config_cache', false);
    }
}
