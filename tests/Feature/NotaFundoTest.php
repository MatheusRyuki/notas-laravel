<?php

namespace Tests\Feature;

use App\Enums\CorNota;
use App\Enums\FundoNota;
use App\Enums\TipoAparencia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotaFundoTest extends TestCase
{
    use RefreshDatabase;

    public function test_criacao_com_fundo_resolve_identificador_para_caminho_do_catalogo(): void
    {
        $usuario = User::factory()->create();
        $fundo = FundoNota::Folhas;

        $this->actingAs($usuario)->post(route('notas.store'), [
            'titulo' => 'Com folhas',
            'descricao' => "Linha curta\nOutra linha",
            'tipo_aparencia' => TipoAparencia::Imagem->value,
            'cor' => CorNota::Menta->value,
            'fundo' => $fundo->value,
            'caminho_imagem' => 'https://exemplo.test/invasao.svg',
        ])->assertRedirect(route('notas.inicio'))->assertSessionHasNoErrors();

        $nota = $usuario->notas()->sole();
        $this->assertSame(TipoAparencia::Imagem, $nota->tipo_aparencia);
        $this->assertNull($nota->cor);
        $this->assertSame($fundo->caminho(), $nota->caminho_imagem);
        $this->assertSame($fundo, $nota->fundo());
    }

    public function test_edicao_troca_entre_fundos_e_retorna_para_cor_e_padrao(): void
    {
        $usuario = User::factory()->create();
        $nota = $usuario->notas()->create(['titulo' => 'Alternável']);
        $nota->forceFill([
            'tipo_aparencia' => TipoAparencia::Imagem,
            'cor' => null,
            'caminho_imagem' => FundoNota::Ondas->caminho(),
        ])->save();

        $this->actingAs($usuario)->patchJson(route('notas.update', $nota), [
            'revisao' => $nota->revisao,
            'tipo_conteudo' => 'texto',
            'titulo' => 'Alternável',
            'descricao' => '',
            'tipo_aparencia' => 'imagem',
            'cor' => 'padrao',
            'fundo' => FundoNota::Constelacao->value,
        ])->assertOk()->assertJsonPath('nota.fundo', FundoNota::Constelacao->value);

        $nota->refresh();
        $this->assertSame(FundoNota::Constelacao->caminho(), $nota->caminho_imagem);

        $this->actingAs($usuario)->patchJson(route('notas.update', $nota), [
            'revisao' => $nota->revisao,
            'tipo_conteudo' => 'texto',
            'titulo' => 'Agora em areia',
            'descricao' => '',
            'tipo_aparencia' => 'cor',
            'cor' => CorNota::Areia->value,
            'fundo' => FundoNota::Folhas->value,
        ])->assertOk()->assertJsonPath('nota.cor', CorNota::Areia->value);

        $nota->refresh();
        $this->assertSame(TipoAparencia::Cor, $nota->tipo_aparencia);
        $this->assertSame(CorNota::Areia, $nota->cor);
        $this->assertNull($nota->caminho_imagem);

        $this->actingAs($usuario)->patchJson(route('notas.update', $nota), [
            'revisao' => $nota->revisao,
            'tipo_conteudo' => 'texto',
            'titulo' => 'Agora padrão',
            'descricao' => '',
            'tipo_aparencia' => 'cor',
            'cor' => CorNota::Padrao->value,
            'fundo' => FundoNota::Folhas->value,
        ])->assertOk();

        $nota->refresh();
        $this->assertSame(TipoAparencia::Cor, $nota->tipo_aparencia);
        $this->assertNull($nota->cor);
        $this->assertNull($nota->caminho_imagem);
    }

    public function test_identificador_de_fundo_caminho_livre_e_tipo_desconhecidos_sao_rejeitados(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->post(route('notas.store'), [
            'titulo' => 'Não criar',
            'tipo_aparencia' => 'imagem',
            'fundo' => 'https://externo.test/fundo.svg',
            'cor' => 'padrao',
        ])->assertSessionHasErrorsIn('criacaoNota', ['fundo']);

        $this->actingAs($usuario)->post(route('notas.store'), [
            'titulo' => 'Também não criar',
            'tipo_aparencia' => 'gradiente-css',
            'fundo' => FundoNota::Folhas->value,
            'cor' => 'padrao',
        ])->assertSessionHasErrorsIn('criacaoNota', ['tipo_aparencia']);

        $this->assertDatabaseCount('notas', 0);
    }

    public function test_editar_apenas_texto_e_fixar_nao_apagam_fundo_existente(): void
    {
        $usuario = User::factory()->create();
        $nota = $usuario->notas()->create(['titulo' => 'Antes']);
        $nota->forceFill([
            'tipo_aparencia' => TipoAparencia::Imagem,
            'cor' => null,
            'caminho_imagem' => FundoNota::Geometria->caminho(),
        ])->save();

        $this->actingAs($usuario)->patchJson(route('notas.update', $nota), [
            'revisao' => $nota->revisao,
            'tipo_conteudo' => 'texto',
            'titulo' => 'Depois',
            'descricao' => 'Somente o texto mudou.',
        ])->assertOk();

        $this->actingAs($usuario)->patch(route('notas.fixacao', $nota), ['fixada' => true])
            ->assertRedirect(route('notas.inicio'));

        $nota->refresh();
        $this->assertSame('Depois', $nota->titulo);
        $this->assertTrue($nota->fixada);
        $this->assertSame(TipoAparencia::Imagem, $nota->tipo_aparencia);
        $this->assertNull($nota->cor);
        $this->assertSame(FundoNota::Geometria->caminho(), $nota->caminho_imagem);
    }

    public function test_segunda_conta_nao_pode_consultar_nem_alterar_fundo_de_outra_pessoa(): void
    {
        $dono = User::factory()->create();
        $intruso = User::factory()->create();
        $nota = $dono->notas()->create(['titulo' => 'Privada']);
        $nota->forceFill([
            'tipo_aparencia' => TipoAparencia::Imagem,
            'caminho_imagem' => FundoNota::Folhas->caminho(),
        ])->save();

        $this->actingAs($intruso)->getJson(route('notas.show', $nota))->assertForbidden();
        $this->actingAs($intruso)->patchJson(route('notas.update', $nota), [
            'revisao' => $nota->revisao,
            'tipo_conteudo' => 'texto',
            'titulo' => 'Invadida',
            'descricao' => '',
            'tipo_aparencia' => 'imagem',
            'fundo' => FundoNota::Ondas->value,
            'cor' => 'padrao',
        ])->assertForbidden();

        $nota->refresh();
        $this->assertSame('Privada', $nota->titulo);
        $this->assertSame(FundoNota::Folhas->caminho(), $nota->caminho_imagem);
    }

    public function test_catalogo_tem_arquivos_svg_locais_e_cartao_exibe_fundo_seguro(): void
    {
        foreach (FundoNota::cases() as $fundo) {
            $this->assertFileExists(public_path($fundo->caminho()));
            $this->assertStringEndsWith('.svg', $fundo->caminho());
        }

        $usuario = User::factory()->create();
        $nota = $usuario->notas()->create(['titulo' => '<script>alert(1)</script>']);
        $nota->forceFill([
            'tipo_aparencia' => TipoAparencia::Imagem,
            'caminho_imagem' => FundoNota::Ondas->caminho(),
        ])->save();

        $this->actingAs($usuario)->get(route('notas.inicio'))
            ->assertOk()
            ->assertSee('data-tipo-aparencia="imagem"', false)
            ->assertSee('data-fundo="ondas"', false)
            ->assertSee(asset(FundoNota::Ondas->caminho()), false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('Folhas tranquilas')
            ->assertSee('Céu pontilhado');
    }
}
