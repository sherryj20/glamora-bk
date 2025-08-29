<?php

namespace App\Services;

use Google\Cloud\Storage\StorageClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class GCSService
{
    private $bucket;

    public function __construct()
    {
        $keyFilePath = storage_path('app/' . env('GOOGLE_CLOUD_KEY_FILE'));

        if (!file_exists($keyFilePath)) {
            throw new \RuntimeException("No se encontró el keyfile en: {$keyFilePath}");
        }

        $storage = new StorageClient([
            'projectId'   => env('GOOGLE_CLOUD_PROJECT_ID'),
            'keyFilePath' => $keyFilePath,
        ]);

        $this->bucket = $storage->bucket(env('GOOGLE_CLOUD_STORAGE_BUCKET'));

        if (!$this->bucket->exists()) {
            throw new \RuntimeException(
                'El bucket configurado no existe: ' . env('GOOGLE_CLOUD_STORAGE_BUCKET')
            );
        }
    }

    /**
     * Sube un archivo al bucket.
     * - $file puede ser UploadedFile o ruta local (string).
     * - $destPath es la ruta destino en el bucket, p.ej. "uploads/2025/08/miimagen.jpg".
     * - $generateFirebaseToken si true, agrega el metadato firebaseStorageDownloadTokens.
     *
     * Devuelve info básica + el token (si se generó).
     */
    public function upload($file, string $destPath, bool $generateFirebaseToken = false): array
    {
        if ($file instanceof UploadedFile) {
            $source      = fopen($file->getRealPath(), 'r');
            $contentType = $file->getMimeType();
        } elseif (is_string($file) && file_exists($file)) {
            $source      = fopen($file, 'r');
            $contentType = mime_content_type($file) ?: 'application/octet-stream';
        } else {
            throw new \InvalidArgumentException('Parámetro $file inválido.');
        }

        $name = ltrim($destPath, '/');
        $metadata = [];

        // Si quieres una URL "estilo Firebase" persistente, necesitas este token:
        if ($generateFirebaseToken) {
            $metadata['firebaseStorageDownloadTokens'] = (string) Str::uuid();
        }

        // Nota: Firebase suele tener "Uniform bucket-level access" activado,
        // por eso NO seteamos ACL aquí (predefinedAcl). Usa URL firmada o token.
        $object = $this->bucket->upload($source, [
            'name'         => $name,
            'metadata'     => $metadata,
            'contentType'  => $contentType,
            'cacheControl' => 'public, max-age=31536000',
        ]);

        $info = $object->info();

        return [
            'bucket'         => $info['bucket'] ?? env('GOOGLE_CLOUD_STORAGE_BUCKET'),
            'name'           => $info['name'] ?? $name,
            'contentType'    => $info['contentType'] ?? $contentType,
            'size'           => (int)($info['size'] ?? 0),
            'firebase_token' => $metadata['firebaseStorageDownloadTokens'] ?? null,
        ];
    }

    /**
     * Genera una URL firmada (v4) que expira en $minutes minutos.
     */
    public function signedUrl(string $path, int $minutes = 15): string
    {
        $object  = $this->bucket->object(ltrim($path, '/'));
        $expires = new \DateTimeImmutable("+{$minutes} minutes");

        return $object->signedUrl($expires, ['version' => 'v4']);
    }

    /**
     * Construye la URL de descarga "estilo Firebase" usando el token.
     * Esta URL no expira (mientras el token siga en los metadatos del objeto).
     */
    public function firebaseDownloadUrl(string $path, string $token): string
    {
        $bucket = env('GOOGLE_CLOUD_STORAGE_BUCKET');
        $o = rawurlencode(ltrim($path, '/'));
        return "https://firebasestorage.googleapis.com/v0/b/{$bucket}/o/{$o}?alt=media&token={$token}";
    }

    public function delete(string $path): bool
    {
        $obj = $this->bucket->object(ltrim($path, '/'));
        if ($obj->exists()) {
            $obj->delete();
            return true;
        }
        return false;
    }
}
