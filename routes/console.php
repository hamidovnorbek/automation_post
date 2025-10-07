<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\Post;
use App\Jobs\SendToN8nJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Enhanced console commands for n8n automation

// Test n8n connectivity
Artisan::command('n8n:test', function () {
    $this->info('Testing n8n webhook connectivity...');
    
    try {
        $webhookUrl = config('services.n8n.webhook_url');
        
        if (!$webhookUrl) {
            $this->error('✗ n8n webhook URL not configured');
            return;
        }
        
        $this->info("Testing connection to: {$webhookUrl}");
        
        $response = Http::timeout(10)->post($webhookUrl, [
            'action' => 'test_connection',
            'source' => 'laravel_console',
            'timestamp' => now()->toISOString(),
            'test_data' => [
                'message' => 'Test from Laravel console command',
            ]
        ]);
        
        if ($response->successful()) {
            $this->info('✓ n8n connection successful');
            $this->line("  Response status: {$response->status()}");
            if ($response->json()) {
                $this->line("  Response: " . json_encode($response->json(), JSON_PRETTY_PRINT));
            }
        } else {
            $this->warn("⚠ n8n responded with status {$response->status()}");
            $this->line("  Response: " . $response->body());
        }
        
    } catch (\Exception $e) {
        $this->error('✗ n8n connection failed: ' . $e->getMessage());
    }
})->purpose('Test n8n webhook connectivity');

// Send test post to n8n
Artisan::command('n8n:send-test-post', function () {
    $this->info('Creating and sending test post to n8n...');
    
    try {
        // Create test post
        $post = Post::create([
            'title' => 'Console Test Post - ' . now()->format('Y-m-d H:i:s'),
            'body' => [
                'content' => 'This is a test post sent from Laravel console command to test n8n integration. 🚀'
            ],
            'social_medias' => ['facebook', 'instagram', 'telegram'],
            'status' => 'draft',
        ]);
        
        $this->info("✓ Test post created with ID: {$post->id}");
        
        // Send to n8n
        SendToN8nJob::dispatch($post);
        
        $this->info('✓ Test post dispatched to n8n queue');
        $this->line("  Post title: {$post->title}");
        $this->line("  Social medias: " . implode(', ', $post->social_medias));
        
    } catch (\Exception $e) {
        $this->error('✗ Failed to send test post: ' . $e->getMessage());
    }
})->purpose('Create and send a test post to n8n');

// System health check
Artisan::command('system:health', function () {
    $this->info('Checking system health...');
    
    // Database check
    try {
        DB::connection()->getPdo();
        $this->info('✓ Database connection: OK');
    } catch (\Exception $e) {
        $this->error('✗ Database connection: FAILED - ' . $e->getMessage());
        return;
    }
    
    // Queue check
    $pendingJobs = DB::table('jobs')->count();
    $failedJobs = DB::table('failed_jobs')->count();
    $this->info("✓ Queue status: {$pendingJobs} pending, {$failedJobs} failed jobs");
    
    // Configuration check
    $configs = [
        'n8n Webhook URL' => config('services.n8n.webhook_url'),
        'n8n API Key' => config('services.n8n.api_key') ? 'Configured' : 'Not configured',
    ];
    
    foreach ($configs as $service => $value) {
        if ($value) {
            $this->info("✓ {$service}: {$value}");
        } else {
            $this->warn("⚠ {$service}: Not configured");
        }
    }
    
    // Recent activity check
    $recentPosts = Post::where('created_at', '>=', now()->subDay())->count();
    $this->info("✓ Recent posts (24h): {$recentPosts}");
    
    // n8n connectivity check
    try {
        $response = Http::timeout(5)->post(config('services.n8n.webhook_url'), [
            'action' => 'health_check',
            'timestamp' => now()->toISOString()
        ]);
        
        if ($response->successful()) {
            $this->info('✓ n8n connectivity: OK');
        } else {
            $this->warn("⚠ n8n connectivity: Response {$response->status()}");
        }
    } catch (\Exception $e) {
        $this->warn('⚠ n8n connectivity: Connection failed');
    }
    
})->purpose('Check overall system health');

// Process pending posts
Artisan::command('posts:process-pending', function () {
    $this->info('Processing pending posts...');
    
    $pendingPosts = Post::whereIn('status', ['ready_to_publish', 'failed'])
        ->whereNull('schedule_time')
        ->get();
    
    if ($pendingPosts->isEmpty()) {
        $this->info('No pending posts to process');
        return;
    }
    
    $this->info("Found {$pendingPosts->count()} pending posts");
    
    foreach ($pendingPosts as $post) {
        try {
            $this->line("Processing post {$post->id}: {$post->title}");
            
            SendToN8nJob::dispatch($post);
            
            $this->info("✓ Post {$post->id} dispatched to n8n");
            
        } catch (\Exception $e) {
            $this->error("✗ Failed to process post {$post->id}: " . $e->getMessage());
        }
    }
    
})->purpose('Process pending posts and send to n8n');

// Process scheduled posts
Artisan::command('posts:process-scheduled', function () {
    $this->info('Processing scheduled posts...');
    
    $scheduledPosts = Post::where('status', 'scheduled')
        ->where('schedule_time', '<=', now())
        ->get();
    
    if ($scheduledPosts->isEmpty()) {
        $this->info('No scheduled posts ready for processing');
        return;
    }
    
    $this->info("Found {$scheduledPosts->count()} scheduled posts ready to publish");
    
    foreach ($scheduledPosts as $post) {
        try {
            $this->line("Processing scheduled post {$post->id}: {$post->title}");
            
            $post->update(['status' => 'ready_to_publish']);
            SendToN8nJob::dispatch($post);
            
            $this->info("✓ Scheduled post {$post->id} dispatched to n8n");
            
        } catch (\Exception $e) {
            $this->error("✗ Failed to process scheduled post {$post->id}: " . $e->getMessage());
        }
    }
    
})->purpose('Process scheduled posts that are ready to publish');

// Queue status
Artisan::command('queue:status', function () {
    $this->info('Queue Status:');
    
    $totalJobs = DB::table('jobs')->count();
    $failedJobs = DB::table('failed_jobs')->count();
    
    $this->line("Total pending jobs: {$totalJobs}");
    $this->line("Failed jobs: {$failedJobs}");
    
    if ($totalJobs > 0) {
        $this->info('Recent jobs:');
        $recentJobs = DB::table('jobs')
            ->select('queue', 'payload')
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get();
            
        foreach ($recentJobs as $job) {
            $payload = json_decode($job->payload, true);
            $jobClass = $payload['displayName'] ?? 'Unknown';
            $this->line("  Queue: {$job->queue}, Job: {$jobClass}");
        }
    }
    
    if ($failedJobs > 0) {
        $this->warn('Recent failed jobs:');
        $recentFailedJobs = DB::table('failed_jobs')
            ->select('queue', 'payload', 'exception', 'failed_at')
            ->orderBy('failed_at', 'desc')
            ->limit(3)
            ->get();
            
        foreach ($recentFailedJobs as $job) {
            $payload = json_decode($job->payload, true);
            $jobClass = $payload['displayName'] ?? 'Unknown';
            $this->line("  Queue: {$job->queue}, Job: {$jobClass}, Failed: {$job->failed_at}");
        }
    }
    
})->purpose('Show queue status and recent jobs');

// Schedule the automated tasks
Schedule::command('posts:process-scheduled')->everyMinute();
Schedule::command('system:health')->hourly();