<?php

namespace App\Http\Requests;

use App\Models\Nota;
use Illuminate\Foundation\Http\FormRequest;

class ArquivarNotaRequest extends FormRequest
{
    protected $errorBag = 'arquivamentoNota';

    public function authorize(): bool
    {
        $nota = $this->route('nota');

        return $nota instanceof Nota && $this->user()?->can('update', $nota) === true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'arquivada' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'arquivada.required' => 'Informe o estado desejado do arquivamento.',
            'arquivada.boolean' => 'O estado do arquivamento é inválido.',
        ];
    }
}
