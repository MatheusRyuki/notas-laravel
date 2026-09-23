<?php

namespace App\Http\Controllers;

use App\Models\Nota;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NotaEtiquetaController extends Controller
{
    public function update(Request $request, Nota $nota): RedirectResponse
    {
        abort_unless($request->user()->can('view', $nota), 403);
        $dados = $request->validate([
            'etiquetas' => ['nullable', 'array'],
            'etiquetas.*' => ['integer', 'distinct'],
        ]);
        $ids = array_map('intval', $dados['etiquetas'] ?? []);
        $validas = $request->user()->etiquetas()->whereKey($ids)->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (count($ids) !== count($validas)) {
            throw ValidationException::withMessages(['etiquetas' => 'Uma etiqueta selecionada não pertence à sua conta.']);
        }

        DB::transaction(function () use ($request, $nota, $validas): void {
            DB::table('etiqueta_nota')->where('usuario_id', $request->user()->id)->where('nota_id', $nota->id)->delete();
            $agora = now();
            foreach ($validas as $id) {
                DB::table('etiqueta_nota')->insert([
                    'usuario_id' => $request->user()->id,
                    'nota_id' => $nota->id,
                    'etiqueta_id' => $id,
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ]);
            }
        });

        return back()->with('sucesso', 'Etiquetas da nota atualizadas.');
    }
}
