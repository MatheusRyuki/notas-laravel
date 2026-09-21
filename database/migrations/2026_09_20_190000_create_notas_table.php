<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->string('titulo', 255)->nullable();
            $table->text('descricao')->nullable();
            $table->boolean('fixada')->default(false);
            $table->boolean('arquivada')->default(false);
            $table->string('tipo_aparencia', 10)->default('cor');
            $table->string('cor', 20)->nullable();
            $table->string('caminho_imagem')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['usuario_id', 'arquivada', 'fixada', 'updated_at'], 'notas_listagem_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notas');
    }
};
