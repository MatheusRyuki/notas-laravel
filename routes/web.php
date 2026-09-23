<?php

use App\Http\Controllers\CompartilhamentoController;
use App\Http\Controllers\DesfazerController;
use App\Http\Controllers\EtiquetaController;
use App\Http\Controllers\ExportarNotaController;
use App\Http\Controllers\LembreteController;
use App\Http\Controllers\LixeiraController;
use App\Http\Controllers\LoteNotaController;
use App\Http\Controllers\NotaController;
use App\Http\Controllers\NotaEtiquetaController;
use App\Http\Controllers\NotificacaoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SincronizacaoController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

if (app()->environment('testing')) {
    Route::get('/_diagnostico/ambiente-verificacao', function () {
        return response()->json([
            'ambiente' => app()->environment(),
            'driver_modelos' => config('database.default'),
            'database_modelos' => DB::connection()->getDatabaseName(),
            'sessao' => config('session.driver'),
            'cache' => config('cache.default'),
            'config_cache' => app()->configurationIsCached(),
        ]);
    })->name('diagnostico.verificacao');
}

Route::middleware('auth')->group(function () {
    Route::get('/', [NotaController::class, 'index'])->name('notas.inicio');
    Route::get('/arquivadas', [NotaController::class, 'arquivadas'])->name('notas.arquivadas');

    Route::get('/lixeira', [LixeiraController::class, 'index'])->name('lixeira.index');
    Route::get('/lixeira/{nota}/confirmar-exclusao', [LixeiraController::class, 'confirmarExclusao'])->name('lixeira.confirmar-exclusao');
    Route::get('/lixeira/{nota}', [LixeiraController::class, 'show'])->name('lixeira.show');
    Route::patch('/lixeira/{nota}/restauracao', [LixeiraController::class, 'restaurar'])->name('lixeira.restaurar');
    Route::delete('/lixeira/{nota}', [LixeiraController::class, 'destruir'])->name('lixeira.destruir');

    Route::post('/notas', [NotaController::class, 'store'])->name('notas.store');
    Route::post('/notas/lote', LoteNotaController::class)->name('notas.lote');
    Route::patch('/notas/{nota}/fixacao', [NotaController::class, 'fixacao'])->name('notas.fixacao');
    Route::patch('/notas/{nota}/arquivamento', [NotaController::class, 'arquivamento'])->name('notas.arquivamento');
    Route::delete('/notas/{nota}/lixeira', [NotaController::class, 'moverLixeira'])->name('notas.mover-lixeira');
    Route::get('/notas/{nota}/exportacao', ExportarNotaController::class)->whereNumber('nota')->name('notas.exportar');
    Route::put('/notas/{nota}/etiquetas', [NotaEtiquetaController::class, 'update'])->name('notas.etiquetas');
    Route::post('/notas/{nota}/lembrete', [LembreteController::class, 'store'])->name('notas.lembrete');
    Route::post('/notas/{nota}/convites', [CompartilhamentoController::class, 'convidar'])->name('notas.convidar');
    Route::patch('/notas/{nota}/participantes/{participante}', [CompartilhamentoController::class, 'alterarPapel'])->name('notas.participantes.papel');
    Route::delete('/notas/{nota}/participantes/{participante}', [CompartilhamentoController::class, 'revogar'])->name('notas.participantes.revogar');
    Route::delete('/notas/{nota}/sair', [CompartilhamentoController::class, 'sair'])->name('notas.compartilhamento.sair');
    Route::get('/notas/{nota}', [NotaController::class, 'show'])->name('notas.show');
    Route::patch('/notas/{nota}', [NotaController::class, 'update'])->name('notas.update');

    Route::post('/desfazer/{token}', DesfazerController::class)->name('desfazer');

    Route::post('/etiquetas', [EtiquetaController::class, 'store'])->name('etiquetas.store');
    Route::patch('/etiquetas/{etiqueta}', [EtiquetaController::class, 'update'])->name('etiquetas.update');
    Route::delete('/etiquetas/{etiqueta}', [EtiquetaController::class, 'destroy'])->name('etiquetas.destroy');

    Route::get('/lembretes', [LembreteController::class, 'index'])->name('lembretes.index');
    Route::delete('/lembretes/{lembrete}', [LembreteController::class, 'destroy'])->name('lembretes.destroy');
    Route::patch('/notificacoes/{notificacao}/leitura', [NotificacaoController::class, 'ler'])->name('notificacoes.ler');

    Route::get('/compartilhamentos', [CompartilhamentoController::class, 'index'])->name('compartilhamentos.index');
    Route::patch('/convites/{token}', [CompartilhamentoController::class, 'responder'])->name('convites.responder');

    Route::get('/sincronizacao/bootstrap', [SincronizacaoController::class, 'bootstrap'])->name('sincronizacao.bootstrap');
    Route::post('/sincronizacao', [SincronizacaoController::class, 'sincronizar'])->name('sincronizacao.executar');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
