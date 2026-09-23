<?php

namespace App\Http\Controllers;

use App\Enums\PapelNota;
use App\Enums\StatusConvite;
use App\Models\ConviteNota;
use App\Models\Nota;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;

class CompartilhamentoController extends Controller
{
    public function index(Request $request): View
    {
        $convites = ConviteNota::query()
            ->where('convidado_id', $request->user()->id)
            ->where('status', StatusConvite::Pendente)
            ->whereHas('nota')
            ->with(['nota.usuario', 'convidado'])
            ->latest()
            ->get();

        $proprias = $request->user()->notas()->with(['participantes', 'usuario'])->latest()->get();
        $compartilhadas = $request->user()->notasCompartilhadas()->with('usuario')->latest('notas.updated_at')->get();

        return view('compartilhamentos.index', compact('convites', 'proprias', 'compartilhadas'));
    }

    public function convidar(Request $request, Nota $nota): RedirectResponse
    {
        Gate::authorize('share', $nota);
        $dados = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'papel' => ['required', new Enum(PapelNota::class)],
        ]);
        $convidado = User::whereRaw('LOWER(email) = ?', [mb_strtolower(trim($dados['email']))])->first();

        if (! $convidado) {
            return back()->withErrors(['email' => 'Não existe uma conta cadastrada com esse e-mail.']);
        }
        if ($convidado->id === $request->user()->id) {
            return back()->withErrors(['email' => 'Você já é o proprietário desta nota.']);
        }
        if ($nota->participantes()->whereKey($convidado->id)->exists()) {
            return back()->withErrors(['email' => 'Essa pessoa já participa da nota.']);
        }

        ConviteNota::where('nota_id', $nota->id)
            ->where('convidado_id', $convidado->id)
            ->delete();

        $convite = new ConviteNota([
            'token' => (string) Str::uuid(),
            'convidado_id' => $convidado->id,
            'convidado_por' => $request->user()->id,
            'email_destino' => $convidado->email,
            'papel' => $dados['papel'],
            'status' => StatusConvite::Pendente,
        ]);
        $nota->convites()->save($convite);

        return back()->with('sucesso', 'Convite criado dentro do aplicativo.');
    }

    public function responder(Request $request, string $token): RedirectResponse
    {
        $dados = $request->validate(['resposta' => ['required', 'in:aceitar,recusar']]);

        DB::transaction(function () use ($request, $token, $dados): void {
            $convite = ConviteNota::where('token', $token)->lockForUpdate()->firstOrFail();
            abort_unless($convite->convidado_id === $request->user()->id, 403);
            abort_unless($convite->status === StatusConvite::Pendente, 409);
            abort_unless($convite->nota !== null && ! $convite->nota->trashed(), 409);

            if ($dados['resposta'] === 'aceitar') {
                $convite->nota->participantes()->syncWithoutDetaching([
                    $request->user()->id => ['papel' => $convite->papel->value],
                ]);
                $convite->status = StatusConvite::Aceito;
            } else {
                $convite->status = StatusConvite::Recusado;
            }
            $convite->respondido_em = now();
            $convite->save();
        });

        return back()->with('sucesso', $dados['resposta'] === 'aceitar' ? 'Convite aceito.' : 'Convite recusado.');
    }

    public function alterarPapel(Request $request, Nota $nota, User $participante): RedirectResponse
    {
        Gate::authorize('share', $nota);
        $dados = $request->validate(['papel' => ['required', new Enum(PapelNota::class)]]);
        abort_unless($nota->participantes()->whereKey($participante->id)->exists(), 404);
        $nota->participantes()->updateExistingPivot($participante->id, ['papel' => $dados['papel']]);

        return back()->with('sucesso', 'Papel atualizado.');
    }

    public function revogar(Request $request, Nota $nota, User $participante): RedirectResponse
    {
        Gate::authorize('share', $nota);
        $this->removerAcesso($nota, $participante);

        return back()->with('sucesso', 'Acesso revogado.');
    }

    public function sair(Request $request, Nota $nota): RedirectResponse
    {
        abort_unless($nota->participantes()->whereKey($request->user()->id)->exists(), 404);
        $this->removerAcesso($nota, $request->user());

        return back()->with('sucesso', 'Você saiu da nota compartilhada.');
    }

    private function removerAcesso(Nota $nota, User $usuario): void
    {
        DB::transaction(function () use ($nota, $usuario): void {
            $nota->participantes()->detach($usuario->id);
            $nota->lembretes()->where('usuario_id', $usuario->id)->delete();
            DB::table('etiqueta_nota')->where('nota_id', $nota->id)->where('usuario_id', $usuario->id)->delete();
        });
    }
}
