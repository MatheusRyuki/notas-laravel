<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nota_participantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nota_id')->constrained('notas')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->string('papel', 10);
            $table->timestamps();
            $table->unique(['nota_id', 'usuario_id']);
        });

        Schema::create('convites_notas', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->foreignId('nota_id')->constrained('notas')->cascadeOnDelete();
            $table->foreignId('convidado_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('convidado_por')->constrained('users')->cascadeOnDelete();
            $table->string('email_destino');
            $table->string('papel', 10);
            $table->string('status', 12)->default('pendente');
            $table->timestamp('respondido_em')->nullable();
            $table->timestamps();
            $table->unique(['nota_id', 'convidado_id', 'status'], 'convite_nota_usuario_status_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convites_notas');
        Schema::dropIfExists('nota_participantes');
    }
};
