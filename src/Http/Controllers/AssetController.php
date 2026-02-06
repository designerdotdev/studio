<?php

namespace Designer\Studio\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AssetController
{
    protected array $allowedFiles = [
        'studio.js' => 'application/javascript',
        'studio-css.css' => 'text/css',
    ];

    public function __invoke(Request $request, string $file): Response
    {
        if (! isset($this->allowedFiles[$file])) {
            abort(404);
        }

        $path = __DIR__ . '/../../../dist/' . $file;

        if (! file_exists($path)) {
            abort(404);
        }

        $contents = file_get_contents($path);
        $etag = md5($contents);

        if ($request->header('If-None-Match') === $etag) {
            return response('', 304);
        }

        return response($contents, 200, [
            'Content-Type' => $this->allowedFiles[$file],
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => $etag,
        ]);
    }
}
