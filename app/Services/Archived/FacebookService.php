<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\Post;
use Illuminate\Support\Facades\Log;

class FacebookService
{
    protected string $graphUrl = 'https://graph.facebook.com/v23.0';

    public function publish(Post $post): void
    {
        $pageId = config('services.facebook.page_id');
        $token  = config('services.facebook.access_token');

        $message = trim(($post->title ?? '') . "\n\n" . strip_tags($post->body ?? ''));

        try {
            // Agar rasm yo'q bo'lsa - faqat matn yuborish
            if (empty($post->photos)) {
                $response = Http::post("{$this->graphUrl}/{$pageId}/feed", [
                    'message' => $message,
                    'access_token' => $token,
                ]);

                if (!$response->successful()) {
                    throw new \Exception('Facebook API xatolik: ' . $response->body());
                }
                return;
            }

            // Rasmlarni array ga aylantirish (agar JSON string bo'lsa)
            $photos = is_array($post->photos) ? $post->photos : json_decode($post->photos, true);

            if (empty($photos)) {
                // Agar rasmlar bo'sh bo'lsa - faqat matn yuborish
                $response = Http::post("{$this->graphUrl}/{$pageId}/feed", [
                    'message' => $message,
                    'access_token' => $token,
                ]);

                if (!$response->successful()) {
                    throw new \Exception('Facebook API xatolik: ' . $response->body());
                }
                return;
            }

            Log::info('Rasmlar topildi', ['count' => count($photos), 'photos' => $photos]);

            // Bitta rasm bo'lsa
            if (count($photos) === 1) {
                $this->uploadSinglePhoto($pageId, $token, $photos[0], $message);
                return;
            }

            // Ko'p rasmlar bo'lsa - album yaratish
            $mediaIds = [];

            foreach ($photos as $photo) {
                $mediaId = $this->uploadPhotoForAlbum($pageId, $token, $photo);
                $mediaIds[] = ['media_fbid' => $mediaId];
            }

            // Album post yaratish
            $response = Http::post("{$this->graphUrl}/{$pageId}/feed", [
                'message' => $message,
                'attached_media' => json_encode($mediaIds),
                'access_token' => $token,
            ]);

            if (!$response->successful()) {
                throw new \Exception('Album yaratishda xatolik: ' . $response->body());
            }

        } catch (\Throwable $e) {
            Log::error('Facebook post yuborishda xatolik: ' . $e->getMessage(), [
                'post_id' => $post->id,
                'error_details' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function uploadSinglePhoto(string $pageId, string $token, string $photo, string $message): void
    {
        $path = $this->findPhotoPath($photo);

        if (!file_exists($path)) {
            throw new \Exception("Rasm fayl topilmadi: {$path}");
        }

        if (!$this->isValidImage($path)) {
            throw new \Exception("Noto'g'ri rasm fayl: {$path}");
        }

        Log::info('Bitta rasm yuklanmoqda', ['path' => $path, 'size' => filesize($path)]);

        $response = Http::attach('source', file_get_contents($path), basename($path))
            ->post("{$this->graphUrl}/{$pageId}/photos", [
                'caption' => $message,
                'access_token' => $token,
            ]);

        if (!$response->successful()) {
            throw new \Exception('Facebook API xatolik: ' . $response->body());
        }

        Log::info('Rasm muvaffaqiyatli yuklandi', ['photo' => $photo]);
    }

    private function uploadPhotoForAlbum(string $pageId, string $token, string $photo): string
    {
        $path = $this->findPhotoPath($photo);

        if (!file_exists($path)) {
            throw new \Exception("Rasm fayl topilmadi: {$path}");
        }

        if (!$this->isValidImage($path)) {
            throw new \Exception("Noto'g'ri rasm fayl: {$path}");
        }

        Log::info('Album uchun rasm yuklanmoqda', ['path' => $path, 'size' => filesize($path)]);

        $response = Http::attach('source', file_get_contents($path), basename($path))
            ->post("{$this->graphUrl}/{$pageId}/photos", [
                'published' => false, // Hali nashr qilmaslik
                'access_token' => $token,
            ]);

        if (!$response->successful()) {
            throw new \Exception('Rasmni yuklashda xatolik: ' . $response->body());
        }

        $data = $response->json();
        Log::info('Album uchun rasm yuklandi', ['photo' => $photo, 'media_id' => $data['id']]);

        return $data['id'];
    }

    private function findPhotoPath(string $photo): string
    {
        // Fayl yo'lini tozalash
        $photo = ltrim($photo, '/');

        // 'storage/' bilan boshlansa, uni olib tashlash
        if (str_starts_with($photo, 'storage/')) {
            $photo = substr($photo, 8);
        }

        // Mumkin bo'lgan yo'llar ro'yxati
        $possiblePaths = [
            // Haqiqiy joylashuv (sening tizimingda)
            storage_path('app/private/' . $photo),

            // Boshqa mumkin bo'lgan joylar
            storage_path('app/public/' . $photo),
            public_path('storage/' . $photo),
            storage_path('app/' . $photo),

            // To'liq yo'l sifatida sinab ko'rish
            $photo,
        ];

        Log::info('Rasm qidirilmoqda', [
            'original' => $photo,
            'searching_in' => $possiblePaths
        ]);

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                Log::info('Rasm topildi!', ['path' => $path]);
                return $path;
            }
        }

        Log::error('Rasm hech qayerda topilmadi!', ['searched_paths' => $possiblePaths]);

        // Eng ehtimoliy yo'lni qaytarish
        return storage_path('app/private/' . $photo);
    }

    private function isValidImage(string $path): bool
    {
        // Rasm ekanligini tekshirish
        $imageInfo = getimagesize($path);
        if ($imageInfo === false) {
            Log::warning('Fayl rasm emas', ['path' => $path]);
            return false;
        }

        // Fayl hajmini tekshirish (4MB limit)
        $fileSize = filesize($path);
        if ($fileSize > 4 * 1024 * 1024) {
            Log::warning('Rasm juda katta', ['path' => $path, 'size' => $fileSize]);
            return false;
        }

        // Qo'llab-quvvatlanadigan formatlarni tekshirish
        $mimeType = $imageInfo['mime'];
        $supportedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        if (!in_array($mimeType, $supportedTypes)) {
            Log::warning('Qo\'llab-quvvatlanmaydigan format', ['path' => $path, 'mime' => $mimeType]);
            return false;
        }

        return true;
    }

    /**
     * Test Facebook API connection and page access
     */
    public function testPostPhoto(): array
    {
        try {
            $pageId = config('services.facebook.page_id');
            $token = config('services.facebook.page_access_token') ?? config('services.facebook.access_token');

            if (!$pageId || !$token) {
                return [
                    'success' => false,
                    'message' => 'Facebook credentials not configured (missing page_id or access_token)',
                    'configured' => false
                ];
            }

            // Test API access by getting page information
            $response = Http::get("{$this->graphUrl}/{$pageId}", [
                'fields' => 'id,name,access_token',
                'access_token' => $token,
            ]);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => 'Facebook API error: ' . $response->body(),
                    'configured' => true,
                    'api_response' => $response->json()
                ];
            }

            $data = $response->json();

            return [
                'success' => true,
                'message' => 'Facebook API connection successful',
                'configured' => true,
                'page_info' => [
                    'id' => $data['id'] ?? null,
                    'name' => $data['name'] ?? null,
                ],
                'api_version' => 'v23.0'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Facebook test failed: ' . $e->getMessage(),
                'configured' => true,
                'error' => $e->getMessage()
            ];
        }
    }
}
