<?php

namespace App\Jobs;

use App\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProcessMediaUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $post;
    protected $tempFiles;

    /**
     * Create a new job instance.
     */
    public function __construct(Post $post, array $tempFiles = [])
    {
        $this->post = $post;
        $this->tempFiles = $tempFiles;
        $this->onQueue('social-media');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Processing media upload for post', ['post_id' => $this->post->id]);

            $processedPhotos = [];
            $processedVideos = [];

            // Process photos
            if (!empty($this->post->photos)) {
                $processedPhotos = $this->processFiles($this->post->photos, 'photos');
            }

            // Process videos  
            if (!empty($this->post->videos)) {
                $processedVideos = $this->processFiles($this->post->videos, 'videos');
            }

            // Update post with processed file URLs
            $this->post->update([
                'photos' => $processedPhotos,
                'videos' => $processedVideos,
                'status' => 'ready_to_publish'
            ]);

            Log::info('Media processing completed', [
                'post_id' => $this->post->id,
                'photos_count' => count($processedPhotos),
                'videos_count' => count($processedVideos)
            ]);

            // If post was set to publish immediately, trigger publishing
            if ($this->post->status === 'ready_to_publish' && !$this->post->schedule_time) {
                PublishPostJob::dispatch($this->post, $this->post->social_medias ?? []);
            }

        } catch (\Exception $e) {
            Log::error('Media processing failed', [
                'post_id' => $this->post->id,
                'error' => $e->getMessage()
            ]);

            $this->post->update([
                'status' => 'failed',
                'publication_status' => ['error' => 'Media processing failed: ' . $e->getMessage()]
            ]);

            throw $e;
        }
    }

    /**
     * Process files and upload to configured disk
     */
    private function processFiles(array $files, string $type): array
    {
        $processedFiles = [];
        $disk = config('filesystems.default', 'public'); // Use configured default disk

        foreach ($files as $file) {
            try {
                // Skip if already a full URL (already processed)
                if (str_starts_with($file, 'http')) {
                    $processedFiles[] = $file;
                    continue;
                }

                // For public disk, files are already in the right place
                if ($disk === 'public') {
                    $publicUrl = asset('storage/' . $file);
                    $processedFiles[] = $publicUrl;
                    continue;
                }

                // For S3 and other cloud disks, process the upload
                if ($disk === 's3') {
                    // Get file from temporary storage
                    $tempPath = 'livewire-tmp/' . $file;
                    if (!Storage::disk('local')->exists($tempPath)) {
                        Log::warning('Temporary file not found', ['file' => $file]);
                        continue;
                    }

                    // Read file content
                    $fileContent = Storage::disk('local')->get($tempPath);
                    $fileName = basename($file);
                    
                    // Create S3 path
                    $s3Path = "posts/{$type}/" . now()->format('Y/m/d') . '/' . $fileName;

                    // Upload to S3
                    Storage::disk('s3')->put($s3Path, $fileContent, 'public');

                    // Generate public URL
                    $bucket = config('filesystems.disks.s3.bucket');
                    $region = config('filesystems.disks.s3.region');
                    $publicUrl = "https://{$bucket}.s3.{$region}.amazonaws.com/{$s3Path}";
                    $processedFiles[] = $publicUrl;

                    // Clean up temporary file
                    Storage::disk('local')->delete($tempPath);

                    Log::info('File uploaded to S3', [
                        'original' => $file,
                        's3_path' => $s3Path,
                        'public_url' => $publicUrl
                    ]);
                }

            } catch (\Exception $e) {
                Log::error('Failed to process file', [
                    'file' => $file,
                    'error' => $e->getMessage()
                ]);
                
                // Continue with other files even if one fails
                continue;
            }
        }

        return $processedFiles;
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Media processing job failed', [
            'post_id' => $this->post->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        $this->post->update([
            'status' => 'failed',
            'publication_status' => ['error' => 'Media processing failed: ' . $exception->getMessage()]
        ]);
    }
}