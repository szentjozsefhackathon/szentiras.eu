<?php

namespace SzentirasHu\Http\Controllers;

use Illuminate\Http\Request;
use Log;
use Storage;
use SzentirasHu\Models\Media;

class MediaController extends Controller
{
    public function show($uuid)
    {
        // Fetch the record
        $record = Media::where('uuid', $uuid)->firstOrFail();
        // Check if image path exists
        $imagePath = "media/{$record->id}";
        if (!Storage::exists($imagePath)) {
            Log::debug("Image not found: $imagePath");            
            abort(404, 'Image not found.');
        }
        $file = Storage::get($imagePath);
        $mimeType = $record->mime_type;
        // Return the image as a response
        return response($file, 200)
                  ->header('Content-Type', $mimeType);
    }
}
