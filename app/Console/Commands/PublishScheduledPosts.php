<?php

namespace App\Console\Commands;

use App\Jobs\PublishScheduledPostsJob;
use Illuminate\Console\Command;

class PublishScheduledPosts extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'social:publish-scheduled 
                            {--dry-run : Show what would be published without actually publishing}';

    /**
     * The console command description.
     */
    protected $description = 'Publish scheduled social media posts';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting scheduled posts publication...');
        
        if ($this->option('dry-run')) {
            $this->showScheduledPosts();
            return;
        }
        
        PublishScheduledPostsJob::dispatch();
        
        $this->info('✅ Scheduled posts publication job dispatched successfully!');
        $this->comment('Monitor the queue worker to see publication progress.');
    }
    
    private function showScheduledPosts()
    {
        $scheduledPosts = \App\Models\PostPublication::where('status', 'scheduled')
            ->where('scheduled_for', '<=', now())
            ->with('post')
            ->get();
            
        if ($scheduledPosts->isEmpty()) {
            $this->info('No posts scheduled for publication at this time.');
            return;
        }
        
        $this->info("Found {$scheduledPosts->count()} posts scheduled for publication:");
        
        $headers = ['Post ID', 'Title', 'Platform', 'Scheduled For'];
        $rows = [];
        
        foreach ($scheduledPosts as $publication) {
            $rows[] = [
                $publication->post->id,
                \Illuminate\Support\Str::limit($publication->post->title, 50),
                ucfirst($publication->platform),
                $publication->scheduled_for->format('Y-m-d H:i:s'),
            ];
        }
        
        $this->table($headers, $rows);
    }
}
