<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class MediaLibraryController extends Controller
{
    /** Allowed upload MIME types (images only for now). */
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    /** Maximum upload size in KB. */
    private const MAX_KB = 5120; // 5 MB

    public function index(Request $request)
    {
        $query = MediaFile::query()->latest();

        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where('original_filename', 'like', "%{$term}%");
        }

        if ($request->filled('type')) {
            $query->where('mime_type', 'like', $request->input('type') . '/%');
        }

        $files = $query->paginate(36)->withQueryString();

        return Inertia::render('media/IndexMedia', [
            'files'  => $files,
            'search' => $request->input('search', ''),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'max:' . self::MAX_KB,
                'mimes:jpeg,jpg,png,webp,gif',
            ],
        ]);

        $uploaded = $request->file('file');
        $mime     = $uploaded->getMimeType();

        $path = $uploaded->store('media', 'public');
        $url  = Storage::disk('public')->url($path);

        $media = MediaFile::create([
            'disk'              => 'public',
            'path'              => $path,
            'url'               => $url,
            'original_filename' => $uploaded->getClientOriginalName(),
            'mime_type'         => $mime,
            'size_bytes'        => $uploaded->getSize(),
        ]);

        return redirect()->back()->with('success', 'File uploaded.')->with('media', $media);
    }

    public function destroy(MediaFile $mediaFile)
    {
        // Remove physical file from disk first
        if (Storage::disk($mediaFile->disk ?? 'public')->exists($mediaFile->path)) {
            Storage::disk($mediaFile->disk ?? 'public')->delete($mediaFile->path);
        }

        $mediaFile->delete();

        return redirect()->back()->with('success', 'File deleted.');
    }
}
