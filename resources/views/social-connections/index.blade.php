<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Social Media Connections - {{ config('app.name') }}</title>
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

        .connection-card {
            transition: all 0.3s ease;
            transform: translateY(0);
        }

        .connection-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .status-badge {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        .status-badge.connected {
            animation: none;
        }
    </style>
</head>
<body class="gradient-bg min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-white mb-4">
                <i class="fas fa-link mr-3"></i>Social Media Connections
            </h1>
            <p class="text-xl text-white/80 max-w-2xl mx-auto">
                Connect your social media accounts to start publishing content automatically across all platforms
            </p>
        </div>

        <!-- Success/Error Messages -->
        @if(session('success'))
            <div class="glass-effect rounded-lg p-4 mb-8 border-l-4 border-green-400">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-400 mr-3"></i>
                    <p class="text-white">{{ session('success') }}</p>
                </div>
            </div>
        @endif

        @if(session('error'))
            <div class="glass-effect rounded-lg p-4 mb-8 border-l-4 border-red-400">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-400 mr-3"></i>
                    <p class="text-white">{{ session('error') }}</p>
                </div>
            </div>
        @endif

        <!-- Connection Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
            <div class="glass-effect rounded-xl p-6 text-center">
                <div class="text-3xl font-bold text-white mb-2">
                    {{ $connections->where('is_active', true)->count() }}
                </div>
                <div class="text-white/70">Active Connections</div>
            </div>
            <div class="glass-effect rounded-xl p-6 text-center">
                <div class="text-3xl font-bold text-white mb-2">
                    {{ count($platforms) }}
                </div>
                <div class="text-white/70">Available Platforms</div>
            </div>
            <div class="glass-effect rounded-xl p-6 text-center">
                <div class="text-3xl font-bold text-white mb-2">
                    @php
                        $validTokens = 0;
                        foreach($connections as $conn) {
                            if($conn->expires_at === null || $conn->expires_at > now()) {
                                $validTokens++;
                            }
                        }
                    @endphp
                    {{ $validTokens }}
                </div>
                <div class="text-white/70">Valid Tokens</div>
            </div>
        </div>

        <!-- Platform Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 gap-8">
            @foreach($platforms as $key => $platform)
                <div class="connection-card glass-effect rounded-2xl p-8 relative overflow-hidden">
                    <!-- Platform Header -->
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center">
                            <div class="w-16 h-16 rounded-full {{ $platform['color'] }} flex items-center justify-center text-white text-2xl mr-4">
                                <i class="{{ $platform['icon'] }}"></i>
                            </div>
                            <div>
                                <h3 class="text-2xl font-bold text-white">{{ $platform['name'] }}</h3>
                                <p class="text-white/70">{{ $platform['description'] }}</p>
                            </div>
                        </div>
                        
                        <!-- Status Badge -->
                        <div class="status-badge {{ $platform['connected'] ? 'connected' : '' }}">
                            @if($platform['connected'])
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                    <span class="w-2 h-2 bg-green-400 rounded-full mr-2"></span>
                                    {{ $platform['status'] }}
                                </span>
                            @else
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800">
                                    <span class="w-2 h-2 bg-gray-400 rounded-full mr-2"></span>
                                    Not Connected
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Connection Details -->
                    @if($platform['connected'])
                        <div class="bg-white/10 rounded-lg p-4 mb-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="text-white/70">Username:</span>
                                    <span class="text-white ml-2">{{ $platform['connection']->account_username ?? 'N/A' }}</span>
                                </div>
                                <div>
                                    <span class="text-white/70">Connected:</span>
                                    <span class="text-white ml-2">{{ $platform['connection']->created_at->diffForHumans() }}</span>
                                </div>
                                @if($platform['connection']->expires_at)
                                    <div class="md:col-span-2">
                                        <span class="text-white/70">Token Expires:</span>
                                        <span class="text-white ml-2">{{ $platform['connection']->expires_at->format('M j, Y g:i A') }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- Scopes/Permissions -->
                    @if(!empty($platform['scopes']))
                        <div class="mb-6">
                            <h4 class="text-white font-semibold mb-2">Required Permissions:</h4>
                            <div class="flex flex-wrap gap-2">
                                @foreach($platform['scopes'] as $scope)
                                    <span class="px-2 py-1 bg-white/20 rounded text-white/80 text-xs">
                                        {{ str_replace('_', ' ', $scope) }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Action Buttons -->
                    <div class="flex gap-3">
                        @if($platform['connected'])
                            <!-- Test Connection -->
                            <a href="{{ route('social.test', $key) }}" 
                               class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition duration-200 text-center">
                                <i class="fas fa-plug mr-2"></i>Test Connection
                            </a>
                            
                            <!-- Disconnect -->
                            <a href="{{ route('social.disconnect', $key) }}" 
                               onclick="return confirm('Are you sure you want to disconnect this account?')"
                               class="flex-1 bg-red-600 hover:bg-red-700 text-white font-semibold py-3 px-4 rounded-lg transition duration-200 text-center">
                                <i class="fas fa-unlink mr-2"></i>Disconnect
                            </a>
                        @else
                            @if($key === 'telegram')
                                <!-- Telegram Connect -->
                                <a href="{{ route('social.telegram.form') }}" 
                                   class="w-full {{ $platform['color'] }} text-white font-semibold py-3 px-6 rounded-lg transition duration-200 text-center">
                                    <i class="fas fa-robot mr-2"></i>Connect Bot
                                </a>
                            @else
                                <!-- OAuth Connect -->
                                <a href="{{ route('social.redirect', $key) }}" 
                                   class="w-full {{ $platform['color'] }} text-white font-semibold py-3 px-6 rounded-lg transition duration-200 text-center">
                                    <i class="fas fa-link mr-2"></i>Connect {{ $platform['name'] }}
                                </a>
                            @endif
                        @endif
                    </div>

                    <!-- Decorative Elements -->
                    <div class="absolute top-0 right-0 w-32 h-32 {{ $platform['color'] }} opacity-10 rounded-full -mr-16 -mt-16"></div>
                    <div class="absolute bottom-0 left-0 w-20 h-20 {{ $platform['color'] }} opacity-10 rounded-full -ml-10 -mb-10"></div>
                </div>
            @endforeach
        </div>

        <!-- Help Section -->
        <div class="mt-16 glass-effect rounded-2xl p-8">
            <div class="text-center mb-8">
                <h2 class="text-3xl font-bold text-white mb-4">
                    <i class="fas fa-question-circle mr-3"></i>Need Help?
                </h2>
                <p class="text-white/80 max-w-3xl mx-auto">
                    Follow these steps to set up your social media connections for automated posting
                </p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="text-center">
                    <div class="w-16 h-16 bg-blue-600 rounded-full flex items-center justify-center text-white text-2xl mx-auto mb-4">
                        <i class="fas fa-cog"></i>
                    </div>
                    <h3 class="text-white font-semibold mb-2">1. Setup Apps</h3>
                    <p class="text-white/70 text-sm">Create developer apps on each platform</p>
                </div>
                
                <div class="text-center">
                    <div class="w-16 h-16 bg-green-600 rounded-full flex items-center justify-center text-white text-2xl mx-auto mb-4">
                        <i class="fas fa-key"></i>
                    </div>
                    <h3 class="text-white font-semibold mb-2">2. Get Credentials</h3>
                    <p class="text-white/70 text-sm">Obtain API keys and configure OAuth</p>
                </div>
                
                <div class="text-center">
                    <div class="w-16 h-16 bg-purple-600 rounded-full flex items-center justify-center text-white text-2xl mx-auto mb-4">
                        <i class="fas fa-link"></i>
                    </div>
                    <h3 class="text-white font-semibold mb-2">3. Connect Accounts</h3>
                    <p class="text-white/70 text-sm">Authorize your social media accounts</p>
                </div>
                
                <div class="text-center">
                    <div class="w-16 h-16 bg-red-600 rounded-full flex items-center justify-center text-white text-2xl mx-auto mb-4">
                        <i class="fas fa-rocket"></i>
                    </div>
                    <h3 class="text-white font-semibold mb-2">4. Start Publishing</h3>
                    <p class="text-white/70 text-sm">Create posts and publish automatically</p>
                </div>
            </div>
        </div>

        <!-- Back to Dashboard -->
        <div class="text-center mt-12">
            <a href="/admin" class="inline-flex items-center px-6 py-3 bg-white/20 hover:bg-white/30 text-white font-semibold rounded-lg transition duration-200">
                <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
            </a>
        </div>
    </div>

    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('[class*="border-green"], [class*="border-red"]');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>