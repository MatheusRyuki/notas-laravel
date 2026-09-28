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
        $this->actingAs($leitor);
        $medicoes = [];

        foreach ([1, 20] as $total) {
            while ($dono->notas()->count() < $total) {
                $nota = $dono->notas()->create(['titulo' => 'Compartilhada']);
                $nota->participantes()->attach($leitor->id, ['papel' => 'leitor']);
                $nota->participantes()->attach($outro->id, ['papel' => 'editor']);
            }

            foreach (['notas.inicio', 'sincronizacao.bootstrap'] as $rota) {
                DB::enableQueryLog();
                DB::flushQueryLog();
                try {
                    $resposta = $this->getJson(route($rota))->assertOk();
                    $medicoes[$rota][$total] = count(DB::getQueryLog());
                } finally {
                    DB::disableQueryLog();
                }
                if ($rota === 'notas.inicio') {
                    $resposta->assertJsonPath('total', $total);
                } else {
                    $resposta->assertJsonCount($total, 'notas')
                        ->assertJsonPath('notas.0.papel', 'leitor')
                        ->assertJsonPath('notas.0.pode_editar', false);
                }
            }
        }

        foreach ($medicoes as $rota => $quantidades) {
            $this->assertEquals($quantidades[1], $quantidades[20], $rota);
        }
    }
}
