<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdentidadeItensNotaTest extends TestCase
{
    use RefreshDatabase;

    public function test_edicao_e_sincronizacao_preservam_ids_e_isolam_itens_de_outra_nota(): void
    {
        $usuario = User::factory()->create();
        $nota = $usuario->notas()->create(['titulo' => 'Lista', 'tipo_conteudo' => 'lista']);
        $primeiro = $nota->itens()->create(['texto' => 'Primeiro', 'concluido' => false, 'posicao' => 0]);
        $segundo = $nota->itens()->create(['texto' => 'Segundo', 'concluido' => false, 'posicao' => 1]);
        $removido = $nota->itens()->create(['texto' => 'Remover', 'concluido' => false, 'posicao' => 2]);
        $outra = User::factory()->create()->notas()->create(['tipo_conteudo' => 'lista']);
        $alheio = $outra->itens()->create(['texto' => 'Privado', 'concluido' => false, 'posicao' => 0]);
        $dados = [
            'titulo' => 'Lista editada',
            'tipo_conteudo' => 'lista',
            'tipo_aparencia' => 'cor',
            'cor' => 'padrao',
            'itens' => [
                ['id' => $segundo->id, 'texto' => 'Segundo editado', 'concluido' => true],
                ['id' => $primeiro->id, 'texto' => 'Primeiro', 'concluido' => false],
                ['id' => $alheio->id, 'texto' => 'Novo item', 'concluido' => false],
            ],
        ];
        $this->actingAs($usuario)->patchJson(route('notas.update', $nota), [
            ...$dados, 'revisao' => $nota->revisao,
        ])->assertOk()->assertJsonPath('nota.itens.0.id', $segundo->id)
            ->assertJsonPath('nota.itens.1.id', $primeiro->id);
        $this->assertDatabaseMissing('nota_itens', ['id' => $removido->id]);
        $this->assertSame('Privado', $alheio->fresh()->texto);

        $ids = $nota->itens()->pluck('id')->all();
        $dados['itens'][2]['id'] = $ids[2];
        $dados['itens'][0]['texto'] = 'Sincronizado';
        $this->postJson(route('sincronizacao.executar'), [
            'conta_id' => $usuario->id,
            'operacoes' => [[
                'uuid' => (string) Str::uuid(),
                'acao' => 'atualizar',
                'nota_uuid' => $nota->uuid_sincronizacao,
                'revisao_base' => $nota->fresh()->revisao,
                'payload' => $dados,
            ]],
        ])->assertOk()->assertJsonPath('resultados.0.status', 'sincronizado')
            ->assertJsonPath('resultados.0.nota.itens.0.id', $segundo->id)
            ->assertJsonPath('resultados.0.nota.itens.0.texto', 'Sincronizado');
        $this->assertSame($ids, $nota->itens()->pluck('id')->all());
        $this->assertSame('Privado', $alheio->fresh()->texto);
    }

    public function test_sync_nao_reaproveita_item_de_outra_nota(): void
    {
        $usuario = User::factory()->create();
        $notaA = $usuario->notas()->create(['titulo' => 'Nota A', 'tipo_conteudo' => 'lista']);
        $notaB = $usuario->notas()->create(['titulo' => 'Nota B', 'tipo_conteudo' => 'lista']);
        $itemB = $notaB->itens()->create(['texto' => 'Original de B', 'concluido' => false, 'posicao' => 0]);

        $resposta = $this->actingAs($usuario)->postJson(route('sincronizacao.executar'), [
            'conta_id' => $usuario->id,
            'operacoes' => [[
                'uuid' => (string) Str::uuid(),
                'acao' => 'atualizar',
                'nota_uuid' => $notaA->uuid_sincronizacao,
                'revisao_base' => $notaA->revisao,
                'payload' => [
                    'titulo' => 'Nota A',
                    'tipo_conteudo' => 'lista',
                    'tipo_aparencia' => 'cor',
                    'cor' => 'padrao',
                    'itens' => [['id' => $itemB->id, 'texto' => 'Novo de A', 'concluido' => true]],
                ],
            ]],
        ])->assertOk()->assertJsonPath('resultados.0.status', 'sincronizado');

        $itemA = $notaA->itens()->sole();
        $this->assertNotSame($itemB->id, $itemA->id);
        $this->assertSame($notaA->id, $itemA->nota_id);
        $this->assertSame('Novo de A', $itemA->texto);
        $resposta->assertJsonPath('resultados.0.nota.itens.0.id', $itemA->id);
        $itemB->refresh();
        $this->assertSame('Original de B', $itemB->texto);
        $this->assertSame($notaB->id, $itemB->nota_id);
        $this->assertFalse($itemB->concluido);
    }
}
