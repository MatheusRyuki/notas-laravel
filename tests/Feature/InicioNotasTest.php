<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InicioNotasTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitante_precisa_entrar_para_acessar_o_inicio_e_o_perfil(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
    }

    public function test_inicio_mostra_usuario_real_e_estado_vazio_sem_notas(): void
    {
        $usuario = User::factory()->create(['name' => 'Ana de Souza']);

        $this->actingAs($usuario)->get('/')
            ->assertOk()
            ->assertSee('Ana de Souza')
            ->assertSee('Crie sua primeira nota.')
            ->assertSee('Criar nota')
            ->assertSee('Buscar por título ou descrição')
            ->assertSee(route('profile.edit'))
            ->assertSee(route('logout'));

        $this->assertTrue(Schema::hasTable('notas'));
    }

    public function test_nome_do_usuario_e_escapado_no_html(): void
    {
        $usuario = User::factory()->create(['name' => '<script>alert(1)</script>']);

        $this->actingAs($usuario)->get('/')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_nome_longo_nao_impede_acesso(): void
    {
        $usuario = User::factory()->create(['name' => str_repeat('Maria ', 40)]);
        $this->actingAs($usuario)->get('/')->assertOk();
    }

    public function test_perfil_altera_apenas_a_conta_autenticada_mesmo_com_id_injetado(): void
    {
        $usuario = User::factory()->create();
        $outroUsuario = User::factory()->create(['name' => 'Outra pessoa']);

        $this->actingAs($usuario)->patch(route('profile.update'), [
            'id' => $outroUsuario->id,
            'name' => 'Meu nome atualizado',
            'email' => $usuario->email,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Meu nome atualizado', $usuario->fresh()->name);
        $this->assertSame('Outra pessoa', $outroUsuario->fresh()->name);
    }

    public function test_nao_permite_usar_email_de_outra_conta(): void
    {
        $usuario = User::factory()->create();
        $outroUsuario = User::factory()->create();

        $this->actingAs($usuario)->patch(route('profile.update'), [
            'name' => $usuario->name,
            'email' => $outroUsuario->email,
        ])->assertSessionHasErrors(['email' => 'Este e-mail já está em uso.']);

        $this->assertSame($usuario->email, $usuario->fresh()->email);
    }

    public function test_validacoes_de_cadastro_estao_em_portugues(): void
    {
        $this->post(route('register'), [
            'name' => '',
            'email' => 'email-invalido',
            'password' => 'abc',
            'password_confirmation' => 'xyz',
        ])->assertSessionHasErrors([
            'name' => 'O campo nome é obrigatório.',
            'email' => 'Informe um e-mail válido.',
            'password' => 'A confirmação de senha não confere.',
        ]);
    }

    public function test_senha_incorreta_mostra_mensagem_em_portugues(): void
    {
        $usuario = User::factory()->create();

        $this->post(route('login'), [
            'email' => $usuario->email,
            'password' => 'incorreta',
        ])->assertSessionHasErrors(['email' => 'O e-mail ou a senha estão incorretos.']);

        $this->assertGuest();
    }

    public function test_login_limita_tentativas_repetidas(): void
    {
        $usuario = User::factory()->create();

        for ($tentativa = 0; $tentativa < 5; $tentativa++) {
            $this->post(route('login'), ['email' => $usuario->email, 'password' => 'incorreta']);
        }

        $this->post(route('login'), ['email' => $usuario->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertStringContainsString('Muitas tentativas', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_token_invalido_nao_altera_senha(): void
    {
        $usuario = User::factory()->create();
        $senhaAnterior = $usuario->password;

        $this->post(route('password.store'), [
            'email' => $usuario->email,
            'token' => 'token-invalido',
            'password' => 'nova-senha-segura',
            'password_confirmation' => 'nova-senha-segura',
        ])->assertSessionHasErrors(['email' => 'Este link de redefinição de senha é inválido ou expirou.']);

        $this->assertSame($senhaAnterior, $usuario->fresh()->password);
    }

    public function test_recuperacao_envia_email_traduzido(): void
    {
        $usuario = User::factory()->create();
        $mensagem = (new ResetPassword('token-teste'))->toMail($usuario);
        $html = $mensagem->render()->toHtml();

        $this->assertSame('Redefinição de senha', $mensagem->subject);
        $this->assertStringContainsString('Olá!', $html);
        $this->assertStringContainsString('Redefinir senha', $html);
        $this->assertStringContainsString('expira em 60 minutos', $html);
        $this->assertStringNotContainsString('All rights reserved', $html);
    }

    public function test_sessao_usa_cookie_proprio(): void
    {
        $this->assertSame('notas_session', config('session.cookie'));
    }
}
