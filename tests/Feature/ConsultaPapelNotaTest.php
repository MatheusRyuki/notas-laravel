<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConsultaPapelNotaTest extends TestCase
{
    use RefreshDatabase;

    public function test_listagem_e_bootstrap_carregam_papeis_sem_consultas_por_nota(): void
    {
        $dono = User::factory()->create();
        $leitor = User::factory()->create();
        $outro = User::factory()->create();
        for ($indice = 0; $indice < 10; $indice++) {
            $nota = $dono->notas()->create(['titulo' => 'Compartilhada '.$indice]);
            $nota->participantes()->attach($leitor->id, ['papel' => 'leitor']);
            $nota->participantes()->attach($outro->id, ['papel' => 'editor']);
        }
        $this->actingAs($leitor);

        foreach (['notas.inicio' => 6, 'sincronizacao.bootstrap' => 4] as $rota => $limite) {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $resposta = $this->getJson(route($rota))->assertOk();
            $quantidade = count(DB::getQueryLog());
            DB::disableQueryLog();
            $this->assertLessThanOrEqual($limite, $quantidade);
            if ($rota === 'notas.inicio') {
                $resposta->assertJsonPath('total', 10);
            } else {
                $resposta->assertJsonCount(10, 'notas')
                    ->assertJsonPath('notas.0.papel', 'leitor')
                    ->assertJsonPath('notas.0.pode_editar', false);
            }
        }
    }
}
