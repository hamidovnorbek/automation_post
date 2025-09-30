<?php

use Illuminate\Support\Facades\Route;
use App\Services\InstagramService;
use App\Services\FacebookService;
use App\Services\TelegramService;
use App\Services\SocialMediaPublisher;
use App\Models\Post;
use App\Models\PostPublication;
use Illuminate\Http\Request;

Route::get('/', function () {
    return view('welcome');
});

// Test routes for each social media service
Route::prefix('test')->group(function () {
    
    Route::get('/instagram', function (InstagramService $service) {
        return $service->testPostPhoto();
    });
    
    Route::get('/facebook', function (FacebookService $service) {
        try {
            $result = $service->testPostPhoto();
            return response()->json([
                'status' => 'success',
                'message' => 'Facebook test completed',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Facebook test failed: ' . $e->getMessage()
            ], 500);
        }
    });
    
    Route::get('/telegram', function (TelegramService $service) {
        try {
            $result = $service->testPostPhoto();
            return response()->json([
                'status' => 'success',
                'message' => 'Telegram test completed',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Telegram test failed: ' . $e->getMessage()
            ], 500);
        }
    });
    
    Route::get('/all-services', function (SocialMediaPublisher $publisher) {
        try {
            $results = [];
            
            // Test each service
            $services = ['facebook', 'instagram', 'telegram'];
            foreach ($services as $platform) {
                try {
                    $service = $publisher->getService($platform);
                    $results[$platform] = $service->testPostPhoto();
                } catch (\Exception $e) {
                    $results[$platform] = [
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            return response()->json([
                'status' => 'success',
                'message' => 'All services tested',
                'results' => $results
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Test failed: ' . $e->getMessage()
            ], 500);
        }
    });
});

// API routes for external integrations
Route::prefix('api')->group(function () {
    
    // Webhook endpoint for external services
    Route::post('/webhook/social-post', function (Request $request) {
        try {
            \Log::info('Webhook received:', $request->all());
            
            return response()->json([
                'status' => 'success',
                'message' => 'Webhook received successfully',
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            \Log::error('Webhook error: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Webhook processing failed'
            ], 500);
        }
    });
    
    // Get post status via API
    Route::get('/posts/{id}/status', function ($id) {
        try {
            $post = Post::findOrFail($id);
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'id' => $post->id,
                    'title' => $post->title,
                    'status' => $post->status,
                    'progress' => $post->publication_progress,
                    'scheduled_at' => $post->scheduled_at,
                    'published_at' => $post->published_at,
                    'publications' => $post->publications->map(function ($pub) {
                        return [
                            'platform' => $pub->platform,
                            'status' => $pub->status,
                            'published_at' => $pub->published_at,
                            'external_id' => $pub->external_id,
                            'error_message' => $pub->error_message,
                        ];
                    })
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Post not found or error occurred'
            ], 404);
        }
    });
    
    // Retry failed publications
    Route::post('/posts/{id}/retry', function ($id, SocialMediaPublisher $publisher) {
        try {
            $post = Post::findOrFail($id);
            $failedPublications = $post->publications()->where('status', 'failed')->get();
            
            if ($failedPublications->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No failed publications to retry'
                ], 400);
            }
            
            $results = [];
            foreach ($failedPublications as $publication) {
                $results[] = $publication->retry();
            }
            
            return response()->json([
                'status' => 'success',
                'message' => 'Retry initiated for failed publications',
                'retried_count' => count($results)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Retry failed: ' . $e->getMessage()
            ], 500);
        }
    });
});

// Debug routes (only in local environment)
if (app()->environment('local')) {
    Route::prefix('debug')->group(function () {
        
        Route::get('/queue-status', function () {
            try {
                $totalJobs = \DB::table('jobs')->count();
                $failedJobs = \DB::table('failed_jobs')->count();
                
                return response()->json([
                    'status' => 'success',
                    'queue_info' => [
                        'pending_jobs' => $totalJobs,
                        'failed_jobs' => $failedJobs,
                        'queue_connection' => config('queue.default'),
                    ]
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Queue status check failed: ' . $e->getMessage()
                ], 500);
            }
        });
        
        Route::get('/config-check', function () {
            $configs = [
                'Facebook Page Access Token' => config('services.facebook.page_access_token') ? '✓ Set' : '✗ Missing',
                'Instagram Access Token' => config('services.instagram.access_token') ? '✓ Set' : '✗ Missing',
                'Telegram Bot Token' => config('services.telegram.bot_token') ? '✓ Set' : '✗ Missing',
                'Telegram Channel ID' => config('services.telegram.channel_id') ? '✓ Set' : '✗ Missing',
                'Queue Connection' => config('queue.default'),
                'Database Connection' => config('database.default'),
                'App Environment' => app()->environment(),
                'App Debug' => config('app.debug') ? 'Enabled' : 'Disabled',
            ];
            
            return response()->json([
                'status' => 'success',
                'configuration' => $configs
            ]);
        });
        
        Route::get('/clear-cache', function () {
            try {
                \Artisan::call('cache:clear');
                \Artisan::call('config:clear');
                \Artisan::call('route:clear');
                \Artisan::call('view:clear');
                
                return response()->json([
                    'status' => 'success',
                    'message' => 'All caches cleared successfully'
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cache clearing failed: ' . $e->getMessage()
                ], 500);
            }
        });
        
        Route::get('/create-test-post', function (SocialMediaPublisher $publisher) {
            try {
                $post = Post::create([
                    'title' => 'Test Post - ' . now()->format('Y-m-d H:i:s'),
                    'body' => '<p>This is a test post created via debug route. It contains <strong>rich text formatting</strong> and will be published to all configured platforms.</p>',
                    'platforms' => ['facebook', 'instagram', 'telegram'],
                    'status' => 'draft',
                    'user_id' => 1, // Assumes user with ID 1 exists
                ]);
                
                // Immediately publish for testing
                $post->update(['status' => 'publishing']);
                $publisher->publishToAllPlatforms($post);
                
                return response()->json([
                    'status' => 'success',
                    'message' => 'Test post created and publishing initiated',
                    'post_id' => $post->id,
                    'view_url' => url("/api/posts/{$post->id}/status")
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Test post creation failed: ' . $e->getMessage()
                ], 500);
            }
        });
    });
}
