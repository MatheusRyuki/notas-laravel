<?php

namespace App\Http\Controllers;

use App\Services\DesfazerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DesfazerController extends Controller
{
    public function __invoke(Request $request, string $token, DesfazerService $service): RedirectResponse
    {
        try {
            $retorno = $service->executar($request->user(), $token);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors(), 'desfazer');
        }

        $rota = in_array($retorno['rota'] ?? '', ['notas.inicio', 'notas.arquivadas', 'lixeira.index'], true)
            ? $retorno['rota']
            : 'notas.inicio';

        return redirect()->route($rota, $retorno['consulta'] ?? [])
            ->with('sucesso', 'Ação desfeita com sucesso.');
    }
}
