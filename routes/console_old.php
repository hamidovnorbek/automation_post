<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\Post;
use App\Models\PostPublication;
use App\Services\SocialMediaPublisher;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Enhanced console commands for social media automation

// Test all social media services
Artisan::command('social:test-all', function () {
    $this->info('Testing all social media services...');
    
    $services = ['facebook', 'instagram', 'telegram'];
    
    foreach ($services as $platform) {
        try {
            $this->info("Testing {$platform}...");
            
            // Test each service directly
            if ($platform === 'facebook') {
                $service = app(\App\Services\FacebookService::class);
            } elseif ($platform === 'instagram') {
                $service = app(\App\Services\InstagramService::class);
            } elseif ($platform === 'telegram') {
                $service = app(\App\Services\TelegramService::class);
            }
            
            $result = $service->testPostPhoto();
            
            if (isset($result['success']) && $result['success']) {
                $this->info("✓ {$platform} test successful");
                if (isset($result['message'])) {
                    $this->line("  " . $result['message']);
                }
            } else {
                $this->warn("⚠ {$platform} test completed with warnings");
                if (isset($result['message'])) {
                    $this->line("  Message: " . $result['message']);
                }
                if (isset($result['error'])) {
                    $this->line("  Error: " . $result['error']);
                }
            }
        } catch (\Exception $e) {
            $this->error("✗ {$platform} test failed: " . $e->getMessage());
        }
    }
    
    $this->info('Social media service testing completed.');
})->purpose('Test all configured social media services');

// Check system health
Artisan::command('social:health-check', function () {
    $this->info('Performing system health check...');
    
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
        'Facebook' => config('services.facebook.page_access_token'),
        'Instagram' => config('services.instagram.access_token'),
        'Telegram Bot' => config('services.telegram.bot_token'),
        'Telegram Channel' => config('services.telegram.channel_id'),
    ];
    
    foreach ($configs as $service => $token) {
        if ($token) {
            $this->info("✓ {$service}: Configured");
        } else {
            $this->warn("⚠ {$service}: Not configured");
        }
    }
    
    // Recent activity check
    $recentPosts = Post::where('created_at', '>=', now()->subDay())->count();
    $recentPublications = PostPublication::where('created_at', '>=', now()->subDay())->count();
    
    $this->info("📊 Activity (24h): {$recentPosts} posts created, {$recentPublications} publications attempted");
    
    $this->info('Health check completed.');
})->purpose('Check system health and configuration');

// Clean up old failed jobs
Artisan::command('social:cleanup', function () {
    $this->info('Cleaning up old data...');
    
    // Clean up failed jobs older than 7 days
    $oldFailedJobs = DB::table('failed_jobs')
        ->where('failed_at', '<', now()->subDays(7))
        ->count();
    
    if ($oldFailedJobs > 0) {
        DB::table('failed_jobs')
            ->where('failed_at', '<', now()->subDays(7))
            ->delete();
        $this->info("✓ Cleaned up {$oldFailedJobs} old failed jobs");
    } else {
        $this->info('✓ No old failed jobs to clean');
    }
    
    // Clean up old successful publications (keep metadata but remove large response data)
    $oldPublications = PostPublication::where('status', 'published')
        ->where('updated_at', '<', now()->subDays(30))
        ->whereNotNull('response_data')
        ->count();
    
    if ($oldPublications > 0) {
        PostPublication::where('status', 'published')
            ->where('updated_at', '<', now()->subDays(30))
            ->update(['response_data' => null]);
        $this->info("✓ Cleaned up response data from {$oldPublications} old publications");
    } else {
        $this->info('✓ No old publication data to clean');
    }
    
    $this->info('Cleanup completed.');
})->purpose('Clean up old failed jobs and publication data');

// Show publication statistics
Artisan::command('social:stats', function () {
    $this->info('Social Media Publication Statistics');
    $this->line('========================================');
    
    // Overall stats
    $totalPosts = Post::count();
    $totalPublications = PostPublication::count();
    
    $this->info("Total Posts: {$totalPosts}");
    $this->info("Total Publications: {$totalPublications}");
    $this->line('');
    
    // By platform
    $this->info('By Platform:');
    $platforms = ['facebook', 'instagram', 'telegram'];
    foreach ($platforms as $platform) {
        $count = PostPublication::where('platform', $platform)->count();
        $published = PostPublication::where('platform', $platform)->where('status', 'published')->count();
        $failed = PostPublication::where('platform', $platform)->where('status', 'failed')->count();
        $successRate = $count > 0 ? round(($published / $count) * 100, 1) : 0;
        
        $this->info("  {$platform}: {$count} total ({$published} published, {$failed} failed) - {$successRate}% success rate");
    }
    
    $this->line('');
    
    // Recent activity
    $this->info('Recent Activity (Last 24 hours):');
    $recentStats = PostPublication::where('created_at', '>=', now()->subDay())
        ->selectRaw('platform, status, COUNT(*) as count')
        ->groupBy('platform', 'status')
        ->get();
    
    if ($recentStats->count() > 0) {
        foreach ($recentStats as $stat) {
            $this->info("  {$stat->platform} - {$stat->status}: {$stat->count}");
        }
    } else {
        $this->info('  No recent activity');
    }
})->purpose('Show publication statistics and success rates');

// Retry all failed publications
Artisan::command('social:retry-failed', function () {
    $failedPublications = PostPublication::where('status', 'failed')
        ->where('retry_count', '<', 3)
        ->get();
    
    if ($failedPublications->count() === 0) {
        $this->info('No failed publications to retry.');
        return;
    }
    
    $this->info("Found {$failedPublications->count()} failed publications to retry.");
    
    if (!$this->confirm('Do you want to retry all failed publications?')) {
        $this->info('Operation cancelled.');
        return;
    }
    
    $retried = 0;
    foreach ($failedPublications as $publication) {
        if ($publication->retry()) {
            $retried++;
            $this->info("✓ Retrying {$publication->platform} publication for post #{$publication->post_id}");
        }
    }
    
    $this->info("Retry initiated for {$retried} publications.");
})->purpose('Retry all failed publications');

// Create test post
Artisan::command('social:create-test-post', function () {
    $title = $this->ask('Post title', 'Test Post - ' . now()->format('Y-m-d H:i:s'));
    $body = $this->ask('Post body', '<p>This is a test post created via console command.</p>');
    
    $availablePlatforms = ['facebook', 'instagram', 'telegram'];
    $selectedPlatforms = [];
    
    foreach ($availablePlatforms as $platform) {
        if ($this->confirm("Publish to {$platform}?", true)) {
            $selectedPlatforms[] = $platform;
        }
    }
    
    if (empty($selectedPlatforms)) {
        $this->error('No platforms selected. Aborting.');
        return;
    }
    
    try {
        $post = Post::create([
            'title' => $title,
            'body' => $body,
            'social_medias' => $selectedPlatforms, // Laravel will cast this to JSON automatically
            'status' => 'draft',
        ]);
        
        $this->info("Test post created with ID: {$post->id}");
        
        if ($this->confirm('Publish immediately?', false)) {
            $publisher = app(SocialMediaPublisher::class);
            $post->update(['status' => 'publishing']);
            
            $publisher->publishPost($post, $selectedPlatforms);
            $this->info('Publishing initiated. Check logs for results.');
        }
        
    } catch (\Exception $e) {
        $this->error('Failed to create test post: ' . $e->getMessage());
    }
})->purpose('Create a test post with interactive prompts');

// Schedule configuration
Schedule::command('social:publish-scheduled')->everyMinute()
    ->description('Publish scheduled social media posts');

Schedule::command('social:cleanup')->daily()
    ->description('Clean up old failed jobs and publication data');

Schedule::command('queue:retry all')->hourly()
    ->description('Retry failed queue jobs every hour');
