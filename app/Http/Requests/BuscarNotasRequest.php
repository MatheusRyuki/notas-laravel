<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BuscarNotasRequest extends FormRequest
{
    public const LIMITE = 100;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:'.self::LIMITE],
            'ordem' => ['nullable', Rule::in(['atualizacao', 'criacao', 'titulo'])],
            'etiquetas' => ['nullable', 'array'],
            'etiquetas.*' => ['integer', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return [
            'q.string' => 'O termo de busca deve ser um texto.',
            'q.max' => 'A busca não pode ter mais de '.self::LIMITE.' caracteres.',
            'ordem.in' => 'A ordenação escolhida é inválida.',
            'etiquetas.array' => 'O filtro de etiquetas é inválido.',
            'etiquetas.*.integer' => 'O filtro de etiquetas é inválido.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $termo = $this->query('q');
        if (is_string($termo)) {
            $termo = trim($termo);
        }

        $etiquetas = array_values(array_unique(array_map('intval', (array) $this->query('etiquetas', []))));

        $this->merge([
            'q' => $termo === '' ? null : $termo,
            'ordem' => $this->query('ordem', 'atualizacao'),
            'etiquetas' => array_values(array_filter($etiquetas, fn ($id) => $id > 0)),
        ]);
    }
}
