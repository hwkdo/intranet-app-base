<?php

// config for Hwkdo/IntranetAppBase
return [
    /**
     * Provider-Klassen für Kern-Widgets des persönlichen Intranet-Hauptdashboards (nicht App-spezifisch).
     * Die Anwendung setzt dies typischerweise in AppServiceProvider per config([...]).
     *
     * @var array<int, class-string>
     */
    'main_dashboard_core_widget_providers' => [],

    /**
     * Optional GitHub personal access token for higher API rate limits (Releases auf App-Info-Seiten).
     */
    'github_token' => env('GITHUB_TOKEN'),

    /**
     * Cache-TTL in Sekunden für GitHub-Release-Listen pro Repository.
     */
    'github_release_cache_ttl' => (int) env('GITHUB_RELEASE_CACHE_TTL', 3600),

    /**
     * Host-Provider für dynamische Benachrichtigungstypen (z. B. News-Kategorien).
     *
     * @var array<int, class-string<\Hwkdo\IntranetAppBase\Interfaces\NotificationTypeProviderInterface>>
     */
    'notification_type_providers' => [],
];
