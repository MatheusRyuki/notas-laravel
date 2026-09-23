<?php

namespace App\Enums;

enum PapelNota: string
{
    case Editor = 'editor';
    case Leitor = 'leitor';

    public function rotulo(): string
    {
        return $this === self::Editor ? 'Editor' : 'Leitor';
    }
}
