<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            @php
                $stats = $this->getConnectionStats();
            @endphp
            
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 dark:bg-blue-900">
                        <x-heroicon-o-link class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Connections</p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $stats['total'] }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 dark:bg-green-900">
                        <x-heroicon-o-check-circle class="h-6 w-6 text-green-600 dark:text-green-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Active</p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $stats['active'] }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 dark:bg-yellow-900">
                        <x-heroicon-o-exclamation-triangle class="h-6 w-6 text-yellow-600 dark:text-yellow-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Expiring Soon</p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $stats['expiring_soon'] }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-red-100 dark:bg-red-900">
                        <x-heroicon-o-x-circle class="h-6 w-6 text-red-600 dark:text-red-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Expired</p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $stats['expired'] }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modern Dashboard CTA -->
        <div class="bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold mb-2">🚀 Experience the Modern Connection Dashboard</h3>
                    <p class="text-blue-100 mb-4">
                        Connect and manage your social media accounts with our beautiful, modern interface featuring real-time status updates, connection testing, and streamlined OAuth flows.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <span class="px-3 py-1 bg-white/20 rounded-full text-sm">✨ Modern UI</span>
                        <span class="px-3 py-1 bg-white/20 rounded-full text-sm">🔒 Secure OAuth</span>
                        <span class="px-3 py-1 bg-white/20 rounded-full text-sm">⚡ Real-time Testing</span>
                        <span class="px-3 py-1 bg-white/20 rounded-full text-sm">📱 Mobile Friendly</span>
                    </div>
                </div>
                <div class="ml-6">
                    <a href="{{ route('social.connections') }}" 
                       target="_blank"
                       class="inline-flex items-center px-6 py-3 bg-white text-blue-600 font-semibold rounded-lg hover:bg-gray-50 transition duration-200">
                        <x-heroicon-m-sparkles class="h-5 w-5 mr-2" />
                        Open Modern Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- Current Connections Table -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Current Connections</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">Manage your connected social media accounts</p>
            </div>
            
            @php
                $connections = $this->getConnections();
            @endphp
            
            @if($connections->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Platform</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Username</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Connected</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Expires</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($connections as $connection)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-8 w-8">
                                                @switch($connection->platform_name)
                                                    @case('facebook')
                                                        <div class="h-8 w-8 bg-blue-600 rounded-full flex items-center justify-center">
                                                            <span class="text-white text-xs font-bold">f</span>
                                                        </div>
                                                        @break
                                                    @case('instagram')
                                                        <div class="h-8 w-8 bg-gradient-to-r from-purple-500 to-pink-500 rounded-full flex items-center justify-center">
                                                            <span class="text-white text-xs font-bold">📷</span>
                                                        </div>
                                                        @break
                                                    @case('youtube')
                                                        <div class="h-8 w-8 bg-red-600 rounded-full flex items-center justify-center">
                                                            <span class="text-white text-xs font-bold">▶</span>
                                                        </div>
                                                        @break
                                                    @case('telegram')
                                                        <div class="h-8 w-8 bg-blue-500 rounded-full flex items-center justify-center">
                                                            <span class="text-white text-xs font-bold">✈</span>
                                                        </div>
                                                        @break
                                                    @default
                                                        <div class="h-8 w-8 bg-gray-500 rounded-full flex items-center justify-center">
                                                            <span class="text-white text-xs font-bold">?</span>
                                                        </div>
                                                @endswitch
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $connection->getPlatformLabel() }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        {{ $connection->account_username ?: 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                            @if($connection->getStatusColor() === 'success') bg-green-100 text-green-800
                                            @elseif($connection->getStatusColor() === 'warning') bg-yellow-100 text-yellow-800
                                            @elseif($connection->getStatusColor() === 'danger') bg-red-100 text-red-800
                                            @else bg-gray-100 text-gray-800
                                            @endif">
                                            {{ $connection->getStatusLabel() }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $connection->created_at->diffForHumans() }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $connection->expires_at ? $connection->expires_at->format('M j, Y') : 'Never' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <a href="{{ route('social.test', $connection->platform_name) }}" 
                                               class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                                Test
                                            </a>
                                            <a href="{{ route('social.disconnect', $connection->platform_name) }}" 
                                               onclick="return confirm('Are you sure you want to disconnect this account?')"
                                               class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                                Disconnect
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="px-6 py-12 text-center">
                    <x-heroicon-o-link class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No connections</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by connecting your first social media account.</p>
                    <div class="mt-6">
                        <a href="{{ route('social.connections') }}" 
                           target="_blank"
                           class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <x-heroicon-m-plus class="h-4 w-4 mr-2" />
                            Connect Account
                        </a>
                    </div>
                </div>
            @endif
        </div>

        <!-- Help Section -->
        <div class="bg-blue-50 dark:bg-blue-900/50 rounded-lg p-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <x-heroicon-o-information-circle class="h-5 w-5 text-blue-400" />
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">Getting Started</h3>
                    <div class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                        <p>To connect your social media accounts:</p>
                        <ol class="mt-2 list-decimal list-inside space-y-1">
                            <li>Click "Open Modern Dashboard" above</li>
                            <li>Choose the platform you want to connect</li>
                            <li>Follow the OAuth authentication flow</li>
                            <li>Your account will be securely connected and ready for posting</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>