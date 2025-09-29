<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InstagramService
{
    protected string $graphUrl;

    public function __construct()
    {
        $this->graphUrl = 'https://graph.facebook.com/' . config('services.instagram.version', 'v23.0');
    }

    public function testPostPhoto(): array
    {
        $token = config('services.instagram.access_token');
        $userId = config('services.instagram.business_account_id');

        if (!$token || !$userId) {
            throw new \RuntimeException('Instagram credentials are not configured.');
        }

        // 👉 Use your working S3 public photo URL
        $imageUrl = "https://s3iucketforinsta.s3.ap-southeast-1.amazonaws.com/photos/edu1.jpg";

        // Step 1: Create media container
        $container = Http::asForm()->post("{$this->graphUrl}/{$userId}/media", [
            'image_url'    => $imageUrl,
            'caption'      => "Testing Instagram upload from Laravel 🚀",
            'access_token' => $token,
        ]);

        if (!$container->successful()) {
            throw new \RuntimeException('Failed to create Instagram image container: ' . $container->body());
        }

        $creationId = $container->json('id');

        // Step 2: Publish media
        $publish = Http::asForm()->post("{$this->graphUrl}/{$userId}/media_publish", [
            'creation_id'  => $creationId,
            'access_token' => $token,
        ]);

        if (!$publish->successful()) {
            throw new \RuntimeException('Failed to publish Instagram image: ' . $publish->body());
        }

        return [
            'container' => $container->json(),
            'publish'   => $publish->json(),
        ];
    }
}
