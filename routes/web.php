<?php

use App\Http\Controllers\LixeiraController;
use App\Http\Controllers\NotaController;
use App\Http\Controllers\ProfileController;
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
    Route::patch('/notas/{nota}/fixacao', [NotaController::class, 'fixacao'])->name('notas.fixacao');
    Route::patch('/notas/{nota}/arquivamento', [NotaController::class, 'arquivamento'])->name('notas.arquivamento');
    Route::delete('/notas/{nota}/lixeira', [NotaController::class, 'moverLixeira'])->name('notas.mover-lixeira');
    Route::get('/notas/{nota}', [NotaController::class, 'show'])->name('notas.show');
    Route::patch('/notas/{nota}', [NotaController::class, 'update'])->name('notas.update');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
