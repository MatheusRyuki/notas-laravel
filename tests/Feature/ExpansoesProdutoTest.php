<?php

namespace Tests\Feature;

use App\Enums\TipoNota;
use App\Jobs\EnviarEmailLembrete;
use App\Models\Etiqueta;
use App\Models\Lembrete;
use App\Models\Nota;
use App\Models\OperacaoDesfazer;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExpansoesProdutoTest extends TestCase
{
    use RefreshDatabase;

    public function test_desfazer_arquivamento_e_lixeira_preserva_estado_e_contexto(): void
    {
        $usuario = User::factory()->create();
        $nota = $this->nota($usuario, ['titulo' => 'Planejamento', 'fixada' => true, 'arquivada' => true]);

        $resposta = $this->actingAs($usuario)->delete(route('notas.mover-lixeira', $nota), [
            'q' => 'Plano', 'ordem' => 'titulo',
        ]);
        $token = $resposta->getSession()->get('desfazer');

        $this->assertNotNull($nota->fresh()->deleted_at);
        $this->post(route('desfazer', $token))
            ->assertRedirect(route('notas.arquivadas', ['q' => 'Plano', 'ordem' => 'titulo']));
        $nota->refresh();
        $this->assertFalse($nota->trashed());
        $this->assertTrue($nota->arquivada);
        $this->assertTrue($nota->fixada);

        $this->post(route('desfazer', $token))->assertSessionHasErrors('desfazer', null, 'desfazer');
        $this->assertNotNull(OperacaoDesfazer::where('token', $token)->value('utilizada_em'));
    }

    public function test_desfazer_recusa_expiracao_alteracao_posterior_e_outro_usuario(): void
    {
        Carbon::setTestNow('2026-09-22 12:00:00');
        CarbonImmutable::setTestNow('2026-09-22 12:00:00');
        $usuario = User::factory()->create();
        $outro = User::factory()->create();
        $nota = $this->nota($usuario);

        $resposta = $this->actingAs($usuario)->patch(route('notas.arquivamento', $nota), ['arquivada' => 1]);
        $token = $resposta->getSession()->get('desfazer');

        $this->actingAs($outro)->post(route('desfazer', $token))->assertNotFound();

        Carbon::setTestNow('2026-09-22 12:06:00');
        CarbonImmutable::setTestNow('2026-09-22 12:06:00');
        $this->actingAs($usuario)->post(route('desfazer', $token))->assertSessionHasErrors('desfazer', null, 'desfazer');

        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        $nota = $this->nota($usuario, ['titulo' => 'Original']);
        $resposta = $this->actingAs($usuario)->patch(route('notas.arquivamento', $nota), ['arquivada' => 1]);
        $token = $resposta->getSession()->get('desfazer');
        $nota->forceFill(['titulo' => 'Alterada', 'revisao' => $nota->fresh()->revisao + 1])->save();

        $this->post(route('desfazer', $token))->assertSessionHasErrors('desfazer', null, 'desfazer');
        $this->assertTrue($nota->fresh()->arquivada);
    }

    public function test_ordenacao_e_filtro_por_qualquer_etiqueta_respeitam_grupos_e_usuario(): void
    {
        $usuario = User::factory()->create();
        $outro = User::factory()->create();
        $semTitulo = $this->nota($usuario, ['titulo' => null, 'descricao' => 'Sem titulo', 'fixada' => true]);
        $zeta = $this->nota($usuario, ['titulo' => 'Zeta', 'fixada' => true]);
        $alfa = $this->nota($usuario, ['titulo' => 'Alfa']);
        $oculta = $this->nota($outro, ['titulo' => 'Segredo']);
        $a = $usuario->etiquetas()->create(['nome' => 'Trabalho']);
        $b = $usuario->etiquetas()->create(['nome' => 'Casa']);
        $this->associar($usuario, $zeta, $a);
        $this->associar($usuario, $alfa, $b);
        $this->associar($outro, $oculta, $outro->etiquetas()->create(['nome' => 'Trabalho']));

        $html = $this->actingAs($usuario)->get(route('notas.inicio', [
            'ordem' => 'titulo', 'etiquetas' => [$a->id, $b->id],
        ]))->assertOk()->getContent();

        $this->assertStringContainsString('Zeta', $html);
        $this->assertStringContainsString('Alfa', $html);
        $this->assertStringNotContainsString('Segredo', $html);
        $this->assertLessThan(strpos($html, 'Alfa'), strpos($html, 'Zeta'));
        $this->assertStringNotContainsString('Sem titulo', $html);

        $semFiltro = $this->get(route('notas.inicio', ['ordem' => 'titulo']))->getContent();
        $this->assertLessThan(strpos($semFiltro, 'Sem titulo'), strpos($semFiltro, 'Zeta'));
        $this->assertLessThan(strpos($semFiltro, 'Alfa'), strpos($semFiltro, 'Sem titulo'));
    }

    public function test_etiquetas_sao_normalizadas_unicas_pessoais_e_permanecem_na_lixeira(): void
    {
        $usuario = User::factory()->create();
        $outro = User::factory()->create();
        $nota = $this->nota($usuario);

        $this->actingAs($usuario)->post(route('etiquetas.store'), ['nome' => '  Projeto   Web '])->assertSessionHasNoErrors();
        $etiqueta = Etiqueta::first();
        $this->assertSame('Projeto Web', $etiqueta->nome);
        $this->assertSame('projeto web', $etiqueta->nome_normalizado);
        $this->post(route('etiquetas.store'), ['nome' => 'PROJETO WEB'])->assertSessionHasErrors('nome');

        $this->put(route('notas.etiquetas', $nota), ['etiquetas' => [$etiqueta->id]])->assertSessionHasNoErrors();
        $this->actingAs($outro)->put(route('notas.etiquetas', $nota), ['etiquetas' => [$etiqueta->id]])->assertForbidden();

        $this->actingAs($usuario)->delete(route('notas.mover-lixeira', $nota));
        $this->get(route('lixeira.index', ['etiquetas' => [$etiqueta->id]]))
            ->assertOk()->assertSee('Nota base');
        $this->delete(route('etiquetas.destroy', $etiqueta))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('notas', ['id' => $nota->id]);
        $this->assertDatabaseMissing('etiqueta_nota', ['etiqueta_id' => $etiqueta->id]);
    }

    public function test_lista_valida_ordem_conclusao_busca_exportacao_e_preservacao(): void
    {
        $usuario = User::factory()->create();
        $this->actingAs($usuario)->post(route('notas.store'), [
            'titulo' => 'Compras',
            'descricao' => 'Mercado',
            'tipo_conteudo' => 'lista',
            'tipo_aparencia' => 'cor',
            'cor' => 'padrao',
            'itens' => [
                ['texto' => 'Cafe acentuado: café', 'concluido' => 1],
                ['texto' => 'Pao', 'concluido' => 0],
            ],
        ])->assertSessionHasNoErrors();
        $nota = Nota::first();

        $this->assertSame(['Cafe acentuado: café', 'Pao'], $nota->itens()->pluck('texto')->all());
        $this->get(route('notas.inicio', ['q' => 'café']))->assertSee('Compras');

        $download = $this->get(route('notas.exportar', $nota));
        $download->assertOk();
        $this->assertStringContainsString('[x] Cafe acentuado: café', $download->getContent());
        $this->assertStringContainsString('[ ] Pao', $download->getContent());
        $this->assertStringNotContainsString('&eacute;', $download->getContent());

        $this->delete(route('notas.mover-lixeira', $nota));
        $this->get(route('notas.exportar', $nota->id))->assertOk()->assertSee('[x] Cafe acentuado: café', false);
        $this->patch(route('lixeira.restaurar', $nota->id));
        $this->assertSame([0, 1], $nota->itens()->orderBy('posicao')->pluck('posicao')->all());

        $this->post(route('notas.store'), [
            'tipo_conteudo' => 'lista', 'tipo_aparencia' => 'cor', 'cor' => 'padrao', 'itens' => [],
        ])->assertSessionHasErrors('itens', null, 'criacaoNota');
    }

    public function test_lote_e_atomico_autorizado_e_desfaz_integralmente(): void
    {
        $usuario = User::factory()->create();
        $outro = User::factory()->create();
        $uma = $this->nota($usuario, ['titulo' => 'Uma']);
        $duas = $this->nota($usuario, ['titulo' => 'Duas']);
        $alheia = $this->nota($outro, ['titulo' => 'Alheia']);

        $this->actingAs($usuario)->post(route('notas.lote'), [
            'acao' => 'arquivar', 'secao' => 'ativas', 'notas' => [$uma->id, $alheia->id],
        ])->assertSessionHasErrors('notas');
        $this->assertFalse($uma->fresh()->arquivada);

        $resposta = $this->post(route('notas.lote'), [
            'acao' => 'arquivar', 'secao' => 'ativas', 'notas' => [$uma->id, $duas->id],
        ])->assertSessionHasNoErrors();
        $token = $resposta->getSession()->get('desfazer');
        $this->assertTrue($uma->fresh()->arquivada);
        $this->assertTrue($duas->fresh()->arquivada);

        $this->post(route('desfazer', $token))->assertSessionHasNoErrors();
        $this->assertFalse($uma->fresh()->arquivada);
        $this->assertFalse($duas->fresh()->arquivada);

        $etiqueta = $usuario->etiquetas()->create(['nome' => 'Lote']);
        $this->post(route('notas.lote'), [
            'acao' => 'aplicar_etiqueta', 'secao' => 'ativas', 'notas' => [$uma->id, $duas->id], 'etiqueta_id' => $etiqueta->id,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('etiqueta_nota', 2);

        $resposta = $this->post(route('notas.lote'), [
            'acao' => 'lixeira', 'secao' => 'ativas', 'notas' => [$uma->id, $duas->id],
        ])->assertSessionHasNoErrors();
        $this->assertTrue($uma->fresh()->trashed());
        $this->post(route('desfazer', $resposta->getSession()->get('desfazer')))->assertSessionHasNoErrors();
        $this->assertFalse($uma->fresh()->trashed());

        $this->post(route('notas.lote'), [
            'acao' => 'lixeira', 'secao' => 'ativas', 'notas' => [$uma->id, $duas->id],
        ])->assertSessionHasNoErrors();
        $this->post(route('notas.lote'), [
            'acao' => 'restaurar', 'secao' => 'lixeira', 'notas' => [$uma->id, $duas->id],
        ])->assertSessionHasNoErrors();
        $this->assertFalse($uma->fresh()->trashed());
        $this->assertFalse($duas->fresh()->trashed());
    }

    public function test_fixacao_avanca_revisao_uma_vez_para_estado_idempotente(): void
    {
        $usuario = User::factory()->create();
        $nota = $this->nota($usuario);
        $revisao = $nota->revisao;

        $this->actingAs($usuario)->patch(route('notas.fixacao', $nota), ['fixada' => 1])->assertSessionHasNoErrors();
        $this->assertSame($revisao + 1, $nota->fresh()->revisao);

        $this->patch(route('notas.fixacao', $nota), ['fixada' => 1])->assertSessionHasNoErrors();
        $this->assertSame($revisao + 1, $nota->fresh()->revisao);
    }

    public function test_lembrete_processa_uma_vez_e_lixeira_suspende_sem_disparo_retroativo(): void
    {
        Queue::fake();
        Carbon::setTestNow('2026-09-22 12:00:00');
        CarbonImmutable::setTestNow('2026-09-22 12:00:00');
        $usuario = User::factory()->create(['fuso_horario' => 'America/Sao_Paulo']);
        $nota = $this->nota($usuario);

        $this->actingAs($usuario)->post(route('notas.lembrete', $nota), [
            'agendado_local' => '2026-09-22T12:01',
            'fuso_horario' => 'America/Sao_Paulo',
            'enviar_email' => 1,
        ])->assertSessionHasNoErrors();

        Carbon::setTestNow('2026-09-22 15:02:00');
        CarbonImmutable::setTestNow('2026-09-22 15:02:00');
        $this->assertTrue(Lembrete::first()->agendado_em->lte(now()), Lembrete::first()->agendado_em->toISOString().' > '.now()->toISOString());
        $this->artisan('lembretes:processar')->expectsOutput('1 lembrete(s) verificado(s).')->assertSuccessful();
        $this->artisan('lembretes:processar')->assertSuccessful();
        $this->assertDatabaseCount('notificacoes_internas', 1);
        Queue::assertPushed(EnviarEmailLembrete::class, 1);

        $futura = $this->nota($usuario, ['titulo' => 'Futura']);
        $this->post(route('notas.lembrete', $futura), [
            'agendado_local' => '2026-09-22T13:00', 'fuso_horario' => 'America/Sao_Paulo',
        ]);
        $this->delete(route('notas.mover-lixeira', $futura));
        $this->assertTrue($futura->lembretes()->first()->suspenso_lixeira);
        Carbon::setTestNow('2026-09-22 17:00:00');
        CarbonImmutable::setTestNow('2026-09-22 17:00:00');
        $this->patch(route('lixeira.restaurar', $futura->id));
        $lembrete = $futura->lembretes()->first();
        $this->assertTrue($lembrete->suspenso_lixeira);
        $this->assertFalse($lembrete->ativo);

        Carbon::setTestNow('2026-09-22 15:10:00');
        CarbonImmutable::setTestNow('2026-09-22 15:10:00');
        $reativada = $this->nota($usuario, ['titulo' => 'Reativada']);
        $this->post(route('notas.lembrete', $reativada), [
            'agendado_local' => '2026-09-22T14:00', 'fuso_horario' => 'America/Sao_Paulo',
        ])->assertSessionHasNoErrors();
        $this->delete(route('notas.mover-lixeira', $reativada));
        $this->patch(route('lixeira.restaurar', $reativada->id));
        $lembreteFuturo = $reativada->lembretes()->first();
        $this->assertTrue($lembreteFuturo->ativo);
        $this->assertFalse($lembreteFuturo->suspenso_lixeira);

        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
    }

    public function test_convite_papeis_revogacao_e_conflito_de_revisao(): void
    {
        $dono = User::factory()->create();
        $editor = User::factory()->create();
        $leitor = User::factory()->create();
        $terceiro = User::factory()->create();
        $nota = $this->nota($dono, ['titulo' => 'Compartilhada']);

        $this->actingAs($dono)->post(route('notas.convidar', $nota), ['email' => $editor->email, 'papel' => 'editor']);
        $convite = $nota->convites()->where('convidado_id', $editor->id)->first();
        $this->actingAs($editor)->get(route('notas.show', $nota))->assertForbidden();
        $this->actingAs($editor)->patch(route('convites.responder', $convite->token), ['resposta' => 'aceitar']);
        $this->actingAs($dono)->post(route('notas.convidar', $nota), ['email' => $leitor->email, 'papel' => 'leitor']);
        $conviteLeitor = $nota->convites()->where('convidado_id', $leitor->id)->latest('id')->first();
        $this->actingAs($leitor)->patch(route('convites.responder', $conviteLeitor->token), ['resposta' => 'aceitar']);

        $this->actingAs($editor)->get(route('notas.show', $nota))->assertOk();
        $this->actingAs($leitor)->get(route('notas.show', $nota))->assertOk();
        $this->actingAs($leitor)->get(route('notas.exportar', $nota))->assertOk()->assertSee('Compartilhada', false);
        $this->actingAs($terceiro)->get(route('notas.show', $nota))->assertForbidden();
        $this->actingAs($leitor)->patchJson(route('notas.update', $nota), [])->assertForbidden();
        $this->actingAs($editor)->patch(route('notas.fixacao', $nota), ['fixada' => 1])->assertForbidden();

        $revisao = $nota->fresh()->revisao;
        $payload = [
            'titulo' => 'Versao editor', 'descricao' => 'Texto', 'tipo_conteudo' => 'texto',
            'tipo_aparencia' => 'cor', 'cor' => 'padrao', 'revisao' => $revisao,
        ];
        $this->actingAs($editor)->patchJson(route('notas.update', $nota), $payload)->assertOk();
        $this->actingAs($dono)->patchJson(route('notas.update', $nota), array_merge($payload, ['titulo' => 'Versao antiga']))
            ->assertStatus(409)->assertJsonPath('conflito.atual.titulo', 'Versao editor');
        $this->assertSame('Versao editor', $nota->fresh()->titulo);

        Carbon::setTestNow('2026-09-22 12:00:00');
        CarbonImmutable::setTestNow('2026-09-22 12:00:00');
        $this->actingAs($editor)->post(route('notas.lembrete', $nota), [
            'agendado_local' => '2026-09-22T13:00', 'fuso_horario' => 'America/Sao_Paulo',
        ])->assertSessionHasNoErrors();
        $this->actingAs($dono)->delete(route('notas.participantes.revogar', [$nota, $editor]));
        $this->assertDatabaseMissing('lembretes', ['nota_id' => $nota->id, 'usuario_id' => $editor->id]);
        $this->actingAs($editor)->get(route('notas.show', $nota))->assertForbidden();

        $this->actingAs($dono)->delete(route('notas.mover-lixeira', $nota));
        $this->actingAs($leitor)->get(route('notas.show', $nota))->assertNotFound();
        $this->actingAs($dono)->patch(route('lixeira.restaurar', $nota->id));
        $this->actingAs($leitor)->get(route('notas.show', $nota))->assertOk();
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
    }

    public function test_convite_nao_pode_ser_aceito_enquanto_nota_esta_na_lixeira(): void
    {
        $dono = User::factory()->create();
        $convidado = User::factory()->create();
        $nota = $this->nota($dono, ['titulo' => 'Convite suspenso']);

        $this->actingAs($dono)->post(route('notas.convidar', $nota), [
            'email' => $convidado->email,
            'papel' => 'editor',
        ])->assertSessionHasNoErrors();
        $convite = $nota->convites()->firstOrFail();
        $this->delete(route('notas.mover-lixeira', $nota));

        $this->actingAs($convidado)->patch(route('convites.responder', $convite->token), [
            'resposta' => 'aceitar',
        ])->assertConflict();
        $this->assertDatabaseMissing('nota_participantes', [
            'nota_id' => $nota->id,
            'usuario_id' => $convidado->id,
        ]);
    }

    public function test_sincronizacao_e_idempotente_isolada_e_nao_recria_acesso_revogado(): void
    {
        $dono = User::factory()->create();
        $editor = User::factory()->create();
        $uuidOperacao = (string) Str::uuid();
        $uuidNota = (string) Str::uuid();
        $payload = [
            'titulo' => 'Offline', 'descricao' => null, 'tipo_conteudo' => 'texto', 'itens' => [],
            'tipo_aparencia' => 'cor', 'cor' => 'padrao', 'fundo' => null,
        ];

        $corpo = ['conta_id' => $dono->id, 'operacoes' => [[
            'uuid' => $uuidOperacao, 'acao' => 'criar', 'id_local' => $uuidNota, 'payload' => $payload,
        ]]];
        $this->actingAs($dono)->postJson(route('sincronizacao.executar'), $corpo)
            ->assertOk()->assertJsonPath('resultados.0.status', 'sincronizado');
        $this->postJson(route('sincronizacao.executar'), $corpo)
            ->assertOk()->assertJsonPath('resultados.0.status', 'sincronizado');
        $repetidaComOutroUuid = $corpo;
        $repetidaComOutroUuid['operacoes'][0]['uuid'] = (string) Str::uuid();
        $this->postJson(route('sincronizacao.executar'), $repetidaComOutroUuid)
            ->assertOk()->assertJsonPath('resultados.0.status', 'sincronizado');
        $this->assertSame(1, Nota::where('uuid_sincronizacao', $uuidNota)->count());

        $this->actingAs($editor)->postJson(route('sincronizacao.executar'), $corpo)->assertForbidden();

        $nota = Nota::where('uuid_sincronizacao', $uuidNota)->first();
        $nota->participantes()->attach($editor->id, ['papel' => 'editor']);
        $revisao = $nota->revisao;
        $nota->participantes()->detach($editor->id);
        $this->actingAs($editor)->postJson(route('sincronizacao.executar'), [
            'conta_id' => $editor->id,
            'operacoes' => [[
                'uuid' => (string) Str::uuid(), 'acao' => 'atualizar', 'nota_uuid' => $uuidNota,
                'revisao_base' => $revisao, 'payload' => array_merge($payload, ['titulo' => 'Nao pode']),
            ]],
        ])->assertOk()->assertJsonPath('resultados.0.status', 'revogado_ou_excluido');
        $this->assertSame('Offline', $nota->fresh()->titulo);
    }

    private function nota(User $usuario, array $atributos = []): Nota
    {
        $nota = new Nota;
        $nota->forceFill(array_merge([
            'titulo' => 'Nota base',
            'descricao' => 'Descricao',
            'tipo_conteudo' => TipoNota::Texto,
            'fixada' => false,
            'arquivada' => false,
            'tipo_aparencia' => 'cor',
            'cor' => null,
            'caminho_imagem' => null,
        ], $atributos));
        $usuario->notas()->save($nota);

        return $nota;
    }

    private function associar(User $usuario, Nota $nota, Etiqueta $etiqueta): void
    {
        $nota->etiquetas()->attach($etiqueta->id, ['usuario_id' => $usuario->id]);
    }
}
