<?php

namespace App\Http\Requests;

use App\Enums\CorNota;
use App\Enums\FundoNota;
use App\Enums\TipoAparencia;
use App\Enums\TipoNota;
use App\Models\Nota;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateNotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $nota = $this->route('nota');

        return $nota instanceof Nota && $this->user()?->can('update', $nota) === true;
    }

    public function rules(): array
    {
        /** @var Nota $nota */
        $nota = $this->route('nota');
        $lista = $nota->tipo_conteudo === TipoNota::Lista;

        return [
            'revisao' => ['required', 'integer', 'min:1'],
            'titulo' => [$lista ? 'nullable' : 'nullable', $lista ? 'string' : 'required_without:descricao', 'string', 'max:255'],
            'descricao' => [$lista ? 'nullable' : 'nullable', $lista ? 'string' : 'required_without:titulo', 'string', 'max:10000'],
            'tipo_conteudo' => ['required', Rule::in([$nota->tipo_conteudo->value])],
            'itens' => [$lista ? 'required' : 'nullable', 'array', $lista ? 'min:1' : 'max:100', 'max:100'],
            'itens.*.id' => ['nullable', 'integer', 'distinct'],
            'itens.*.texto' => ['required', 'string', 'max:500'],
            'itens.*.concluido' => ['sometimes', 'boolean'],
            'tipo_aparencia' => ['sometimes', 'required', new Enum(TipoAparencia::class)],
            'cor' => ['nullable', 'required_if:tipo_aparencia,cor', new Enum(CorNota::class)],
            'fundo' => ['nullable', 'required_if:tipo_aparencia,imagem', new Enum(FundoNota::class)],
        ];
    }

    public function messages(): array
    {
        return array_merge((new StoreNotaRequest)->messages(), [
            'revisao.required' => 'A revisão da nota é obrigatória.',
            'tipo_conteudo.in' => 'O tipo da nota não pode ser alterado durante a edição.',
        ]);
    }

    protected function prepareForValidation(): void
    {
        $dados = [
            'titulo' => $this->textoLimpo('titulo'),
            'descricao' => $this->textoLimpo('descricao'),
        ];

        if ($this->exists('tipo_aparencia') || $this->exists('cor') || $this->exists('fundo')) {
            $dados['tipo_aparencia'] = $this->input(
                'tipo_aparencia',
                $this->exists('fundo') ? TipoAparencia::Imagem->value : TipoAparencia::Cor->value,
            );
        }

        if ($this->exists('itens')) {
            $dados['itens'] = collect($this->input('itens', []))->map(fn ($item) => [
                'id' => is_array($item) ? ($item['id'] ?? null) : null,
                'texto' => is_array($item) && is_string($item['texto'] ?? null) ? trim($item['texto']) : null,
                'concluido' => filter_var(is_array($item) ? ($item['concluido'] ?? false) : false, FILTER_VALIDATE_BOOL),
            ])->filter(fn ($item) => $item['texto'] !== null && $item['texto'] !== '')->values()->all();
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
