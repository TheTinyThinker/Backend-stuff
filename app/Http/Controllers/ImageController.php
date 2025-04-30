<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImageController extends Controller
{
    public function getImage($path)
    {
        try {
            // Check if file exists
            if (!Storage::disk('db-backend')->exists($path)) {
                return response()->json(['message' => 'Image not found'], 404);
            }

            // Get file content using Storage::get()
            $fileContent = Storage::disk('db-backend')->get($path);

            // Get mime type
            $mimeType = Storage::disk('db-backend')->mimeType($path);

            // Return image with proper headers
            return response($fileContent)
                ->header('Content-Type', $mimeType)
                ->header('Cache-Control', 'public, max-age=86400');
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error retrieving image: ' . $e->getMessage()], 500);
        }
    }
}
