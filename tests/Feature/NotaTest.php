<?php

namespace Tests\Feature;

use App\Enums\TipoAparencia;
use App\Models\Nota;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use Tests\TestCase;

class NotaTest extends TestCase
{
    use RefreshDatabase;

    public function test_ambiente_de_testes_usa_sqlite_em_memoria(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame('sqlite', DB::connection()->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME));
    }

    public function test_usuario_cria_nota_com_dados_normalizados_e_proprietario_da_sessao(): void
    {
        $usuario = User::factory()->create();
        $outroUsuario = User::factory()->create();

        $this->actingAs($usuario)->post(route('notas.store'), [
            'titulo' => '  Minha ideia  ',
            'descricao' => "  primeira linha\nsegunda linha  ",
            'usuario_id' => $outroUsuario->id,
            'fixada' => true,
            'arquivada' => true,
        ])->assertRedirect(route('notas.inicio'))
            ->assertSessionHas('sucesso', 'Nota criada com sucesso.');

        $nota = Nota::sole();
        $this->assertSame($usuario->id, $nota->usuario_id);
        $this->assertSame('Minha ideia', $nota->titulo);
        $this->assertSame("primeira linha\nsegunda linha", $nota->descricao);
        $this->assertFalse($nota->fixada);
        $this->assertFalse($nota->arquivada);
        $this->assertSame(TipoAparencia::Cor, $nota->tipo_aparencia);
        $this->assertNull($nota->cor);
        $this->assertNull($nota->caminho_imagem);
    }

    public function test_titulo_e_descricao_sao_opcionais_individualmente(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->post(route('notas.store'), [
            'titulo' => 'Somente título',
            'descricao' => '',
        ])->assertSessionHasNoErrors();

        $this->actingAs($usuario)->post(route('notas.store'), [
            'titulo' => '',
            'descricao' => "Somente\ndescrição",
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, $usuario->notas()->count());
    }

    public function test_nota_sem_conteudo_e_rejeitada_em_portugues(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->from(route('notas.inicio'))->post(route('notas.store'), [
            'titulo' => '   ',
            'descricao' => " \n ",
        ])->assertRedirect(route('notas.inicio'))
            ->assertSessionHasErrorsIn('criacaoNota', ['titulo', 'descricao']);

        $this->assertDatabaseCount('notas', 0);
    }

    public function test_limites_sao_validados_e_texto_digitado_e_preservado(): void
    {
        $usuario = User::factory()->create();
        $titulo = str_repeat('a', 256);
        $descricao = str_repeat('b', 10001);

        $this->actingAs($usuario)->from(route('notas.inicio'))->post(route('notas.store'), compact('titulo', 'descricao'))
            ->assertSessionHasErrorsIn('criacaoNota', ['titulo', 'descricao'])
            ->assertSessionHasInput('titulo', $titulo)
            ->assertSessionHasInput('descricao', $descricao);

        $this->assertDatabaseCount('notas', 0);
    }

    public function test_listagem_mostra_apenas_notas_ativas_do_proprio_usuario_em_ordem_deterministica(): void
    {
        $usuario = User::factory()->create();
        $outroUsuario = User::factory()->create();

        $antiga = $usuario->notas()->create(['titulo' => 'Minha antiga']);
        $recente = $usuario->notas()->create(['titulo' => 'Minha recente']);
        $arquivada = $usuario->notas()->create(['titulo' => 'Minha arquivada']);
        $arquivada->forceFill(['arquivada' => true])->save();
        $outroUsuario->notas()->create(['titulo' => 'Segredo alheio']);
        $antiga->forceFill(['updated_at' => now()->subDay()])->saveQuietly();
        $recente->forceFill(['updated_at' => now()])->saveQuietly();

        $this->actingAs($usuario)->get(route('notas.inicio'))
            ->assertOk()
            ->assertSeeInOrder(['Minha recente', 'Minha antiga'])
            ->assertDontSee('Minha arquivada')
            ->assertDontSee('Segredo alheio');
    }

    public function test_conteudo_da_nota_e_escapado_na_listagem(): void
    {
        $usuario = User::factory()->create();
        $usuario->notas()->create([
            'titulo' => '<script>alert("titulo")</script>',
            'descricao' => '<img src=x onerror=alert("descricao")>',
        ]);

        $this->actingAs($usuario)->get(route('notas.inicio'))
            ->assertSee('&lt;script&gt;alert(&quot;titulo&quot;)&lt;/script&gt;', false)
            ->assertSee('&lt;img src=x onerror=alert(&quot;descricao&quot;)&gt;', false)
            ->assertDontSee('<script>', false)
            ->assertDontSee('<img src=x', false);
    }

    public function test_usuario_pode_abrir_e_editar_a_propria_nota(): void
    {
        $usuario = User::factory()->create();
        $nota = $usuario->notas()->create(['titulo' => 'Antes', 'descricao' => 'Texto anterior']);

        $this->actingAs($usuario)->getJson(route('notas.show', $nota))
            ->assertOk()
            ->assertJsonPath('titulo', 'Antes')
            ->assertJsonPath('descricao', 'Texto anterior');

        $this->actingAs($usuario)->patchJson(route('notas.update', $nota), [
            'revisao' => $nota->revisao,
            'tipo_conteudo' => 'texto',
            'titulo' => '  Depois  ',
            'descricao' => "  linha um\nlinha dois  ",
            'usuario_id' => User::factory()->create()->id,
            'fixada' => true,
        ])->assertOk()
            ->assertJsonPath('mensagem', 'Nota atualizada com sucesso.');

        $nota->refresh();
        $this->assertSame($usuario->id, $nota->usuario_id);
        $this->assertSame('Depois', $nota->titulo);
        $this->assertSame("linha um\nlinha dois", $nota->descricao);
        $this->assertFalse($nota->fixada);
    }

    public function test_edicao_invalida_nao_altera_a_nota(): void
    {
        $usuario = User::factory()->create();
        $nota = $usuario->notas()->create(['titulo' => 'Preservada', 'descricao' => null]);

        $this->actingAs($usuario)->patchJson(route('notas.update', $nota), [
            'revisao' => $nota->revisao,
            'tipo_conteudo' => 'texto',
            'titulo' => '   ',
            'descricao' => " \n ",
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['titulo', 'descricao']);

        $this->assertSame('Preservada', $nota->fresh()->titulo);
    }

    public function test_usuario_nao_pode_visualizar_nem_editar_nota_de_outra_conta(): void
    {
        $dono = User::factory()->create();
        $intruso = User::factory()->create();
        $nota = $dono->notas()->create(['titulo' => 'Privada', 'descricao' => 'Segredo']);

        $this->actingAs($intruso)->getJson(route('notas.show', $nota))->assertForbidden();
        $this->actingAs($intruso)->patchJson(route('notas.update', $nota), [
            'revisao' => $nota->revisao,
            'tipo_conteudo' => 'texto',
            'titulo' => 'Invadida',
            'descricao' => 'Alterada',
        ])->assertForbidden();

        $this->assertSame('Privada', $nota->fresh()->titulo);
        $this->assertSame('Segredo', $nota->descricao);
    }

    public function test_excluir_conta_remove_suas_notas_inclusive_as_excluidas_logicamente(): void
    {
        $usuario = User::factory()->create();
        $ativa = $usuario->notas()->create(['titulo' => 'Ativa']);
        $naLixeira = $usuario->notas()->create(['titulo' => 'Na lixeira']);
        $naLixeira->delete();

        $usuario->delete();

        $this->assertDatabaseMissing('notas', ['id' => $ativa->id]);
        $this->assertDatabaseMissing('notas', ['id' => $naLixeira->id]);
    }

    public function test_pagina_contem_token_csrf_nos_formularios_de_mutacao(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->get(route('notas.inicio'))
            ->assertOk()
            ->assertSee('name="_token"', false)
            ->assertSee('meta name="csrf-token"', false);
    }

    public function test_tabela_inclui_campos_planejados_e_soft_deletes(): void
    {
        foreach (['id', 'usuario_id', 'titulo', 'descricao', 'fixada', 'arquivada', 'tipo_aparencia', 'cor', 'caminho_imagem', 'created_at', 'updated_at', 'deleted_at'] as $campo) {
            $this->assertTrue(Schema::hasColumn('notas', $campo));
        }
    }
}
