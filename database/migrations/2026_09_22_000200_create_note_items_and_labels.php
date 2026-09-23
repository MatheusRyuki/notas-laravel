<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nota_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nota_id')->constrained('notas')->cascadeOnDelete();
            $table->string('texto', 500);
            $table->boolean('concluido')->default(false);
            $table->unsignedInteger('posicao');
            $table->timestamps();
            $table->unique(['nota_id', 'posicao']);
        });

        Schema::create('etiquetas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->string('nome', 60);
            $table->string('nome_normalizado', 60);
            $table->timestamps();
            $table->unique(['usuario_id', 'nome_normalizado']);
        });

        Schema::create('etiqueta_nota', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etiqueta_id')->constrained('etiquetas')->cascadeOnDelete();
            $table->foreignId('nota_id')->constrained('notas')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['usuario_id', 'nota_id', 'etiqueta_id']);
            $table->index(['usuario_id', 'etiqueta_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etiqueta_nota');
        Schema::dropIfExists('etiquetas');
        Schema::dropIfExists('nota_itens');
    }
};
