<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Post;
use App\Models\PostPublication;
use App\Services\SocialMediaPublisher;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Public webhook endpoint (no authentication required)
Route::post('/webhook/social-post', function (Request $request) {
    try {
        Log::info('Social Media Webhook received:', [
            'headers' => $request->headers->all(),
            'payload' => $request->all(),
            'ip' => $request->ip(),
            'timestamp' => now()->toISOString()
        ]);
        
        // You can add webhook verification logic here
        // For example, verify signature from Facebook, Instagram, etc.
        
        return response()->json([
            'status' => 'success',
            'message' => 'Webhook received and logged successfully',
            'timestamp' => now()->toISOString()
        ]);
    } catch (\Exception $e) {
        Log::error('Webhook processing error:', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'status' => 'error',
            'message' => 'Webhook processing failed'
        ], 500);
    }
});

// Authentication required routes
Route::middleware(['api'])->group(function () {
    
    // Posts management
    Route::prefix('posts')->group(function () {
        
        // Get all posts with pagination
        Route::get('/', function (Request $request) {
            try {
                $query = Post::with(['publications', 'user']);
                
                // Filter by status
                if ($request->has('status')) {
                    $query->where('status', $request->status);
                }
                
                // Filter by platform
                if ($request->has('platform')) {
                    $query->whereJsonContains('platforms', $request->platform);
                }
                
                // Filter by date range
                if ($request->has('from_date')) {
                    $query->where('created_at', '>=', $request->from_date);
                }
                
                if ($request->has('to_date')) {
                    $query->where('created_at', '<=', $request->to_date);
                }
                
                $posts = $query->latest()
                    ->paginate($request->get('per_page', 15));
                
                return response()->json([
                    'status' => 'success',
                    'data' => $posts
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to retrieve posts: ' . $e->getMessage()
                ], 500);
            }
        });
        
        // Get specific post with publications
        Route::get('/{id}', function ($id) {
            try {
                $post = Post::with(['publications', 'user'])->findOrFail($id);
                
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'id' => $post->id,
                        'title' => $post->title,
                        'body' => $post->body,
                        'platforms' => $post->platforms,
                        'status' => $post->status,
                        'progress' => $post->publication_progress,
                        'scheduled_at' => $post->scheduled_at,
                        'published_at' => $post->published_at,
                        'created_at' => $post->created_at,
                        'updated_at' => $post->updated_at,
                        'user' => [
                            'id' => $post->user->id,
                            'name' => $post->user->name,
                            'email' => $post->user->email,
                        ],
                        'publications' => $post->publications->map(function ($pub) {
                            return [
                                'id' => $pub->id,
                                'platform' => $pub->platform,
                                'status' => $pub->status,
                                'published_at' => $pub->published_at,
                                'external_id' => $pub->external_id,
                                'external_url' => $pub->external_url,
                                'error_message' => $pub->error_message,
                                'retry_count' => $pub->retry_count,
                                'created_at' => $pub->created_at,
                                'updated_at' => $pub->updated_at,
                            ];
                        })
                    ]
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Post not found or error occurred: ' . $e->getMessage()
                ], 404);
            }
        });
        
        // Get post status summary
        Route::get('/{id}/status', function ($id) {
            try {
                $post = Post::with('publications')->findOrFail($id);
                
                $summary = [
                    'id' => $post->id,
                    'title' => $post->title,
                    'status' => $post->status,
                    'progress' => $post->publication_progress,
                    'scheduled_at' => $post->scheduled_at,
                    'published_at' => $post->published_at,
                    'platforms' => [
                        'selected' => $post->platforms,
                        'published' => $post->publications->where('status', 'published')->pluck('platform')->toArray(),
                        'failed' => $post->publications->where('status', 'failed')->pluck('platform')->toArray(),
                        'publishing' => $post->publications->where('status', 'publishing')->pluck('platform')->toArray(),
                    ],
                    'publications_summary' => [
                        'total' => $post->publications->count(),
                        'published' => $post->publications->where('status', 'published')->count(),
                        'failed' => $post->publications->where('status', 'failed')->count(),
                        'publishing' => $post->publications->where('status', 'publishing')->count(),
                        'pending' => $post->publications->where('status', 'pending')->count(),
                    ]
                ];
                
                return response()->json([
                    'status' => 'success',
                    'data' => $summary
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Post not found: ' . $e->getMessage()
                ], 404);
            }
        });
        
        // Retry failed publications
        Route::post('/{id}/retry', function ($id, SocialMediaPublisher $publisher) {
            try {
                $post = Post::with('publications')->findOrFail($id);
                $failedPublications = $post->publications()->where('status', 'failed')->get();
                
                if ($failedPublications->isEmpty()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'No failed publications to retry'
                    ], 400);
                }
                
                $retryResults = [];
                foreach ($failedPublications as $publication) {
                    $retryResults[] = [
                        'platform' => $publication->platform,
                        'retry_initiated' => $publication->retry()
                    ];
                }
                
                return response()->json([
                    'status' => 'success',
                    'message' => 'Retry initiated for failed publications',
                    'retried_count' => count($retryResults),
                    'retry_details' => $retryResults
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Retry failed: ' . $e->getMessage()
                ], 500);
            }
        });
        
        // Republish to specific platforms
        Route::post('/{id}/republish', function (Request $request, $id, SocialMediaPublisher $publisher) {
            try {
                $post = Post::findOrFail($id);
                $platforms = $request->input('platforms', []);
                
                if (empty($platforms)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Please specify platforms to republish to'
                    ], 400);
                }
                
                // Validate platforms
                $validPlatforms = ['facebook', 'instagram', 'telegram'];
                $invalidPlatforms = array_diff($platforms, $validPlatforms);
                
                if (!empty($invalidPlatforms)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Invalid platforms: ' . implode(', ', $invalidPlatforms)
                    ], 400);
                }
                
                // Create new publication records for specified platforms
                foreach ($platforms as $platform) {
                    PostPublication::create([
                        'post_id' => $post->id,
                        'platform' => $platform,
                        'status' => 'pending',
                    ]);
                }
                
                // Trigger republishing
                $publisher->publishPost($post, $platforms);
                
                return response()->json([
                    'status' => 'success',
                    'message' => 'Republishing initiated',
                    'platforms' => $platforms
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Republish failed: ' . $e->getMessage()
                ], 500);
                
            }
        });
    });
    
    // Publications management
    Route::prefix('publications')->group(function () {
        
        // Get all publications
        Route::get('/', function (Request $request) {
            try {
                $query = PostPublication::with(['post']);
                
                // Filter by platform
                if ($request->has('platform')) {
                    $query->where('platform', $request->platform);
                }
                
                // Filter by status
                if ($request->has('status')) {
                    $query->where('status', $request->status);
                }
                
                // Filter by date range
                if ($request->has('from_date')) {
                    $query->where('created_at', '>=', $request->from_date);
                }
                
                if ($request->has('to_date')) {
                    $query->where('created_at', '<=', $request->to_date);
                }
                
                $publications = $query->latest()
                    ->paginate($request->get('per_page', 20));
                
                return response()->json([
                    'status' => 'success',
                    'data' => $publications
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to retrieve publications: ' . $e->getMessage()
                ], 500);
            }
        });
        
        // Get publication statistics
        Route::get('/stats', function (Request $request) {
            try {
                $stats = [
                    'total_publications' => PostPublication::count(),
                    'by_platform' => [
                        'facebook' => PostPublication::where('platform', 'facebook')->count(),
                        'instagram' => PostPublication::where('platform', 'instagram')->count(),
                        'telegram' => PostPublication::where('platform', 'telegram')->count(),
                    ],
                    'by_status' => [
                        'published' => PostPublication::where('status', 'published')->count(),
                        'failed' => PostPublication::where('status', 'failed')->count(),
                        'publishing' => PostPublication::where('status', 'publishing')->count(),
                        'pending' => PostPublication::where('status', 'pending')->count(),
                    ],
                    'success_rate' => [
                        'facebook' => PostPublication::where('platform', 'facebook')->whereIn('status', ['published', 'failed'])->count() > 0 
                            ? round((PostPublication::where('platform', 'facebook')->where('status', 'published')->count() / PostPublication::where('platform', 'facebook')->whereIn('status', ['published', 'failed'])->count()) * 100, 2) 
                            : 0,
                        'instagram' => PostPublication::where('platform', 'instagram')->whereIn('status', ['published', 'failed'])->count() > 0 
                            ? round((PostPublication::where('platform', 'instagram')->where('status', 'published')->count() / PostPublication::where('platform', 'instagram')->whereIn('status', ['published', 'failed'])->count()) * 100, 2) 
                            : 0,
                        'telegram' => PostPublication::where('platform', 'telegram')->whereIn('status', ['published', 'failed'])->count() > 0 
                            ? round((PostPublication::where('platform', 'telegram')->where('status', 'published')->count() / PostPublication::where('platform', 'telegram')->whereIn('status', ['published', 'failed'])->count()) * 100, 2) 
                            : 0,
                    ],
                    'recent_activity' => PostPublication::latest()->take(10)->get(['platform', 'status', 'created_at']),
                ];
                
                return response()->json([
                    'status' => 'success',
                    'data' => $stats
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to retrieve statistics: ' . $e->getMessage()
                ], 500);
            }
        });
    });
    
    // System status and health check
    Route::get('/health', function () {
        try {
            $health = [
                'status' => 'healthy',
                'timestamp' => now()->toISOString(),
                'version' => config('app.version', '1.0.0'),
                'environment' => app()->environment(),
                'database' => [
                    'connection' => config('database.default'),
                    'status' => 'connected'
                ],
                'queue' => [
                    'default_connection' => config('queue.default'),
                    'pending_jobs' => DB::table('jobs')->count(),
                    'failed_jobs' => DB::table('failed_jobs')->count(),
                ],
                'services' => [
                    'facebook' => config('services.facebook.page_access_token') ? 'configured' : 'not_configured',
                    'instagram' => config('services.instagram.access_token') ? 'configured' : 'not_configured',
                    'telegram' => config('services.telegram.bot_token') ? 'configured' : 'not_configured',
                ]
            ];
            
            // Test database connection
            DB::connection()->getPdo();
            
            return response()->json($health);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 500);
        }
    });
});