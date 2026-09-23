<?php

namespace App\Http\Controllers;

use App\Models\Lembrete;
use App\Models\Nota;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LembreteController extends Controller
{
    public function index(Request $request): View
    {
        $lembretes = $request->user()->lembretes()
            ->with('nota')
            ->orderByDesc('ativo')
            ->orderBy('agendado_em')
            ->get();
        $notificacoes = $request->user()->notificacoesInternas()->latest()->get();

        return view('lembretes.index', compact('lembretes', 'notificacoes'));
    }

    public function store(Request $request, Nota $nota): RedirectResponse
    {
        Gate::authorize('view', $nota);
        abort_if($nota->trashed(), 409);
        $dados = $request->validate([
            'agendado_local' => ['required', 'date_format:Y-m-d\TH:i'],
            'fuso_horario' => ['required', 'string', Rule::in(timezone_identifiers_list(DateTimeZone::ALL))],
            'enviar_email' => ['nullable', 'boolean'],
        ]);

        $instante = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $dados['agendado_local'], $dados['fuso_horario']);
        if (! $instante || $instante->isPast()) {
            throw ValidationException::withMessages(['agendado_local' => 'Escolha um horário futuro.']);
        }

        $nota->lembretes()->updateOrCreate(
            ['usuario_id' => $request->user()->id],
            [
                'agendado_em' => $instante->utc(),
                'fuso_horario' => $dados['fuso_horario'],
                'enviar_email' => $request->boolean('enviar_email'),
                'ativo' => true,
                'suspenso_lixeira' => false,
                'processado_em' => null,
            ],
        );

        $request->user()->forceFill(['fuso_horario' => $dados['fuso_horario']])->save();

        return back()->with('sucesso', 'Lembrete agendado.');
    }

    public function destroy(Request $request, Lembrete $lembrete): RedirectResponse
    {
        abort_unless($lembrete->usuario_id === $request->user()->id, 403);
        $lembrete->delete();

        return back()->with('sucesso', 'Lembrete cancelado.');
    }
}
