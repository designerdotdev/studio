<?php

use Designer\Studio\Http\Controllers\AssetController;
use Designer\Studio\Http\Controllers\StudioController;
use Illuminate\Support\Facades\Route;

$middleware = config('studio.middleware', ['web']);

// Optional authorization gate (e.g. 'viewStudio') applied to every route
if ($gate = config('studio.gate')) {
    $middleware[] = 'can:' . $gate;
}

Route::group([
    'prefix' => config('studio.path', 'studio'),
    'middleware' => $middleware,
    'as' => 'studio.',
], function () {
    // Package assets
    Route::get('/assets/{file}', AssetController::class)->name('assets');

    // Studio editor (single route)
    Route::get('/', [StudioController::class, 'index'])->name('index');

    // Iframe preview for pages
    Route::get('/page/{slug}/iframe', [StudioController::class, 'iframe'])->name('page.iframe');

    // Rendered previews (section picker thumbnails + onboarding templates)
    Route::get('/preview/component/{name}', [StudioController::class, 'componentPreview'])->name('preview.component');
    Route::get('/preview/template/{name}', [StudioController::class, 'templatePreview'])->name('preview.template');
    Route::get('/preview/block/{slug}', [StudioController::class, 'blockPreview'])->name('preview.block');

    // Draft site preview (page slugs never contain '/', so these can't
    // shadow the two-segment preview routes above)
    Route::get('/preview', [StudioController::class, 'previewPage'])->name('preview.home');
    Route::get('/preview/{slug}', [StudioController::class, 'previewPage'])->name('preview.page');

    // API endpoints
    Route::prefix('api')->group(function () {
        // Pages
        Route::post('/pages', [StudioController::class, 'createPage'])->name('api.pages.store');
        Route::put('/pages/{slug}', [StudioController::class, 'updatePage'])->name('api.pages.update');
        Route::post('/pages/{slug}/duplicate', [StudioController::class, 'duplicatePage'])->name('api.pages.duplicate');
        Route::delete('/pages/{slug}', [StudioController::class, 'deletePage'])->name('api.pages.destroy');
        Route::put('/pages/{slug}/components', [StudioController::class, 'updatePageComponents'])->name('api.pages.components.update');
        Route::post('/pages/{slug}/components/add', [StudioController::class, 'addComponentToPage'])->name('api.pages.components.add');
        Route::delete('/pages/{slug}/components/{componentId}', [StudioController::class, 'removeComponentFromPage'])->name('api.pages.components.remove');
        Route::post('/pages/{slug}/components/{componentId}/move', [StudioController::class, 'moveComponentOnPage'])->name('api.pages.components.move');

        // Uploads (image fields)
        Route::post('/upload', [StudioController::class, 'upload'])->name('api.upload');

        // Onboarding
        Route::post('/onboarding/apply-template', [StudioController::class, 'applyTemplate'])->name('api.onboarding.apply');

        // Blade generation
        Route::post('/generate', [StudioController::class, 'generate'])->name('api.generate');
        Route::post('/generate/{slug}', [StudioController::class, 'generatePage'])->name('api.generate.page');

        // Draft publishing
        Route::get('/publish/status', [StudioController::class, 'publishStatus'])->name('api.publish.status');
        Route::post('/publish', [StudioController::class, 'publishSite'])->name('api.publish');
        Route::post('/publish/discard', [StudioController::class, 'discardDraft'])->name('api.publish.discard');
    });
});
