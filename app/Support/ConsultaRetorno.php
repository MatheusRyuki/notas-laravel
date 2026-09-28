<?php

namespace App\Support;

use Illuminate\Http\Request;

class ConsultaRetorno
{
    public static function consultaRetorno(Request $request): array
    {
        return collect($request->only(['q', 'ordem', 'etiquetas']))
            ->reject(fn ($valor) => $valor === null || $valor === '' || $valor === [])
            ->all();
    }
}
