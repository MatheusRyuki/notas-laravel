<?php

namespace App\Http\Controllers;

use App\Models\NotificacaoInterna;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificacaoController extends Controller
{
    public function ler(Request $request, NotificacaoInterna $notificacao): RedirectResponse
    {
        abort_unless($notificacao->usuario_id === $request->user()->id, 403);
        $notificacao->forceFill(['lida_em' => now()])->save();

        return back()->with('sucesso', 'Notificação marcada como lida.');
    }
}
