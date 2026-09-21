<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BuscarNotasRequest extends FormRequest
{
    public const LIMITE = 100;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['q' => ['nullable', 'string', 'max:'.self::LIMITE]];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'q.string' => 'O termo de busca deve ser um texto.',
            'q.max' => 'A busca não pode ter mais de '.self::LIMITE.' caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $termo = $this->query('q');

        if (is_string($termo)) {
            $termo = trim($termo);
        }

        $this->merge(['q' => $termo === '' ? null : $termo]);
    }
}
