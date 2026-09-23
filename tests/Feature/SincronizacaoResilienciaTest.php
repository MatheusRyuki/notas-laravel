<?php

namespace Tests\Feature;

use App\Enums\TipoNota;
use App\Models\Nota;
use App\Models\OperacaoSincronizacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SincronizacaoResilienciaTest extends TestCase
{
    use RefreshDatabase;

    public function test_repetir_atualizacao_apos_resposta_perdida_nao_reaplica_a_alteracao(): void
    {
        $usuario = User::factory()->create();
        $nota = $this->nota($usuario);
        $revisaoInicial = $nota->revisao;
        $operacaoUuid = (string) Str::uuid();
        $corpo = $this->atualizacao($usuario, $nota, $operacaoUuid, $revisaoInicial, 'Texto confirmado');

        $primeira = $this->actingAs($usuario)->postJson(route('sincronizacao.executar'), $corpo)
            ->assertOk()
            ->assertJsonPath('resultados.0.status', 'sincronizado')
            ->json('resultados.0');
        $segunda = $this->postJson(route('sincronizacao.executar'), $corpo)
            ->assertOk()
            ->assertJsonPath('resultados.0.status', 'sincronizado')
            ->json('resultados.0');

        $this->assertSame($primeira, $segunda);
        $this->assertSame($revisaoInicial + 1, $nota->fresh()->revisao);
        $this->assertSame('Texto confirmado', $nota->fresh()->titulo);
        $this->assertSame(1, OperacaoSincronizacao::where('operacao_uuid', $operacaoUuid)->count());
    }

    public function test_mudanca_de_editor_para_leitor_recusa_pendencia_e_devolve_estado_atual(): void
    {
        $dono = User::factory()->create();
        $editor = User::factory()->create();
        $nota = $this->nota($dono);
        $nota->participantes()->attach($editor->id, ['papel' => 'editor']);
        $revisaoOffline = $nota->revisao;

        $nota->participantes()->updateExistingPivot($editor->id, ['papel' => 'leitor']);

        $this->actingAs($editor)
            ->postJson(route('sincronizacao.executar'), $this->atualizacao(
                $editor,
                $nota,
                (string) Str::uuid(),
                $revisaoOffline,
                'Alteração offline recusada',
            ))
            ->assertOk()
            ->assertJsonPath('resultados.0.status', 'somente_leitura')
            ->assertJsonPath('resultados.0.nota.titulo', 'Original')
            ->assertJsonPath('resultados.0.nota.papel', 'leitor')
            ->assertJsonPath('resultados.0.nota.pode_editar', false);

        $this->assertSame('Original', $nota->fresh()->titulo);
        $this->assertSame($revisaoOffline, $nota->fresh()->revisao);
    }

    public function test_revogacao_e_remocao_durante_periodo_offline_nao_recriam_nota_ou_acesso(): void
    {
        $dono = User::factory()->create();
        $editor = User::factory()->create();

        $revogada = $this->nota($dono, 'Revogada');
        $revogada->participantes()->attach($editor->id, ['papel' => 'editor']);
        $corpoRevogada = $this->atualizacao(
            $editor,
            $revogada,
            (string) Str::uuid(),
            $revogada->revisao,
            'Não deve voltar',
        );
        $revogada->participantes()->detach($editor->id);

        $this->actingAs($editor)->postJson(route('sincronizacao.executar'), $corpoRevogada)
            ->assertOk()
            ->assertJsonPath('resultados.0.status', 'revogado_ou_excluido');

        $removida = $this->nota($dono, 'Removida');
        $removida->participantes()->attach($editor->id, ['papel' => 'editor']);
        $corpoRemovida = $this->atualizacao(
            $editor,
            $removida,
            (string) Str::uuid(),
            $removida->revisao,
            'Não deve ressurgir',
        );
        $removida->delete();

        $this->postJson(route('sincronizacao.executar'), $corpoRemovida)
            ->assertOk()
            ->assertJsonPath('resultados.0.status', 'revogado_ou_excluido');

        $this->assertSame('Revogada', $revogada->fresh()->titulo);
        $this->assertFalse($revogada->participantes()->whereKey($editor->id)->exists());
        $this->assertSame('Removida', Nota::withTrashed()->findOrFail($removida->id)->titulo);
        $this->assertTrue(Nota::withTrashed()->findOrFail($removida->id)->trashed());
        $this->assertSame(2, Nota::withTrashed()->count());
    }

    public function test_tentativa_de_resolver_conflito_revalida_revisao_e_permissao(): void
    {
        $dono = User::factory()->create();
        $editor = User::factory()->create();
        $nota = $this->nota($dono);
        $nota->participantes()->attach($editor->id, ['papel' => 'editor']);
        $revisaoOffline = $nota->revisao;

        $nota->forceFill(['titulo' => 'Versão atual', 'revisao' => $revisaoOffline + 1])->save();

        $this->actingAs($editor)->postJson(
            route('sincronizacao.executar'),
            $this->atualizacao($editor, $nota, (string) Str::uuid(), $revisaoOffline, 'Minha versão'),
        )->assertOk()
            ->assertJsonPath('resultados.0.status', 'conflito')
            ->assertJsonPath('resultados.0.local.titulo', 'Minha versão')
            ->assertJsonPath('resultados.0.atual.titulo', 'Versão atual');

        $nota->participantes()->updateExistingPivot($editor->id, ['papel' => 'leitor']);

        $this->postJson(
            route('sincronizacao.executar'),
            $this->atualizacao($editor, $nota, (string) Str::uuid(), $nota->fresh()->revisao, 'Minha versão'),
        )->assertOk()
            ->assertJsonPath('resultados.0.status', 'somente_leitura');

        $this->assertSame('Versão atual', $nota->fresh()->titulo);
    }

    private function nota(User $usuario, string $titulo = 'Original'): Nota
    {
        $nota = new Nota;
        $nota->forceFill([
            'titulo' => $titulo,
            'descricao' => 'Descrição de teste',
            'tipo_conteudo' => TipoNota::Texto,
            'fixada' => false,
            'arquivada' => false,
            'tipo_aparencia' => 'cor',
            'cor' => null,
            'caminho_imagem' => null,
        ]);
        $usuario->notas()->save($nota);

        return $nota;
    }

    /** @return array<string, mixed> */
    private function atualizacao(
        User $usuario,
        Nota $nota,
        string $operacaoUuid,
        int $revisao,
        string $titulo,
    ): array {
        return [
            'conta_id' => $usuario->id,
            'operacoes' => [[
                'uuid' => $operacaoUuid,
                'acao' => 'atualizar',
                'nota_uuid' => $nota->uuid_sincronizacao,
                'revisao_base' => $revisao,
                'payload' => [
                    'titulo' => $titulo,
                    'descricao' => 'Descrição de teste',
                    'tipo_conteudo' => 'texto',
                    'itens' => [],
                    'tipo_aparencia' => 'cor',
                    'cor' => 'padrao',
                    'fundo' => null,
                ],
            ]],
        ];
    }
}
