<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connect Telegram Bot - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="gradient-bg min-h-screen flex items-center justify-center">
    <div class="container mx-auto px-4 py-8 max-w-2xl">
        <div class="glass-effect rounded-2xl p-8">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="w-20 h-20 bg-blue-500 rounded-full flex items-center justify-center text-white text-3xl mx-auto mb-4">
                    <i class="fab fa-telegram-plane"></i>
                </div>
                <h1 class="text-3xl font-bold text-white mb-2">Connect Telegram Bot</h1>
                <p class="text-white/80">Set up your Telegram bot for automated posting to channels</p>
            </div>

            <!-- Error Messages -->
            @if($errors->any())
                <div class="bg-red-500/20 border border-red-500/50 rounded-lg p-4 mb-6">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-exclamation-circle text-red-400 mr-2"></i>
                        <h3 class="text-red-400 font-semibold">Error</h3>
                    </div>
                    @foreach($errors->all() as $error)
                        <p class="text-red-300 text-sm">{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <!-- Instructions -->
            <div class="bg-blue-500/20 border border-blue-500/50 rounded-lg p-6 mb-8">
                <h3 class="text-blue-300 font-semibold mb-3 flex items-center">
                    <i class="fas fa-info-circle mr-2"></i>Setup Instructions
                </h3>
                <ol class="text-blue-200 text-sm space-y-2 list-decimal list-inside">
                    <li>Message <a href="https://t.me/botfather" target="_blank" class="text-blue-300 underline">@BotFather</a> on Telegram</li>
                    <li>Send <code class="bg-blue-500/30 px-1 rounded">/newbot</code> to create a new bot</li>
                    <li>Follow the instructions to set a name and username</li>
                    <li>Copy the bot token from BotFather's message</li>
                    <li>Add your bot to the channel you want to post to</li>
                    <li>Make your bot an admin with posting permissions</li>
                    <li>Get your channel/chat ID (use <a href="https://t.me/userinfobot" target="_blank" class="text-blue-300 underline">@userinfobot</a>)</li>
                </ol>
            </div>

            <!-- Form -->
            <form method="POST" action="{{ route('social.telegram.connect') }}" class="space-y-6">
                @csrf
                
                <div>
                    <label for="bot_token" class="block text-white font-semibold mb-2">
                        <i class="fas fa-key mr-2"></i>Bot Token
                    </label>
                    <input type="text" 
                           id="bot_token" 
                           name="bot_token" 
                           value="{{ old('bot_token') }}"
                           placeholder="123456789:ABCdefGHIjklMNOpqrsTUVwxyz"
                           class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <p class="text-white/60 text-sm mt-2">The token you received from @BotFather</p>
                </div>

                <div>
                    <label for="chat_id" class="block text-white font-semibold mb-2">
                        <i class="fas fa-comments mr-2"></i>Chat/Channel ID
                    </label>
                    <input type="text" 
                           id="chat_id" 
                           name="chat_id" 
                           value="{{ old('chat_id') }}"
                           placeholder="@your_channel or -1001234567890"
                           class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <p class="text-white/60 text-sm mt-2">Channel username (@channel) or numeric chat ID</p>
                </div>

                <!-- Example Chat IDs -->
                <div class="bg-gray-500/20 border border-gray-500/50 rounded-lg p-4">
                    <h4 class="text-gray-300 font-semibold mb-2">
                        <i class="fas fa-lightbulb mr-2"></i>Chat ID Examples:
                    </h4>
                    <ul class="text-gray-200 text-sm space-y-1">
                        <li><strong>Public Channel:</strong> <code class="bg-gray-500/30 px-1 rounded">@mychannel</code></li>
                        <li><strong>Private Channel:</strong> <code class="bg-gray-500/30 px-1 rounded">-1001234567890</code></li>
                        <li><strong>Group Chat:</strong> <code class="bg-gray-500/30 px-1 rounded">-1234567890</code></li>
                        <li><strong>Direct Message:</strong> <code class="bg-gray-500/30 px-1 rounded">123456789</code></li>
                    </ul>
                </div>

                <!-- Action Buttons -->
                <div class="flex gap-4 pt-4">
                    <button type="submit" 
                            class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-200 flex items-center justify-center">
                        <i class="fas fa-link mr-2"></i>Connect Bot
                    </button>
                    
                    <a href="{{ route('social.connections') }}" 
                       class="flex-1 bg-gray-600 hover:bg-gray-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-200 text-center flex items-center justify-center">
                        <i class="fas fa-arrow-left mr-2"></i>Cancel
                    </a>
                </div>
            </form>

            <!-- Test Bot Section -->
            <div class="mt-8 pt-8 border-t border-white/20">
                <h3 class="text-white font-semibold mb-4 flex items-center">
                    <i class="fas fa-vial mr-2"></i>Test Your Bot
                </h3>
                <p class="text-white/70 text-sm mb-4">
                    Before connecting, you can test if your bot token works by sending a test message:
                </p>
                <div class="bg-white/5 rounded-lg p-4">
                    <code class="text-green-300 text-xs block break-all">
                        curl -X POST "https://api.telegram.org/bot[YOUR_BOT_TOKEN]/sendMessage" \<br>
                        &nbsp;&nbsp;-H "Content-Type: application/json" \<br>
                        &nbsp;&nbsp;-d '{"chat_id": "[YOUR_CHAT_ID]", "text": "Test message from my bot!"}'
                    </code>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-fill example when focusing on chat_id
        document.getElementById('chat_id').addEventListener('focus', function() {
            if (!this.value) {
                this.placeholder = '@your_channel_name';
            }
        });

        // Validate bot token format
        document.getElementById('bot_token').addEventListener('input', function() {
            const token = this.value;
            const isValid = /^\d+:[A-Za-z0-9_-]+$/.test(token);
            
            if (token && !isValid) {
                this.style.borderColor = '#ef4444';
            } else {
                this.style.borderColor = 'rgba(255, 255, 255, 0.2)';
            }
        });
    </script>
</body>
</html>