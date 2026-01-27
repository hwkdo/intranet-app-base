<?php

namespace Hwkdo\IntranetAppBase\Services;

use Hwkdo\IntranetAppBase\Interfaces\IntranetAppInterface;
use Illuminate\Support\Facades\Log;
use Prism\Relay\Enums\Transport;

class McpServerService
{
    /**
     * Get the app class for a given app identifier.
     */
    private function getAppClass(string $appIdentifier): ?string
    {
        $packagesFile = base_path('bootstrap/cache/packages.php');

        if (! file_exists($packagesFile)) {
            return null;
        }

        $packages = require $packagesFile;

        // Find package matching the app identifier
        foreach ($packages as $packageName => $packageData) {
            if (! str_starts_with($packageName, 'hwkdo/intranet-app-') ||
                str_starts_with($packageName, 'hwkdo/intranet-app-base')) {
                continue;
            }

            $packageIdentifier = str($packageName)->after('intranet-app-')->value;

            if ($packageIdentifier === $appIdentifier) {
                // Convert package name to class name
                // e.g., "hwkdo/intranet-app-hwro" -> "Hwkdo\IntranetAppHwro\IntranetAppHwro"
                $parts = explode('/', $packageName);
                $vendor = ucfirst($parts[0]);
                $packagePart = str_replace('-', '', ucwords($parts[1], '-'));

                return "$vendor\\$packagePart\\$packagePart";
            }
        }

        return null;
    }

    /**
     * Get MCP server names for a given app identifier.
     *
     * @return array<string>
     */
    public function getMcpServerNamesForApp(string $appIdentifier): array
    {
        $appClass = $this->getAppClass($appIdentifier);

        if (! $appClass || ! class_exists($appClass)) {
            return [];
        }

        if (! is_subclass_of($appClass, IntranetAppInterface::class)) {
            return [];
        }

        $servers = $appClass::mcpServers();

        return array_keys($servers);
    }

    /**
     * Configure MCP servers for an app dynamically at runtime.
     */
    public function configureMcpServersForApp(string $appIdentifier, string $accessToken): void
    {
        $appClass = $this->getAppClass($appIdentifier);

        if (! $appClass || ! class_exists($appClass)) {
            return;
        }

        if (! is_subclass_of($appClass, IntranetAppInterface::class)) {
            return;
        }

        $servers = $appClass::mcpServers();

        foreach ($servers as $serverName => $serverConfig) {
            // Check if server already exists in config
            $existingConfig = config("relay.servers.{$serverName}");
            
            // Use existing URL if available, otherwise generate or use provided
            if ($existingConfig && isset($existingConfig['url'])) {
                $url = $existingConfig['url'];
            } else {
                $url = $serverConfig['url'] ?? $this->generateServerUrl($appIdentifier, $serverName);
            }

            // Merge with existing config to preserve other settings
            $serverConfigArray = [
                'transport' => Transport::Http,
                'url' => $url,
                'timeout' => $existingConfig['timeout'] ?? env('RELAY_SERVER_TIMEOUT', 30),
                'headers' => array_merge(
                    $existingConfig['headers'] ?? [],
                    [
                        'Authorization' => 'Bearer '.$accessToken,
                    ]
                ),
            ];

            // Log the URL being used for debugging
            Log::debug('Configuring MCP server', [
                'server' => $serverName,
                'url' => $url,
                'appIdentifier' => $appIdentifier,
            ]);

            // Configure relay server
            config([
                "relay.servers.{$serverName}" => $serverConfigArray,
            ]);
        }
    }

    /**
     * Generate default server URL based on app identifier and server name.
     */
    private function generateServerUrl(string $appIdentifier, string $serverName): string
    {
        // Use localhost for local development, otherwise use app.url
        $baseUrl = env('APP_ENV') === 'local' 
            ? 'http://localhost' 
            : config('app.url', 'http://localhost');

        // If server name matches app identifier, use simple pattern
        if ($serverName === $appIdentifier) {
            return rtrim($baseUrl, '/')."/mcp/apps/{$appIdentifier}";
        }

        // Otherwise use full pattern
        return rtrim($baseUrl, '/')."/mcp/apps/{$appIdentifier}/{$serverName}";
    }
}
