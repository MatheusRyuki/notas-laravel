<?php

namespace App\Enums;

enum CorNota: string
{
    case Padrao = 'padrao';
    case Areia = 'areia';
    case Menta = 'menta';
    case Ceu = 'ceu';
    case Lavanda = 'lavanda';
    case Pessego = 'pessego';

    public function rotulo(): string
    {
        return match ($this) {
            self::Padrao => 'Padrão',
            self::Areia => 'Areia',
            self::Menta => 'Menta',
            self::Ceu => 'Céu',
            self::Lavanda => 'Lavanda',
            self::Pessego => 'Pêssego',
        };
    }
}
