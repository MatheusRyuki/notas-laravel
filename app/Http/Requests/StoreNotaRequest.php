<?php

namespace App\Http\Requests;

use App\Enums\CorNota;
use App\Enums\FundoNota;
use App\Enums\TipoAparencia;
use App\Enums\TipoNota;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreNotaRequest extends FormRequest
{
    protected $errorBag = 'criacaoNota';

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $lista = $this->input('tipo_conteudo') === TipoNota::Lista->value;

        return [
            'titulo' => [$lista ? 'nullable' : 'nullable', $lista ? 'string' : 'required_without:descricao', 'string', 'max:255'],
            'descricao' => [$lista ? 'nullable' : 'nullable', $lista ? 'string' : 'required_without:titulo', 'string', 'max:10000'],
            'tipo_conteudo' => ['required', new Enum(TipoNota::class)],
            'itens' => [$lista ? 'required' : 'nullable', 'array', $lista ? 'min:1' : 'max:100', 'max:100'],
            'itens.*.texto' => ['required', 'string', 'max:500'],
            'itens.*.concluido' => ['sometimes', 'boolean'],
            'tipo_aparencia' => ['required', new Enum(TipoAparencia::class)],
            'cor' => ['nullable', 'required_if:tipo_aparencia,cor', new Enum(CorNota::class)],
            'fundo' => ['nullable', 'required_if:tipo_aparencia,imagem', new Enum(FundoNota::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'titulo.required_without' => 'Informe um título ou uma descrição.',
            'titulo.max' => 'O título não pode ter mais de 255 caracteres.',
            'descricao.required_without' => 'Informe um título ou uma descrição.',
            'descricao.max' => 'A descrição não pode ter mais de 10.000 caracteres.',
            'tipo_conteudo.required' => 'Escolha entre nota de texto e lista de tarefas.',
            'tipo_conteudo.enum' => 'O tipo de nota escolhido é inválido.',
            'itens.required' => 'Adicione pelo menos um item à lista.',
            'itens.min' => 'Adicione pelo menos um item à lista.',
            'itens.max' => 'Uma lista pode ter no máximo 100 itens.',
            'itens.*.texto.required' => 'Todos os itens precisam de texto.',
            'itens.*.texto.max' => 'Cada item pode ter no máximo 500 caracteres.',
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
        $tipoAparencia = $this->input('tipo_aparencia');
        if (! $this->exists('tipo_aparencia')) {
            $tipoAparencia = $this->exists('fundo') ? TipoAparencia::Imagem->value : TipoAparencia::Cor->value;
        }

        $itens = collect($this->input('itens', []))->map(fn ($item) => [
            'texto' => is_array($item) && is_string($item['texto'] ?? null) ? trim($item['texto']) : null,
            'concluido' => filter_var(is_array($item) ? ($item['concluido'] ?? false) : false, FILTER_VALIDATE_BOOL),
        ])->filter(fn ($item) => $item['texto'] !== null && $item['texto'] !== '')->values()->all();

        $this->merge([
            'titulo' => $this->textoLimpo('titulo'),
            'descricao' => $this->textoLimpo('descricao'),
            'tipo_conteudo' => $this->input('tipo_conteudo', TipoNota::Texto->value),
            'itens' => $itens,
            'tipo_aparencia' => $tipoAparencia,
            'cor' => $this->input('cor', CorNota::Padrao->value),
        ]);
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
