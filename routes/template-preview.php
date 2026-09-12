<?php

use Designer\Studio\Http\Controllers\TemplatePreviewController as Preview;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Template preview (local development only)
|--------------------------------------------------------------------------
|
| Loaded by StudioServiceProvider only when TemplatePreview::enabled():
| the app runs locally and `studio.template_preview.path` names a folder.
|
|   /template                        every template in the folder
|   /template/{slug}                 its home page, rendered from the working tree
|   /template/{slug}/{path}          any other page, including [collection.field] pages
|   /template/{slug}/_files/{path}   its files/public
|
*/

Route::group([
    'prefix' => config('studio.template_preview.prefix', 'template'),
    'middleware' => config('studio.middleware', ['web']),
    'as' => 'studio.template-preview.',
], function () {
    Route::get('/', [Preview::class, 'index'])->name('index');
    Route::get('/{slug}/_files/{path}', [Preview::class, 'file'])->where(['slug' => '[a-z0-9-]+', 'path' => '.+'])->name('file');
    Route::get('/{slug}/_thumbnail', [Preview::class, 'thumbnail'])->where('slug', '[a-z0-9-]+')->name('thumbnail');
    Route::get('/{slug}/{path?}', [Preview::class, 'show'])->where(['slug' => '[a-z0-9-]+', 'path' => '.*'])->name('show');
});
