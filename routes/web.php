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
    Route::get('/assets/{file}', AssetController::class)->where('file', '[a-zA-Z0-9._-]+')->name('assets');

    // Studio editor (single route)
    Route::get('/', [StudioController::class, 'index'])->name('index');

    // Iframe preview for pages
    Route::get('/page/{slug}/iframe', [StudioController::class, 'iframe'])->where('slug', '[a-z0-9-]+')->name('page.iframe');

    // Rendered previews (section picker thumbnails) + template thumbnails
    Route::get('/preview/component/{name}', [StudioController::class, 'componentPreview'])->where('name', '[a-z0-9-]+')->name('preview.component');
    Route::get('/preview/block/{slug}', [StudioController::class, 'blockPreview'])->where('slug', '[a-z0-9-]+')->name('preview.block');
    Route::get('/preview/thumbnail/{name}', [StudioController::class, 'templateThumbnail'])->where('name', '[a-z0-9-]+')->name('preview.thumbnail');

    // Draft site preview (page slugs never contain '/', so these can't
    // shadow the two-segment preview routes above)
    Route::get('/preview', [StudioController::class, 'previewPage'])->name('preview.home');
    Route::get('/preview/{slug}', [StudioController::class, 'previewPage'])->where('slug', '[a-z0-9-]+')->name('preview.page');

    // API endpoints
    Route::prefix('api')->group(function () {
        // Pages
        Route::post('/pages', [StudioController::class, 'createPage'])->name('api.pages.store');
        Route::put('/pages/{slug}', [StudioController::class, 'updatePage'])->where('slug', '[a-z0-9-]+')->name('api.pages.update');
        Route::post('/pages/{slug}/duplicate', [StudioController::class, 'duplicatePage'])->where('slug', '[a-z0-9-]+')->name('api.pages.duplicate');
        Route::delete('/pages/{slug}', [StudioController::class, 'deletePage'])->where('slug', '[a-z0-9-]+')->name('api.pages.destroy');
        Route::put('/pages/{slug}/components', [StudioController::class, 'updatePageComponents'])->where('slug', '[a-z0-9-]+')->name('api.pages.components.update');
        Route::post('/pages/{slug}/components/add', [StudioController::class, 'addComponentToPage'])->where('slug', '[a-z0-9-]+')->name('api.pages.components.add');
        Route::delete('/pages/{slug}/components/{componentId}', [StudioController::class, 'removeComponentFromPage'])->where(['slug' => '[a-z0-9-]+', 'componentId' => '[a-zA-Z0-9_-]+'])->name('api.pages.components.remove');
        Route::post('/pages/{slug}/components/{componentId}/move', [StudioController::class, 'moveComponentOnPage'])->where(['slug' => '[a-z0-9-]+', 'componentId' => '[a-zA-Z0-9_-]+'])->name('api.pages.components.move');

        // Live canvas rendering — one round trip per edit, batched across
        // the sibling placements of a global block. Typing is the load
        // here, so the limit is generous.
        Route::post('/render', \Designer\Studio\Http\Controllers\RenderController::class)->middleware('throttle:600,1')->name('api.render');

        // Uploads (image fields) — public/designer/uploads
        Route::post('/upload', [StudioController::class, 'upload'])->middleware('throttle:30,1')->name('api.upload');

        // Media library (public/designer)
        Route::get('/media', [\Designer\Studio\Http\Controllers\MediaController::class, 'index'])->name('api.media.index');
        Route::post('/media/upload', [\Designer\Studio\Http\Controllers\MediaController::class, 'upload'])->middleware('throttle:60,1')->name('api.media.upload');
        Route::post('/media/folder', [\Designer\Studio\Http\Controllers\MediaController::class, 'folder'])->middleware('throttle:60,1')->name('api.media.folder');
        Route::patch('/media', [\Designer\Studio\Http\Controllers\MediaController::class, 'update'])->middleware('throttle:120,1')->name('api.media.update');
        Route::post('/media/duplicate', [\Designer\Studio\Http\Controllers\MediaController::class, 'duplicate'])->middleware('throttle:60,1')->name('api.media.duplicate');
        Route::delete('/media', [\Designer\Studio\Http\Controllers\MediaController::class, 'destroy'])->middleware('throttle:60,1')->name('api.media.destroy');

        // Onboarding
        Route::post('/onboarding/apply-template', [StudioController::class, 'applyTemplate'])->name('api.onboarding.apply');

        // Code mode — the workspace file tree and its editor (404s unless
        // the dev-mode gate passes). Paths travel in the query/body, not the
        // URL, so the route itself needs no slug constraint.
        Route::get('/code/tree', [\Designer\Studio\Http\Controllers\CodeController::class, 'tree'])->name('api.code.tree');
        Route::get('/code/file', [\Designer\Studio\Http\Controllers\CodeController::class, 'show'])->name('api.code.show');
        Route::put('/code/file', [\Designer\Studio\Http\Controllers\CodeController::class, 'update'])->middleware('throttle:60,1')->name('api.code.update');
        Route::post('/code/section', [\Designer\Studio\Http\Controllers\CodeController::class, 'store'])->middleware('throttle:20,1')->name('api.code.store');
        Route::delete('/code/file', [\Designer\Studio\Http\Controllers\CodeController::class, 'destroy'])->middleware('throttle:20,1')->name('api.code.destroy');

        // Dev mode — section source editing (404s unless the gate passes)
        Route::get('/dev/components/{name}', [\Designer\Studio\Http\Controllers\DevModeController::class, 'show'])->where('name', '[a-z0-9-]+')->name('api.dev.components.show');
        Route::put('/dev/components/{name}', [\Designer\Studio\Http\Controllers\DevModeController::class, 'update'])->where('name', '[a-z0-9-]+')->middleware('throttle:30,1')->name('api.dev.components.update');
        Route::post('/dev/components/{name}/field', [\Designer\Studio\Http\Controllers\DevModeController::class, 'promoteField'])->where('name', '[a-z0-9-]+')->middleware('throttle:30,1')->name('api.dev.components.field');

        // Assistant — the local AI CLI (404s unless dev mode is on)
        Route::get('/assistant/engines', [\Designer\Studio\Http\Controllers\AssistantController::class, 'engines'])->name('api.assistant.engines');
        Route::post('/assistant/turn', [\Designer\Studio\Http\Controllers\AssistantController::class, 'turn'])->middleware('throttle:60,1')->name('api.assistant.turn');
        Route::get('/assistant/stream/{turn}', [\Designer\Studio\Http\Controllers\AssistantController::class, 'stream'])->where('turn', '[a-f0-9-]{36}')->name('api.assistant.stream');
        Route::delete('/assistant/stream/{turn}', [\Designer\Studio\Http\Controllers\AssistantController::class, 'stop'])->where('turn', '[a-f0-9-]{36}')->name('api.assistant.stop');

        // Draft publishing
        Route::get('/publish/status', [StudioController::class, 'publishStatus'])->name('api.publish.status');
        Route::post('/publish', [StudioController::class, 'publishSite'])->middleware('throttle:12,1')->name('api.publish');
        Route::post('/publish/discard', [StudioController::class, 'discardDraft'])->middleware('throttle:12,1')->name('api.publish.discard');
    });
});
