<?php

namespace Designer\Studio\Http\Controllers;

use Designer\Studio\Services\MediaLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use InvalidArgumentException;

/**
 * JSON endpoints behind the Media panel. Every path is relative to
 * public/studio-uploads; MediaLibrary does the fencing.
 */
class MediaController extends Controller
{
    public function __construct(protected MediaLibrary $library) {}

    public function index(Request $request): JsonResponse
    {
        return $this->guard(fn () => [
            ...$this->library->list((string) $request->query('dir', '')),
            'all_folders' => $this->library->folders(),
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|image|mimes:jpeg,jpg,png,gif,webp,avif,svg|max:5120',
            'dir' => 'nullable|string|max:255',
        ], [
            'file.max' => 'This image is too large — the maximum upload size is 5 MB.',
            'file.image' => 'That file is not an image.',
            'file.mimes' => 'Only JPEG, PNG, GIF, WebP, AVIF, and SVG images can be uploaded.',
            'file.required' => 'No image was received by the server.',
        ]);

        return $this->guard(fn () => [
            'file' => $this->library->upload($request->file('file'), (string) $request->input('dir', '')),
        ]);
    }

    public function folder(Request $request): JsonResponse
    {
        $request->validate(['dir' => 'nullable|string|max:255', 'name' => 'required|string|max:80']);

        return $this->guard(fn () => [
            'path' => $this->library->createFolder((string) $request->input('dir', ''), $request->input('name')),
        ]);
    }

    /** Rename (`name`) or move (`to`) a file or folder */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'path' => 'required|string|max:512',
            'name' => 'nullable|string|max:120',
            'to' => 'nullable|string|max:512',
        ]);

        return $this->guard(function () use ($request) {
            $path = $request->input('path');

            if ($request->filled('name')) {
                $path = $this->library->rename($path, $request->input('name'));
            }

            if ($request->has('to')) {
                $path = $this->library->move($path, (string) $request->input('to'));
            }

            return ['path' => $path];
        });
    }

    public function duplicate(Request $request): JsonResponse
    {
        $request->validate(['path' => 'required|string|max:512']);

        return $this->guard(fn () => ['file' => $this->library->duplicate($request->input('path'))]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['path' => 'required|string|max:512']);

        return $this->guard(function () use ($request) {
            $this->library->delete($request->input('path'));

            return ['deleted' => $request->input('path')];
        });
    }

    protected function guard(callable $action): JsonResponse
    {
        try {
            return response()->json(['success' => true, ...$action()]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
