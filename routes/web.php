<?php

use Designer\Studio\Http\Controllers\StudioController;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => config('studio.path', 'studio'),
    'middleware' => config('studio.middleware', ['web']),
    'as' => 'studio.',
], function () {
    // Dashboard - list all pages
    Route::get('/', [StudioController::class, 'index'])->name('index');

    // Page editor
    Route::get('/page/{slug}', [StudioController::class, 'edit'])->name('page.edit');
    Route::get('/page/{slug}/iframe', [StudioController::class, 'iframe'])->name('page.iframe');

    // API endpoints
    Route::prefix('api')->group(function () {
        // Pages
        Route::post('/pages', [StudioController::class, 'createPage'])->name('api.pages.store');
        Route::delete('/pages/{slug}', [StudioController::class, 'deletePage'])->name('api.pages.destroy');
        Route::put('/pages/{slug}/components', [StudioController::class, 'updatePageComponents'])->name('api.pages.components.update');

        // Blade generation
        Route::post('/generate', [StudioController::class, 'generate'])->name('api.generate');
        Route::post('/generate/{slug}', [StudioController::class, 'generatePage'])->name('api.generate.page');
    });
});
