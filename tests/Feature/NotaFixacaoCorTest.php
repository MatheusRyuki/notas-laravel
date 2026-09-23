<?php

namespace Tests\Feature;

use App\Enums\CorNota;
use App\Enums\TipoAparencia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotaFixacaoCorTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_pode_fixar_e_desafixar_alterando_somente_esse_campo(): void
    {
        $usuario = User::factory()->create();
        $nota = $usuario->notas()->create(['titulo' => 'Original', 'descricao' => 'Conteúdo']);

        $this->actingAs($usuario)->patch(route('notas.fixacao', $nota), [
            'fixada' => true,
            'titulo' => 'Tentativa de alteração',
            'usuario_id' => User::factory()->create()->id,
        ])->assertRedirect(route('notas.inicio'))
            ->assertSessionHas('sucesso', 'Nota fixada com sucesso.');

        $nota->refresh();
        $this->assertTrue($nota->fixada);
        $this->assertSame('Original', $nota->titulo);
        $this->assertSame('Conteúdo', $nota->descricao);
        $this->assertSame($usuario->id, $nota->usuario_id);

        $this->actingAs($usuario)->patch(route('notas.fixacao', $nota), ['fixada' => false])
            ->assertRedirect(route('notas.inicio'))
            ->assertSessionHas('sucesso', 'Nota desafixada com sucesso.');

        $this->assertFalse($nota->fresh()->fixada);
    }

    public function test_estado_de_fixacao_invalido_e_rejeitado(): void
    {
        $usuario = User::factory()->create();
        $nota = $usuario->notas()->create(['titulo' => 'Não alterar']);

        $this->actingAs($usuario)->patch(route('notas.fixacao', $nota), ['fixada' => 'talvez'])
            ->assertSessionHasErrors(['fixada' => 'O estado da fixação é inválido.']);

        $this->assertFalse($nota->fresh()->fixada);
    }

    public function test_listagem_separa_fixadas_e_outras_sem_titulos_vazios(): void
    {
        $usuario = User::factory()->create();
        $fixadaAntiga = $usuario->notas()->create(['titulo' => 'Fixada antiga']);
        $fixadaRecente = $usuario->notas()->create(['titulo' => 'Fixada recente']);
        $outraAntiga = $usuario->notas()->create(['titulo' => 'Outra antiga']);
        $outraRecente = $usuario->notas()->create(['titulo' => 'Outra recente']);

        $fixadaAntiga->forceFill(['fixada' => true, 'updated_at' => now()->subDays(2)])->saveQuietly();
        $fixadaRecente->forceFill(['fixada' => true, 'updated_at' => now()->subDay()])->saveQuietly();
        $outraAntiga->forceFill(['updated_at' => now()->subDays(2)])->saveQuietly();
        $outraRecente->forceFill(['updated_at' => now()->subDay()])->saveQuietly();

        $this->actingAs($usuario)->get(route('notas.inicio'))
            ->assertOk()
            ->assertSeeInOrder(['Fixadas', 'Fixada recente', 'Fixada antiga', 'Outras', 'Outra recente', 'Outra antiga']);

        $fixadaAntiga->forceFill(['fixada' => false])->save();
        $fixadaRecente->forceFill(['fixada' => false])->save();

        $this->actingAs($usuario)->get(route('notas.inicio'))
            ->assertOk()
            ->assertDontSee('id="titulo-fixadas"', false)
            ->assertDontSee('id="titulo-outras"', false);
    }

    public function test_criacao_aceita_cor_da_paleta_e_padrao_e_nulo(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->post(route('notas.store'), [
            'titulo' => 'Nota menta',
            'descricao' => '',
            'cor' => CorNota::Menta->value,
        ])->assertSessionHasNoErrors();

        $menta = $usuario->notas()->where('titulo', 'Nota menta')->sole();
        $this->assertSame(CorNota::Menta, $menta->cor);
        $this->assertSame(TipoAparencia::Cor, $menta->tipo_aparencia);
        $this->assertNull($menta->caminho_imagem);

        $this->actingAs($usuario)->post(route('notas.store'), [
            'titulo' => 'Nota padrão',
            'descricao' => '',
            'cor' => CorNota::Padrao->value,
        ])->assertSessionHasNoErrors();

        $this->assertNull($usuario->notas()->where('titulo', 'Nota padrão')->sole()->cor);
    }

    public function test_criacao_rejeita_cor_desconhecida_e_preserva_entrada(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->from(route('notas.inicio'))->post(route('notas.store'), [
            'titulo' => 'Não deve existir',
            'descricao' => 'Conteúdo',
            'cor' => 'background:red',
        ])->assertRedirect(route('notas.inicio'))
            ->assertSessionHasErrorsIn('criacaoNota', ['cor'])
            ->assertSessionHasInput('titulo', 'Não deve existir')
            ->assertSessionHasInput('descricao', 'Conteúdo');

        $this->assertDatabaseCount('notas', 0);
    }

    public function test_edicao_salva_cor_valida_e_ignora_campos_nao_permitidos(): void
    {
        $usuario = User::factory()->create();
        $outroUsuario = User::factory()->create();
        $nota = $usuario->notas()->create(['titulo' => 'Antes', 'descricao' => 'Texto']);

        $this->actingAs($usuario)->patchJson(route('notas.update', $nota), [
            'revisao' => $nota->revisao,
            'tipo_conteudo' => 'texto',
            'titulo' => 'Depois',
            'descricao' => "Linha 1\nLinha 2",
            'cor' => CorNota::Lavanda->value,
            'usuario_id' => $outroUsuario->id,
            'fixada' => true,
            'caminho_imagem' => '/arquivo-manipulado.jpg',
        ])->assertOk()
            ->assertJsonPath('nota.cor', CorNota::Lavanda->value);

        $nota->refresh();
        $this->assertSame(CorNota::Lavanda, $nota->cor);
        $this->assertSame(TipoAparencia::Cor, $nota->tipo_aparencia);
        $this->assertNull($nota->caminho_imagem);
        $this->assertSame($usuario->id, $nota->usuario_id);
        $this->assertFalse($nota->fixada);
        $this->assertSame("Linha 1\nLinha 2", $nota->descricao);
    }

    public function test_edicao_rejeita_cor_desconhecida_sem_alterar_nota(): void
    {
        $usuario = User::factory()->create();
        $nota = $usuario->notas()->create(['titulo' => 'Preservada']);
        $nota->forceFill(['cor' => CorNota::Areia])->save();

        $this->actingAs($usuario)->patchJson(route('notas.update', $nota), [
            'revisao' => $nota->revisao,
            'tipo_conteudo' => 'texto',
            'titulo' => 'Não salvar',
            'descricao' => '',
            'cor' => '#ff0000',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['cor']);

        $nota->refresh();
        $this->assertSame('Preservada', $nota->titulo);
        $this->assertSame(CorNota::Areia, $nota->cor);
    }

    public function test_visitante_nao_pode_editar_nem_fixar_nota(): void
    {
        $nota = User::factory()->create()->notas()->create(['titulo' => 'Privada']);

        $this->patch(route('notas.fixacao', $nota), ['fixada' => true])->assertRedirect(route('login'));
        $this->patch(route('notas.update', $nota), [
            'titulo' => 'Invadida',
            'descricao' => '',
            'cor' => CorNota::Ceu->value,
        ])->assertRedirect(route('login'));

        $nota->refresh();
        $this->assertFalse($nota->fixada);
        $this->assertSame('Privada', $nota->titulo);
    }

    public function test_segunda_conta_nao_pode_consultar_editar_nem_fixar_nota(): void
    {
        $dono = User::factory()->create();
        $intruso = User::factory()->create();
        $nota = $dono->notas()->create(['titulo' => 'Segredo']);

        $this->actingAs($intruso)->getJson(route('notas.show', $nota))->assertForbidden();
        $this->actingAs($intruso)->patchJson(route('notas.update', $nota), [
            'revisao' => $nota->revisao,
            'tipo_conteudo' => 'texto',
            'titulo' => 'Invadida',
            'descricao' => '',
            'cor' => CorNota::Ceu->value,
        ])->assertForbidden();
        $this->actingAs($intruso)->patch(route('notas.fixacao', $nota), ['fixada' => true])->assertForbidden();

        $nota->refresh();
        $this->assertSame('Segredo', $nota->titulo);
        $this->assertFalse($nota->fixada);
        $this->assertNull($nota->cor);
    }

    public function test_cartao_exibe_cor_estado_e_acoes_acessiveis(): void
    {
        $usuario = User::factory()->create();
        $nota = $usuario->notas()->create(['titulo' => 'Colorida']);
        $nota->forceFill(['cor' => CorNota::Pessego, 'fixada' => true])->save();

        $this->actingAs($usuario)->get(route('notas.inicio'))
            ->assertOk()
            ->assertSee('data-cor="pessego"', false)
            ->assertSee('data-fixada="true"', false)
            ->assertSee('Fixada')
            ->assertSee('aria-label="Desafixar nota Colorida"', false)
            ->assertSee('Cor de fundo')
            ->assertSee('value="menta"', false)
            ->assertSee('value="ceu"', false);
    }
}
