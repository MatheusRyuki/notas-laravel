<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('fuso_horario', 64)->default('America/Sao_Paulo')->after('password');
        });

        Schema::table('notas', function (Blueprint $table) {
            $table->string('tipo_conteudo', 10)->default('texto')->after('descricao');
            $table->unsignedBigInteger('revisao')->default(1)->after('tipo_conteudo');
            $table->uuid('uuid_sincronizacao')->nullable()->unique()->after('revisao');
        });

        DB::table('notas')
            ->whereNull('uuid_sincronizacao')
            ->orderBy('id')
            ->eachById(fn ($nota) => DB::table('notas')
                ->where('id', $nota->id)
                ->update(['uuid_sincronizacao' => (string) Str::uuid()]));
    }

    public function down(): void
    {
        Schema::table('notas', function (Blueprint $table) {
            $table->dropUnique(['uuid_sincronizacao']);
            $table->dropColumn(['tipo_conteudo', 'revisao', 'uuid_sincronizacao']);
        });

        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('fuso_horario'));
    }
};
