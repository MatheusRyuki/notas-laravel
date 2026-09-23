<?php

namespace App\Enums;

enum StatusConvite: string
{
    case Pendente = 'pendente';
    case Aceito = 'aceito';
    case Recusado = 'recusado';
}
