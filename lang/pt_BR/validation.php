<?php

return [
    'required' => 'O campo :attribute é obrigatório.',
    'string' => 'O campo :attribute deve ser um texto.',
    'email' => 'Informe um e-mail válido.',
    'lowercase' => 'O campo :attribute deve conter apenas letras minúsculas.',
    'confirmed' => 'A confirmação de :attribute não confere.',
    'unique' => 'Este :attribute já está em uso.',
    'current_password' => 'A senha atual está incorreta.',
    'max' => ['string' => 'O campo :attribute não pode ter mais de :max caracteres.'],
    'min' => ['string' => 'O campo :attribute deve ter pelo menos :min caracteres.'],
    'password' => [
        'letters' => 'A :attribute deve conter pelo menos uma letra.',
        'mixed' => 'A :attribute deve conter letras maiúsculas e minúsculas.',
        'numbers' => 'A :attribute deve conter pelo menos um número.',
        'symbols' => 'A :attribute deve conter pelo menos um símbolo.',
        'uncompromised' => 'Esta :attribute apareceu em um vazamento de dados. Escolha outra.',
    ],
    'attributes' => [
        'name' => 'nome',
        'email' => 'e-mail',
        'password' => 'senha',
        'password_confirmation' => 'confirmação de senha',
        'current_password' => 'senha atual',
        'token' => 'código de recuperação',
    ],
];
