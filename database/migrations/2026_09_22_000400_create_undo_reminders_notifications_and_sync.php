<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operacoes_desfazer', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->string('tipo', 30);
            $table->json('dados');
            $table->timestamp('expira_em');
            $table->timestamp('utilizada_em')->nullable();
            $table->timestamps();
        });

        Schema::create('lembretes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nota_id')->constrained('notas')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('agendado_em');
            $table->string('fuso_horario', 64);
            $table->boolean('enviar_email')->default(false);
            $table->boolean('ativo')->default(true);
            $table->boolean('suspenso_lixeira')->default(false);
            $table->timestamp('processado_em')->nullable();
            $table->timestamps();
            $table->unique(['nota_id', 'usuario_id']);
            $table->index(['ativo', 'agendado_em']);
        });

        Schema::create('notificacoes_internas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('nota_id')->nullable()->constrained('notas')->nullOnDelete();
            $table->string('tipo', 30);
            $table->string('chave')->unique();
            $table->json('dados');
            $table->timestamp('lida_em')->nullable();
            $table->timestamps();
        });

        Schema::create('operacoes_sincronizacao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('operacao_uuid');
            $table->string('acao', 20);
            $table->uuid('id_local')->nullable();
            $table->json('resultado');
            $table->timestamps();
            $table->unique(['usuario_id', 'operacao_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operacoes_sincronizacao');
        Schema::dropIfExists('notificacoes_internas');
        Schema::dropIfExists('lembretes');
        Schema::dropIfExists('operacoes_desfazer');
    }
};
