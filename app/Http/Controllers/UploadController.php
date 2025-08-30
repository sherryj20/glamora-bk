<?php

namespace App\Http\Controllers;

use App\Services\GCSService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    public function uploadToFirebase(Request $request, GCSService $gcs)
    {
        $request->validate([
            'file' => ['required','file','max:10240'], // 10 MB
        ]);

        $file = $request->file('file');
        $ext  = $file->getClientOriginalExtension() ?: $file->extension();
        $dest = 'uploads/' . date('Y/m/d') . '/' . Str::uuid() . ($ext ? ".{$ext}" : '');

        $res = $gcs->upload($file, $dest, true);

        $publicUrl = $gcs->firebaseDownloadUrl($res['name'], $res['firebase_token']);

        // Opción B (temporal): URL firmada (ej. válida 60 min)
        // $publicUrl = $gcs->signedUrl($res['name'], 60);

        return response()->json([
            'path'        => $res['name'],
            'contentType' => $res['contentType'],
            'size'        => $res['size'],
            'url'         => $publicUrl,
        ]);
    }
}
