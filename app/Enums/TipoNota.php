<?php

namespace App\Enums;

enum TipoNota: string
{
    case Texto = 'texto';
    case Lista = 'lista';

    public function rotulo(): string
    {
        return $this === self::Texto ? 'Texto' : 'Lista de tarefas';
    }
}
