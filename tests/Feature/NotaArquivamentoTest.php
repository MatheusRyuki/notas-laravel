<?php

namespace Tests\Feature;

use App\Enums\FundoNota;
use App\Enums\TipoAparencia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotaArquivamentoTest extends TestCase
{
    use RefreshDatabase;

    public function test_arquivar_e_repetir_estado_preserva_todos_os_outros_campos(): void
    {
        $usuario = User::factory()->create();
        $nota = $usuario->notas()->create(['titulo' => 'Guardar', 'descricao' => "Linha 1\nLinha 2"]);
        $nota->forceFill([
            'fixada' => true,
            'tipo_aparencia' => TipoAparencia::Imagem,
            'cor' => null,
            'caminho_imagem' => FundoNota::Folhas->caminho(),
        ])->save();

        $payloadManipulado = [
            'arquivada' => true,
            'titulo' => 'Não alterar',
            'descricao' => 'Não alterar',
            'fixada' => false,
            'usuario_id' => User::factory()->create()->id,
            'caminho_imagem' => 'https://externo.test/fundo.svg',
            'deleted_at' => now(),
        ];

        $this->actingAs($usuario)->patch(route('notas.arquivamento', $nota), $payloadManipulado)
            ->assertRedirect(route('notas.inicio'))
            ->assertSessionHas('sucesso', 'Nota arquivada com sucesso.');

        $this->actingAs($usuario)->patch(route('notas.arquivamento', $nota), $payloadManipulado)
            ->assertRedirect(route('notas.inicio'));

        $nota->refresh();
        $this->assertTrue($nota->arquivada);
        $this->assertTrue($nota->fixada);
        $this->assertSame('Guardar', $nota->titulo);
        $this->assertSame("Linha 1\nLinha 2", $nota->descricao);
        $this->assertSame($usuario->id, $nota->usuario_id);
        $this->assertSame(TipoAparencia::Imagem, $nota->tipo_aparencia);
        $this->assertSame(FundoNota::Folhas->caminho(), $nota->caminho_imagem);
        $this->assertNull($nota->deleted_at);
    }

    public function test_desarquivar_mantem_fixacao_e_nota_retorna_ao_grupo_fixadas(): void
    {
        $usuario = User::factory()->create();
        $nota = $usuario->notas()->create(['titulo' => 'Fixada guardada']);
        $nota->forceFill(['fixada' => true, 'arquivada' => true])->save();

        $this->actingAs($usuario)->patch(route('notas.arquivamento', $nota), ['arquivada' => false])
            ->assertRedirect(route('notas.arquivadas'))
            ->assertSessionHas('sucesso', 'Nota desarquivada com sucesso.');

        $nota->refresh();
        $this->assertFalse($nota->arquivada);
        $this->assertTrue($nota->fixada);

        $this->actingAs($usuario)->get(route('notas.inicio'))
            ->assertOk()
            ->assertSeeInOrder(['Fixadas', 'Fixada guardada']);
    }

    public function test_listagens_separam_ativas_arquivadas_removidas_e_usuarios(): void
    {
        $usuario = User::factory()->create();
        $outro = User::factory()->create();
        $ativa = $usuario->notas()->create(['titulo' => 'Ativa própria']);
        $arquivada = $usuario->notas()->create(['titulo' => 'Arquivada própria']);
        $arquivada->forceFill(['arquivada' => true])->save();
        $removida = $usuario->notas()->create(['titulo' => 'Removida própria']);
        $removida->delete();
        $outraArquivada = $outro->notas()->create(['titulo' => 'Arquivada alheia']);
        $outraArquivada->forceFill(['arquivada' => true])->save();

        $this->actingAs($usuario)->get(route('notas.inicio'))
            ->assertOk()
            ->assertSee($ativa->titulo)
            ->assertDontSee($arquivada->titulo)
            ->assertDontSee($removida->titulo)
            ->assertDontSee($outraArquivada->titulo);

        $this->actingAs($usuario)->get(route('notas.arquivadas'))
            ->assertOk()
            ->assertSee($arquivada->titulo)
            ->assertDontSee($ativa->titulo)
            ->assertDontSee($removida->titulo)
            ->assertDontSee($outraArquivada->titulo)
            ->assertSee('aria-current="page"', false);
    }

    public function test_edicao_de_nota_arquivada_preserva_estado_e_contexto(): void
    {
        $usuario = User::factory()->create();
        $nota = $usuario->notas()->create(['titulo' => 'Texto antigo']);
        $nota->forceFill([
            'arquivada' => true,
            'tipo_aparencia' => TipoAparencia::Imagem,
            'caminho_imagem' => FundoNota::Ondas->caminho(),
        ])->save();

        $this->actingAs($usuario)->patchJson(route('notas.update', $nota), [
            'revisao' => $nota->revisao,
            'tipo_conteudo' => 'texto',
            'titulo' => 'Texto revisado',
            'descricao' => 'Continua arquivada.',
        ])->assertOk()->assertJsonPath('nota.arquivada', true);

        $nota->refresh();
        $this->assertTrue($nota->arquivada);
        $this->assertSame(FundoNota::Ondas->caminho(), $nota->caminho_imagem);

        $this->actingAs($usuario)->get(route('notas.arquivadas'))
            ->assertOk()
            ->assertSee('Texto revisado')
            ->assertSee('Desarquivar nota Texto revisado');
    }

    public function test_estados_vazios_sao_especificos_para_cada_secao(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->get(route('notas.inicio'))
            ->assertOk()
            ->assertSee('Crie sua primeira nota.');

        $this->actingAs($usuario)->get(route('notas.arquivadas'))
            ->assertOk()
            ->assertSee('Nenhuma nota arquivada.')
            ->assertSee('Voltar às minhas notas');
    }

    public function test_estado_invalido_e_rejeitado_e_gets_nao_modificam_a_nota(): void
    {
        $usuario = User::factory()->create();
        $nota = $usuario->notas()->create(['titulo' => 'Imutável por GET']);

        $this->actingAs($usuario)->from(route('notas.inicio'))
            ->patch(route('notas.arquivamento', $nota), ['arquivada' => 'talvez'])
            ->assertRedirect(route('notas.inicio'))
            ->assertSessionHasErrorsIn('arquivamentoNota', ['arquivada']);

        $this->actingAs($usuario)->get(route('notas.arquivadas'))->assertOk();
        $this->actingAs($usuario)->getJson(route('notas.show', $nota))->assertOk();
        $this->assertFalse($nota->fresh()->arquivada);
    }

    public function test_visitante_e_segunda_conta_nao_podem_arquivar_nota_alheia(): void
    {
        $dono = User::factory()->create();
        $intruso = User::factory()->create();
        $nota = $dono->notas()->create(['titulo' => 'Privada']);

        $this->patch(route('notas.arquivamento', $nota), ['arquivada' => true])
            ->assertRedirect(route('login'));
        $this->actingAs($intruso)->patch(route('notas.arquivamento', $nota), ['arquivada' => true])
            ->assertForbidden();

        $this->assertFalse($nota->fresh()->arquivada);
    }
}
