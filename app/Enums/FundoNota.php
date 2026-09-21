<?php

namespace App\Enums;

enum FundoNota: string
{
    case Folhas = 'folhas';
    case Ondas = 'ondas';
    case Geometria = 'geometria';
    case Constelacao = 'constelacao';

    public function rotulo(): string
    {
        return match ($this) {
            self::Folhas => 'Folhas tranquilas',
            self::Ondas => 'Ondas suaves',
            self::Geometria => 'Formas serenas',
            self::Constelacao => 'Céu pontilhado',
        };
    }

    public function caminho(): string
    {
        return match ($this) {
            self::Folhas => 'images/fundos/folhas-tranquilas.svg',
            self::Ondas => 'images/fundos/ondas-suaves.svg',
            self::Geometria => 'images/fundos/formas-serenas.svg',
            self::Constelacao => 'images/fundos/ceu-pontilhado.svg',
        };
    }

    public static function porCaminho(?string $caminho): ?self
    {
        if ($caminho === null) {
            return null;
        }

        foreach (self::cases() as $fundo) {
            if ($fundo->caminho() === $caminho) {
                return $fundo;
            }
        }

        return null;
    }
}
