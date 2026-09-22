<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AprimoramentosInterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_seletores_usam_radios_nativos_sem_tabulação_ou_semantica_redundante(): void
    {
        $usuario = User::factory()->create();

        $html = $this->actingAs($usuario)->get(route('notas.inicio'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('class="radio-aparencia"', $html);
        $this->assertStringContainsString('type="radio"', $html);
        $this->assertStringNotContainsString('role="radio"', $html);
        $this->assertStringNotContainsString('tabindex="-1"', $html);
        $this->assertStringContainsString("el.matches('input[type=\\'radio\\']') && ! el.checked", $html);
        $this->assertSame(2, substr_count($html, 'firstFocusable()?.focus()'));
    }

    public function test_interface_remove_marcadores_internos_e_mantem_anuncios_sem_regiao_viva_aninhada(): void
    {
        $usuario = User::factory()->create();

        $resposta = $this->actingAs($usuario)->get(route('notas.inicio'))
            ->assertOk()
            ->assertDontSee('Etapa 07')
            ->assertDontSee('EM CONSTRUÇÃO')
            ->assertDontSee('Um começo simples')
            ->assertSee('role="status" aria-live="polite"', false)
            ->assertSee('role="alert" aria-atomic="true"', false)
            ->assertDontSee('class="estado-consulta" aria-live=', false)
            ->assertSee('<svg', false);

        $this->assertStringNotContainsString('♲', $resposta->getContent());
        $this->assertStringNotContainsString('◇', $resposta->getContent());
    }

    public function test_perfil_tem_h1_e_confirmacao_definitiva_e_uma_pagina_comum(): void
    {
        $usuario = User::factory()->create();
        $nota = $usuario->notas()->create(['titulo' => 'Nota removida']);
        $nota->delete();

        $this->actingAs($usuario)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('<h1', false)
            ->assertSee('Meu perfil');

        $this->actingAs($usuario)->get(route('lixeira.confirmar-exclusao', $nota->id))
            ->assertOk()
            ->assertSee('<h1 id="titulo-confirmacao">', false)
            ->assertDontSee('role="alertdialog"', false)
            ->assertDontSee('aria-modal="true"', false)
            ->assertSee('Cancelar')
            ->assertSee('Excluir definitivamente');
    }

    public function test_falhas_de_rede_dos_modais_exibem_mensagens_em_portugues(): void
    {
        $script = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString(
            "this.erroLeitura = 'Não foi possível consultar esta nota. Tente novamente.';",
            $script,
        );
        $this->assertStringContainsString(
            "this.erroCarregamento = 'Não foi possível abrir esta nota. Tente novamente.';",
            $script,
        );
        $this->assertStringContainsString(
            "this.erroCarregamento = 'Não foi possível salvar. Tente novamente.';",
            $script,
        );
        $this->assertStringNotContainsString('this.erroCarregamento = erro.message;', $script);
        $this->assertStringNotContainsString('this.erroLeitura = erro.message;', $script);
    }
}
