<?php

namespace Tests\Feature;

use App\Enums\CorNota;
use App\Enums\FundoNota;
use App\Enums\TipoAparencia;
use App\Models\Nota;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotaLixeiraTest extends TestCase
{
    use RefreshDatabase;

    public function test_move_notas_ativa_e_arquivada_preservando_todos_os_estados(): void
    {
        $usuario = User::factory()->create();
        $ativa = $usuario->notas()->create(['titulo' => 'Ativa', 'descricao' => 'Texto']);
        $ativa->forceFill(['fixada' => true, 'cor' => CorNota::Ceu])->save();
        $arquivada = $usuario->notas()->create(['titulo' => 'Arquivada']);
        $arquivada->forceFill(['arquivada' => true, 'tipo_aparencia' => TipoAparencia::Imagem, 'caminho_imagem' => FundoNota::Ondas->caminho()])->save();

        $this->actingAs($usuario)->delete(route('notas.mover-lixeira', $ativa), ['titulo' => 'Manipulado'])
            ->assertRedirect(route('notas.inicio'))->assertSessionHas('sucesso', 'Nota movida para a lixeira.');
        $this->actingAs($usuario)->delete(route('notas.mover-lixeira', $arquivada))
            ->assertRedirect(route('notas.arquivadas'));

        $ativa = Nota::onlyTrashed()->findOrFail($ativa->id);
        $arquivada = Nota::onlyTrashed()->findOrFail($arquivada->id);
        $this->assertSame('Ativa', $ativa->titulo);
        $this->assertTrue($ativa->fixada);
        $this->assertSame(CorNota::Ceu, $ativa->cor);
        $this->assertFalse($ativa->arquivada);
        $this->assertTrue($arquivada->arquivada);
        $this->assertSame(FundoNota::Ondas->caminho(), $arquivada->caminho_imagem);
    }

    public function test_lixeira_lista_somente_removidas_do_usuario_em_ordem_deterministica(): void
    {
        $usuario = User::factory()->create();
        $outro = User::factory()->create();
        $antiga = $usuario->notas()->create(['titulo' => 'Removida antiga']);
        $recente = $usuario->notas()->create(['titulo' => 'Removida recente']);
        $ativa = $usuario->notas()->create(['titulo' => 'Ainda ativa']);
        $alheia = $outro->notas()->create(['titulo' => 'Removida alheia']);
        $antiga->delete();
        $recente->delete();
        $alheia->delete();
        Nota::onlyTrashed()->whereKey($antiga->id)->update(['deleted_at' => now()->subDay()]);

        $this->actingAs($usuario)->get(route('lixeira.index'))->assertOk()
            ->assertSeeInOrder(['Removida recente', 'Removida antiga'])
            ->assertDontSee($ativa->titulo)->assertDontSee($alheia->titulo)
            ->assertSee('aria-current="page"', false);
    }

    public function test_leitura_na_lixeira_e_somente_do_proprietario(): void
    {
        $dono = User::factory()->create();
        $intruso = User::factory()->create();
        $nota = $dono->notas()->create(['titulo' => 'Segredo', 'descricao' => '<b>texto</b>']);
        $nota->delete();

        $this->actingAs($dono)->getJson(route('lixeira.show', $nota->id))->assertOk()
            ->assertJsonPath('titulo', 'Segredo')->assertJsonPath('descricao', '<b>texto</b>');
        $this->actingAs($intruso)->getJson(route('lixeira.show', $nota->id))->assertForbidden();
    }

    public function test_restauracao_mantem_id_aparencia_fixacao_e_destino_anterior(): void
    {
        $usuario = User::factory()->create();
        $ativa = $usuario->notas()->create(['titulo' => 'Volta principal']);
        $ativa->forceFill(['fixada' => true, 'cor' => CorNota::Menta])->save();
        $arquivada = $usuario->notas()->create(['titulo' => 'Volta arquivo']);
        $arquivada->forceFill(['arquivada' => true, 'tipo_aparencia' => TipoAparencia::Imagem, 'caminho_imagem' => FundoNota::Geometria->caminho()])->save();
        $ativa->delete();
        $arquivada->delete();

        $this->actingAs($usuario)->patch(route('lixeira.restaurar', $ativa->id))
            ->assertSessionHas('sucesso', 'Nota restaurada para Minhas notas.');
        $this->actingAs($usuario)->patch(route('lixeira.restaurar', $arquivada->id))
            ->assertSessionHas('sucesso', 'Nota restaurada para Arquivadas.');

        $this->assertSame($ativa->id, $ativa->fresh()->id);
        $this->assertTrue($ativa->fresh()->fixada);
        $this->assertSame(CorNota::Menta, $ativa->fresh()->cor);
        $this->assertTrue($arquivada->fresh()->arquivada);
        $this->assertSame(FundoNota::Geometria->caminho(), $arquivada->fresh()->caminho_imagem);
        $this->assertDatabaseCount('notas', 2);
    }

    public function test_endpoints_normais_rejeitam_nota_removida_de_aba_antiga(): void
    {
        $usuario = User::factory()->create();
        $nota = $usuario->notas()->create(['titulo' => 'Removida']);
        $nota->delete();

        $this->actingAs($usuario)->getJson(route('notas.show', $nota->id))->assertNotFound();
        $this->actingAs($usuario)->patchJson(route('notas.update', $nota->id), ['titulo' => 'Alterada', 'descricao' => 'x'])->assertNotFound();
        $this->actingAs($usuario)->patch(route('notas.fixacao', $nota->id), ['fixada' => true])->assertNotFound();
        $this->actingAs($usuario)->patch(route('notas.arquivamento', $nota->id), ['arquivada' => true])->assertNotFound();
    }

    public function test_confirmacao_get_nao_muta_cancelamento_preserva_e_ativa_nao_pode_ser_excluida_definitivamente(): void
    {
        $usuario = User::factory()->create();
        $removida = $usuario->notas()->create(['titulo' => 'Identifique esta nota', 'descricao' => 'Trecho de confirmação']);
        $ativa = $usuario->notas()->create(['titulo' => 'Ativa protegida']);
        $removida->delete();

        $this->actingAs($usuario)->get(route('lixeira.confirmar-exclusao', $removida->id))->assertOk()
            ->assertSee('Identifique esta nota')->assertSee('não poderá ser desfeita');
        $this->assertSoftDeleted($removida);
        $this->actingAs($usuario)->get(route('lixeira.index'))->assertOk();
        $this->assertSoftDeleted($removida);
        $this->actingAs($usuario)->delete(route('lixeira.destruir', $ativa->id))->assertNotFound();
        $this->assertDatabaseHas('notas', ['id' => $ativa->id]);
    }

    public function test_exclusao_definitiva_remove_apenas_nota_e_preserva_fundo_compartilhado(): void
    {
        $usuario = User::factory()->create();
        $excluir = $usuario->notas()->create(['titulo' => 'Excluir']);
        $preservar = $usuario->notas()->create(['titulo' => 'Preservar']);
        foreach ([$excluir, $preservar] as $nota) {
            $nota->forceFill(['tipo_aparencia' => TipoAparencia::Imagem, 'caminho_imagem' => FundoNota::Folhas->caminho()])->save();
        }
        $excluir->delete();

        $this->actingAs($usuario)->delete(route('lixeira.destruir', $excluir->id))
            ->assertRedirect(route('lixeira.index'))->assertSessionHas('sucesso', 'Nota excluída definitivamente.');

        $this->assertNull(Nota::withTrashed()->find($excluir->id));
        $this->assertDatabaseHas('notas', ['id' => $preservar->id, 'caminho_imagem' => FundoNota::Folhas->caminho()]);
        $this->assertFileExists(public_path(FundoNota::Folhas->caminho()));
    }

    public function test_visitante_e_segunda_conta_nao_podem_restaurar_confirmar_ou_excluir_nota_alheia(): void
    {
        $dono = User::factory()->create();
        $intruso = User::factory()->create();
        $nota = $dono->notas()->create(['titulo' => 'Privada']);
        $nota->delete();

        $this->patch(route('lixeira.restaurar', $nota->id))->assertRedirect(route('login'));
        $this->actingAs($intruso)->get(route('lixeira.confirmar-exclusao', $nota->id))->assertForbidden();
        $this->actingAs($intruso)->patch(route('lixeira.restaurar', $nota->id))->assertForbidden();
        $this->actingAs($intruso)->delete(route('lixeira.destruir', $nota->id))->assertForbidden();
        $this->assertSoftDeleted($nota);
    }

    public function test_estado_vazio_da_lixeira(): void
    {
        $this->actingAs(User::factory()->create())->get(route('lixeira.index'))->assertOk()
            ->assertSee('A lixeira está vazia.');
    }
}
