<?php

namespace App\Http\Controllers;

use App\Enums\TipoNota;
use App\Models\Nota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ExportarNotaController extends Controller
{
    public function __invoke(Request $request, int $nota): Response|JsonResponse
    {
        $registro = Nota::withTrashed()->with('itens')->findOrFail($nota);
        abort_unless($request->user()->can('view', $registro), 403);

        $texto = $this->texto($registro);
        if ($request->expectsJson() || $request->boolean('texto')) {
            return response()->json(['texto' => $texto]);
        }

        $base = Str::slug($registro->titulo ?: 'nota-'.$registro->id) ?: 'nota';
        $nome = mb_substr($base, 0, 80).'.txt';

        return response($texto, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$nome.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function texto(Nota $nota): string
    {
        $partes = [];
        if ($nota->titulo !== null) {
            $partes[] = $nota->titulo;
        }
        if ($nota->descricao !== null) {
            $partes[] = $nota->descricao;
        }
        if ($nota->tipo_conteudo === TipoNota::Lista) {
            $partes[] = $nota->itens
                ->map(fn ($item) => sprintf('[%s] %s', $item->concluido ? 'x' : ' ', $item->texto))
                ->implode("\n");
        }

        return implode("\n\n", array_filter($partes, fn ($parte) => $parte !== ''))."\n";
    }
}
