<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageService
{
    public function storeWebp(UploadedFile $file, string $folder, int $quality = 80): string
    {
        $folder = trim($folder, '/');
        $quality = max(0, min(100, $quality));

        $mime = strtolower((string) ($file->getMimeType() ?? ''));
        if (! in_array($mime, ['image/jpeg', 'image/jpg', 'image/png'], true)) {
            throw new \InvalidArgumentException('Formato inválido. Solo se permite JPG o PNG.');
        }

        if (! function_exists('imagewebp')) {
            throw new \RuntimeException('La extensión GD no soporta WebP. Instala/activa soporte WebP en el servidor.');
        }

        $path = $file->getRealPath();
        if (! is_string($path) || $path === '' || ! is_file($path)) {
            throw new \RuntimeException('No se pudo leer el archivo subido.');
        }

        $image = match ($mime) {
            'image/png' => @imagecreatefrompng($path),
            default => @imagecreatefromjpeg($path),
        };

        if (! $image) {
            throw new \RuntimeException('No se pudo procesar la imagen.');
        }

        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        ob_start();
        imagewebp($image, null, $quality);
        $data = ob_get_clean();

        imagedestroy($image);

        if (! is_string($data) || $data === '') {
            throw new \RuntimeException('No se pudo convertir la imagen a WebP.');
        }

        $filename = Str::ulid().'.webp';
        $relativePath = $folder !== '' ? ($folder.'/'.$filename) : $filename;

        Storage::disk('public')->put($relativePath, $data);

        return $relativePath;
    }

    public function deletePublicFile(?string $path): void
    {
        $path = trim((string) $path);
        if ($path === '') {
            return;
        }

        Storage::disk('public')->delete($path);
    }
}

