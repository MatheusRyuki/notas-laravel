<?php

namespace App\Http\Requests;

use App\Models\Nota;
use Illuminate\Foundation\Http\FormRequest;

class FixarNotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $nota = $this->route('nota');

        return $nota instanceof Nota && $this->user()?->can('manageState', $nota) === true;
    }

    public function rules(): array
    {
        return ['fixada' => ['required', 'boolean']];
    }

    public function messages(): array
    {
        return [
            'fixada.required' => 'Informe o estado desejado da fixação.',
            'fixada.boolean' => 'O estado da fixação é inválido.',
        ];
    }
}
