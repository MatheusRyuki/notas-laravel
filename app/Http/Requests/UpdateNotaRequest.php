<?php

namespace App\Http\Requests;

use App\Enums\CorNota;
use App\Enums\FundoNota;
use App\Enums\TipoAparencia;
use App\Models\Nota;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateNotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $nota = $this->route('nota');

        return $nota instanceof Nota && $this->user()?->can('update', $nota) === true;
    }

    /** @return array<string, list<string>|string> */
    public function rules(): array
    {
        return [
            'titulo' => ['nullable', 'required_without:descricao', 'string', 'max:255'],
            'descricao' => ['nullable', 'required_without:titulo', 'string', 'max:10000'],
            'tipo_aparencia' => ['sometimes', 'required', new Enum(TipoAparencia::class)],
            'cor' => ['nullable', 'required_if:tipo_aparencia,cor', new Enum(CorNota::class)],
            'fundo' => ['nullable', 'required_if:tipo_aparencia,imagem', new Enum(FundoNota::class)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'titulo.required_without' => 'Informe um título ou uma descrição.',
            'titulo.max' => 'O título não pode ter mais de 255 caracteres.',
            'descricao.required_without' => 'Informe um título ou uma descrição.',
            'descricao.max' => 'A descrição não pode ter mais de 10.000 caracteres.',
            'tipo_aparencia.required' => 'Escolha uma aparência para a nota.',
            'tipo_aparencia.enum' => 'O tipo de aparência escolhido é inválido.',
            'cor.required_if' => 'Escolha uma cor de fundo.',
            'cor.enum' => 'A cor de fundo escolhida é inválida.',
            'fundo.required_if' => 'Escolha um fundo para a nota.',
            'fundo.enum' => 'O fundo escolhido é inválido.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $dados = [
            'titulo' => $this->textoLimpo('titulo'),
            'descricao' => $this->textoLimpo('descricao'),
        ];

        if ($this->exists('tipo_aparencia') || $this->exists('cor') || $this->exists('fundo')) {
            $tipo = $this->input('tipo_aparencia');

            if (! $this->exists('tipo_aparencia')) {
                $tipo = $this->exists('fundo') ? TipoAparencia::Imagem->value : TipoAparencia::Cor->value;
            }

            $dados['tipo_aparencia'] = $tipo;
        }

        $this->merge($dados);
    }

    private function textoLimpo(string $campo): ?string
    {
        $valor = $this->input($campo);

        if (! is_string($valor)) {
            return $valor;
        }

        $valor = trim($valor);

        return $valor === '' ? null : $valor;
    }
}
