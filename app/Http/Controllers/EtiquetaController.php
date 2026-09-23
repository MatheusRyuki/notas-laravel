<?php

namespace App\Http\Controllers;

use App\Models\Etiqueta;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EtiquetaController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $nome = $this->validarNome($request);
        try {
            $request->user()->etiquetas()->create(['nome' => $nome]);
        } catch (QueryException) {
            throw ValidationException::withMessages(['nome' => 'Você já possui uma etiqueta com esse nome.']);
        }

        return back()->with('sucesso', 'Etiqueta criada com sucesso.');
    }

    public function update(Request $request, Etiqueta $etiqueta): RedirectResponse
    {
        abort_unless($etiqueta->usuario_id === $request->user()->id, 403);
        $etiqueta->nome = $this->validarNome($request);
        try {
            $etiqueta->save();
        } catch (QueryException) {
            throw ValidationException::withMessages(['nome' => 'Você já possui uma etiqueta com esse nome.']);
        }

        return back()->with('sucesso', 'Etiqueta renomeada com sucesso.');
    }

    public function destroy(Request $request, Etiqueta $etiqueta): RedirectResponse
    {
        abort_unless($etiqueta->usuario_id === $request->user()->id, 403);
        $etiqueta->delete();

        return back()->with('sucesso', 'Etiqueta excluída. As notas foram preservadas.');
    }

    private function validarNome(Request $request): string
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:60'],
        ], [
            'nome.required' => 'Informe o nome da etiqueta.',
            'nome.max' => 'O nome da etiqueta pode ter no máximo 60 caracteres.',
        ]);
        $nome = trim(preg_replace('/\s+/u', ' ', $dados['nome']) ?? '');

        if ($nome === '') {
            throw ValidationException::withMessages(['nome' => 'Informe o nome da etiqueta.']);
        }

        return $nome;
    }
}
