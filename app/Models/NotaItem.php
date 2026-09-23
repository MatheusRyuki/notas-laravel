<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['texto', 'concluido', 'posicao'])]
class NotaItem extends Model
{
    protected $table = 'nota_itens';

    /** @return BelongsTo<Nota, $this> */
    public function nota(): BelongsTo
    {
        return $this->belongsTo(Nota::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['concluido' => 'boolean', 'posicao' => 'integer'];
    }
}
