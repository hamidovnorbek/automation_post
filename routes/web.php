<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use App\Models\Post;
use App\Jobs\SendToN8nJob;
use Illuminate\Http\Request;

Route::get('/', function () {
    return view('welcome');
});

// Test routes for n8n integration
Route::prefix('test')->group(function () {
    
    Route::get('/n8n', function () {
        try {
            // Test n8n webhook connectivity
            $response = Http::timeout(10)->post(config('services.n8n.webhook_url'), [
                'action' => 'test_connection',
                'timestamp' => now()->toISOString(),
                'test_data' => [
                    'message' => 'Test connection from Laravel',
                    'source' => 'automation_post_system'
                ]
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'n8n connection test completed',
                'n8n_webhook_url' => config('services.n8n.webhook_url'),
                'response_status' => $response->status(),
                'response_body' => $response->json()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'n8n connection test failed: ' . $e->getMessage(),
                'n8n_webhook_url' => config('services.n8n.webhook_url')
            ], 500);
        }
    });
    
    Route::get('/send-sample-post', function () {
        try {
            // Create and save a sample post for testing
            $post = Post::create([
                'title' => 'Test Post - ' . now()->format('Y-m-d H:i:s'),
                'body' => [
                    'content' => 'This is a test post for n8n integration testing. 🚀'
                ],
                'social_medias' => ['facebook', 'instagram', 'telegram'],
                'status' => 'ready_to_publish',
            ]);
            
            // Dispatch to n8n
            SendToN8nJob::dispatch($post);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Sample post saved and sent to n8n queue',
                'post_data' => [
                    'id' => $post->id,
                    'title' => $post->title,
                    'body' => $post->body,
                    'social_medias' => $post->social_medias,
                    'status' => $post->status
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sample post sending failed: ' . $e->getMessage()
            ], 500);
        }
    });
});

// API routes for external integrations
Route::prefix('api')->group(function () {
    
    // Webhook endpoint for n8n or other external services
    Route::post('/webhook/n8n-response', function (Request $request) {
        try {
            Log::info('n8n response webhook received:', $request->all());
            
            // Here you could update post status based on n8n response
            $postId = $request->input('post_id');
            $status = $request->input('status'); // success, failed, etc.
            $results = $request->input('results', []);
            
            if ($postId && $status) {
                $post = Post::find($postId);
                if ($post) {
                    $post->update([
                        'status' => $status === 'success' ? 'published' : 'failed',
                        'publication_status' => [
                            'n8n_response' => $request->all(),
                            'updated_at' => now()->toISOString()
                        ]
                    ]);
                }
            }
            
            return response()->json([
                'status' => 'success',
                'message' => 'n8n response processed successfully',
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            Log::error('n8n response webhook error: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'n8n response processing failed'
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
                    'social_medias' => $post->social_medias,
                    'schedule_time' => $post->schedule_time,
                    'publication_status' => $post->publication_status,
                    'created_at' => $post->created_at,
                    'updated_at' => $post->updated_at,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Post not found or error occurred'
            ], 404);
        }
    });
    
    // Retry sending post to n8n
    Route::post('/posts/{id}/retry-n8n', function ($id) {
        try {
            $post = Post::findOrFail($id);
            
            if (!in_array($post->status, ['failed', 'sent_to_n8n'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Post is not in a retryable state'
                ], 400);
            }
            
            // Dispatch to n8n again
            SendToN8nJob::dispatch($post);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Post resent to n8n queue',
                'post_id' => $post->id
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
                $totalJobs = DB::table('jobs')->count();
                $failedJobs = DB::table('failed_jobs')->count();
                
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
                'n8n Webhook URL' => config('services.n8n.webhook_url'),
                'n8n API Key' => config('services.n8n.api_key') ? '✓ Set' : '✗ Missing',
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
                Artisan::call('cache:clear');
                Artisan::call('config:clear');
                Artisan::call('route:clear');
                Artisan::call('view:clear');
                
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
    });
}
