<?php

namespace App\Services\Traits;

use Illuminate\Support\Facades\Log;

trait ResolvesMedia
{
    protected function resolvePhotoPath(string $photo): string
    {
        $photo = ltrim($photo, '/');
        if (str_starts_with($photo, 'storage/')) {
            $photo = substr($photo, 8);
        }

        $possible = [
            storage_path('app/private/' . $photo),
            storage_path('app/public/' . $photo),
            public_path('storage/' . $photo),
            storage_path('app/' . $photo),
            $photo,
        ];

        foreach ($possible as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        // Fallback to expected private location for posts/photos
        return storage_path('app/private/' . $photo);
    }

    protected function resolveVideoPath(string $video): string
    {
        $video = ltrim($video, '/');
        if (str_starts_with($video, 'storage/')) {
            $video = substr($video, 8);
        }

        $possible = [
            storage_path('app/private/' . $video),
            storage_path('app/public/' . $video),
            public_path('storage/' . $video),
            storage_path('app/' . $video),
            $video,
        ];

        foreach ($possible as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return storage_path('app/private/' . $video);
    }

    protected function isValidImage(string $path, int $maxBytes = 4_000_000): bool
    {
        $info = @getimagesize($path);
        if ($info === false) {
            Log::warning('File is not an image', ['path' => $path]);
            return false;
        }
        $size = @filesize($path) ?: 0;
        if ($size <= 0 || $size > $maxBytes) {
            Log::warning('Image invalid size', ['path' => $path, 'size' => $size, 'max' => $maxBytes]);
            return false;
        }
        $supported = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array(($info['mime'] ?? ''), $supported, true)) {
            Log::warning('Unsupported image mime', ['path' => $path, 'mime' => $info['mime'] ?? null]);
            return false;
        }
        return true;
    }

    protected function guessMime(string $path): ?string
    {
        if (function_exists('mime_content_type')) {
            return @mime_content_type($path) ?: null;
        }
        return null;
    }

    protected function isValidVideo(string $path, array $allowedExt = ['mp4','avi','mov','webm'], ?int $maxBytes = null): bool
    {
        if (!file_exists($path)) {
            return false;
        }
        $size = @filesize($path) ?: 0;
        if ($maxBytes !== null && $size > $maxBytes) {
            Log::warning('Video exceeds max size', ['path' => $path, 'size' => $size, 'max' => $maxBytes]);
            return false;
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            Log::warning('Unsupported video extension', ['path' => $path, 'ext' => $ext, 'allowed' => $allowedExt]);
            return false;
        }
        return true;
    }

    protected function probeVideoDurationSeconds(string $path): ?float
    {
        // Best-effort using ffprobe if present
        $which = trim((string) @shell_exec('command -v ffprobe 2>/dev/null'));
        if ($which === '') {
            return null;
        }
        $cmd = sprintf('ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s 2>/dev/null', escapeshellarg($path));
        $out = @shell_exec($cmd);
        if ($out === null) {
            return null;
        }
        $dur = (float) trim($out);
        return $dur > 0 ? $dur : null;
    }

    protected function ensureTempPublicUrl(string $path, string $prefix = 'tmp/instagram'): string
    {
        // Copy to public disk so Instagram can fetch via URL
        $filename = basename($path);
        $targetRel = $prefix . '/' . uniqid('', true) . '_' . $filename;
        $targetFull = storage_path('app/public/' . $targetRel);
        if (!is_dir(dirname($targetFull))) {
            @mkdir(dirname($targetFull), 0775, true);
        }
        if (!@copy($path, $targetFull)) {
            throw new \RuntimeException('Failed to copy media to public disk: ' . $targetFull);
        }
        // asset() relies on APP_URL; this must be publicly reachable by Instagram
        $url = asset('storage/' . $targetRel);
        return $url;
    }

    // --- Instagram image aspect helpers ---

    protected function ensureInstagramAspectCompliantImage(string $path, float $min = 0.8, float $max = 1.91, int $maxBytes = 8_000_000): string
    {
        $info = @getimagesize($path);
        if (!is_array($info)) {
            return $path;
        }
        $w = (int) ($info[0] ?? 0);
        $h = (int) ($info[1] ?? 0);
        if ($w <= 0 || $h <= 0) {
            return $path;
        }
        $ratio = $w / max(1, $h);
        if ($ratio >= $min && $ratio <= $max) {
            return $path; // Already compliant
        }

        // Choose target ratio by clamping
        $targetRatio = $ratio < $min ? $min : $max;

        // Compute crop rectangle centered
        if ($targetRatio > $ratio) {
            // Need to reduce height
            $newH = (int) floor($w / $targetRatio);
            $srcX = 0;
            $srcY = (int) max(0, floor(($h - $newH) / 2));
            $srcW = $w;
            $srcH = min($h, $newH);
        } else {
            // Need to reduce width
            $newW = (int) floor($h * $targetRatio);
            $srcX = (int) max(0, floor(($w - $newW) / 2));
            $srcY = 0;
            $srcW = min($w, $newW);
            $srcH = $h;
        }

        // Load source image
        $mime = $info['mime'] ?? '';
        $src = $this->gdCreateFromPath($path, $mime);
        if (!$src) {
            Log::warning('GD not available or unable to open image; skipping aspect fix', ['path' => $path]);
            return $path;
        }

        $dst = imagecreatetruecolor($srcW, $srcH);
        // White background for formats with alpha
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);
        imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, $srcW, $srcH, $srcW, $srcH);

        // Save to public tmp and compress under maxBytes if possible
        $relDir = 'tmp/instagram/processed';
        $targetRel = $relDir . '/' . uniqid('ig_', true) . '.jpg';
        $targetFull = storage_path('app/public/' . $targetRel);
        if (!is_dir(dirname($targetFull))) {
            @mkdir(dirname($targetFull), 0775, true);
        }

        $quality = 90;
        $this->gdSaveJpeg($dst, $targetFull, $quality);
        @imagedestroy($dst);
        @imagedestroy($src);

        // If too large, try step-down qualities
        for ($q = 80; $q >= 50 && (@filesize($targetFull) ?: 0) > $maxBytes; $q -= 10) {
            $this->gdSaveJpeg(null, $targetFull, $q, $reopenFrom = $targetFull);
        }

        return $targetFull;
    }

    protected function gdCreateFromPath(string $path, string $mime)
    {
        if (!function_exists('imagecreatefromjpeg')) {
            return null;
        }
        try {
            switch (strtolower($mime)) {
                case 'image/jpeg':
                case 'image/jpg':
                    return @imagecreatefromjpeg($path);
                case 'image/png':
                    $img = @imagecreatefrompng($path);
                    if ($img) { @imagesavealpha($img, true); }
                    return $img;
                case 'image/gif':
                    return @imagecreatefromgif($path);
                case 'image/webp':
                    return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null;
                default:
                    return null;
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to load image via GD', ['path' => $path, 'error' => $e->getMessage()]);
            return null;
        }
    }

    protected function gdSaveJpeg($gd, string $target, int $quality = 90, ?string $reopenFrom = null): void
    {
        if ($reopenFrom !== null) {
            // Reopen existing file and recompress
            $mime = $this->guessMime($reopenFrom) ?? 'image/jpeg';
            $gd = $this->gdCreateFromPath($reopenFrom, $mime);
            if (!$gd) return;
        }
        @imagejpeg($gd, $target, $quality);
    }
}
