<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImageController extends Controller
{
    public function getImage($path)
    {
        try {
            \Log::info("Attempting to retrieve image at path: {$path}");

            // Check if file exists
            if (!Storage::disk('db-backend')->exists($path)) {
                \Log::error("Image not found at path: {$path}");
                return response()->json(['message' => 'Image not found'], 404);
            }

            // Get file content
            $fileContent = Storage::disk('db-backend')->get($path);

            // Get mime type
            $mimeType = Storage::disk('db-backend')->mimeType($path);

            // Add debug log
            \Log::info("Successfully retrieved image", [
                'path' => $path,
                'mimeType' => $mimeType,
                'size' => strlen($fileContent)
            ]);

            // Return image with proper headers
            return response($fileContent)
                ->header('Content-Type', $mimeType)
                ->header('Cache-Control', 'public, max-age=86400');
        } catch (\Exception $e) {
            \Log::error("Error retrieving image: {$e->getMessage()}", [
                'path' => $path,
                'exception' => $e
            ]);
            return response()->json(['message' => 'Error retrieving image: ' . $e->getMessage()], 500);
        }
    }
}
