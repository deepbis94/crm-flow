<?php

use App\Http\Controllers\Workspace\AgentWorkspaceController;
use App\Http\Controllers\Workspace\SessionController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [SessionController::class, 'create'])->name('login');
Route::post('/login', [SessionController::class, 'store']);
Route::post('/logout', [SessionController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/', fn () => redirect()->route('workspace.queue'));
    Route::get('/workspace', [AgentWorkspaceController::class, 'queue'])->name('workspace.queue');
    Route::get('/workspace/leads/{lead}', [AgentWorkspaceController::class, 'show'])->name('workspace.leads.show');
    Route::post('/workspace/leads/{lead}/claim', [AgentWorkspaceController::class, 'claim'])->name('workspace.leads.claim');
    Route::post('/workspace/leads/{lead}/working', [AgentWorkspaceController::class, 'working'])->name('workspace.leads.working');
    Route::post('/workspace/leads/{lead}/notes', [AgentWorkspaceController::class, 'note'])->name('workspace.leads.notes');
    Route::post('/workspace/leads/{lead}/convert', [AgentWorkspaceController::class, 'convert'])->name('workspace.leads.convert');
    Route::get('/workspace/supervisor', [AgentWorkspaceController::class, 'supervisor'])->name('workspace.supervisor');
    Route::post('/workspace/dead-letter/retry', [AgentWorkspaceController::class, 'retryDead'])->name('workspace.dead.retry');
});
