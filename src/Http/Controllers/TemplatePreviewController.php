<?php

namespace Designer\Studio\Http\Controllers;

use Designer\Studio\Services\Templates\TemplatePreview;

/**
 * /template — browse the template repositories in the configured folder
 * without installing any of them (local development only; see
 * TemplatePreview and `studio.template_preview`).
 */
class TemplatePreviewController
{
    public function index()
    {
        $preview = TemplatePreview::make();

        return response()
            ->view('studio::template-preview', [
                'templates' => $preview->catalog(),
                'root' => $preview->root(),
                'prefix' => $preview->prefix(),
            ])
            ->header('Cache-Control', 'no-store');
    }

    public function show(string $slug, ?string $path = null)
    {
        $page = TemplatePreview::make()->render($slug, (string) $path);

        abort_if($page === null, 404);

        return response($page['html'], $page['status'])
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Cache-Control', 'no-store');
    }

    public function file(string $slug, string $path)
    {
        $file = TemplatePreview::make()->file($slug, $path);

        abort_if($file === null, 404);

        $headers = ['Content-Type' => $file['type'], 'Cache-Control' => 'no-cache'];

        return $file['body'] !== null
            ? response($file['body'], 200, $headers)
            : response()->file($file['path'], $headers);
    }

    public function thumbnail(string $slug)
    {
        $path = TemplatePreview::make()->thumbnail($slug);

        abort_if($path === null, 404);

        return response()->file($path, ['Content-Type' => 'image/png', 'Cache-Control' => 'no-cache']);
    }
}
