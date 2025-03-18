<?php

namespace SzentirasHu\Http\Controllers;

use Illuminate\Support\Facades\Config;
use Log;
use Storage;
use Symfony\Component\Mime\MimeTypes;
use SzentirasHu\Models\Media;

class MediaController extends Controller
{
    public function show($uuid)
    {
        $record = Media::where('uuid', $uuid)->with('mediaType')->firstOrFail();
        // Check if image path exists
        $imagePath = "media/{$record->id}";
        if (!Storage::exists($imagePath)) {
            Log::debug("Image not found: $imagePath");            
            abort(404, 'Image not found.');
        }
        $file = Storage::get($imagePath);
        $mimeType = $record->mime_type;
        $brand = Config::get('settings.brand.domain');
        $mimeTypes = new MimeTypes();
        $extension = $mimeTypes->getExtensions($mimeType)[0];
        $fileName = "{$brand}_{$record->usx_code}_{$record->chapter}_{$record->verse}_{$record->mediaType->name}.{$extension}";
        // Return the image as a response
        return response($file, 200)
                  ->header('Content-Type', $mimeType)
                  ->header('Content-Disposition', 'inline; filename="' . $fileName . '"');
    }
}
