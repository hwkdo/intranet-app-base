@props([
    'apiKey' => '',        // OpenWebUI API Token (required)
    'model' => '',         // Model ID (required)
    'endpoint' => '',      // API Endpoint URL (required)
    'height' => '600px',   // Widget Höhe (optional)
    'title' => null,       // Optional: Widget-Titel
    'containerId' => null, // Optional: Custom Container ID
])

@php
    // Generate unique container ID if not provided
    $containerId = $containerId ?? 'owui-chat-container-' . uniqid();
    
    // Validate required props
    $hasRequiredProps = !empty($apiKey) && !empty($model) && !empty($endpoint);
@endphp

@if ($hasRequiredProps)
    <div class="w-full">
        <link rel="stylesheet" href="{{ route('intranet-app-base.openwebui-widget.css') }}">
        <link rel="stylesheet" href="{{ route('intranet-app-base.openwebui-widget.dark-css') }}">
        <div id="{{ $containerId }}" class="w-full" style="height: {{ $height }}; min-height: {{ $height }};"></div>
    </div>
    
    <script type="module">
        import ChatWidget, { mount } from '{{ route('intranet-app-base.openwebui-widget.js') }}';
        
        // Get URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        
        // Set API key, model, and endpoint from props
        const apiKey = @js($apiKey);
        const model = @js($model);
        const endpoint = @js($endpoint);
        
        // Add parameters to URL if not already present
        if (apiKey && !urlParams.has('api_key')) {
            urlParams.set('api_key', apiKey);
        }
        if (model && !urlParams.has('model')) {
            urlParams.set('model', model);
        }
        if (endpoint && !urlParams.has('endpoint')) {
            urlParams.set('endpoint', endpoint);
        }
        
        // Update URL without reload
        const newUrl = window.location.pathname + '?' + urlParams.toString();
        window.history.replaceState({}, '', newUrl);
        
        // Mount the widget
        mount(ChatWidget, {
            target: document.getElementById(@js($containerId))
        });
    </script>
@else
    <flux:card>
        <flux:callout variant="warning" class="mb-4">
            <flux:heading size="sm">Konfiguration unvollständig</flux:heading>
            <flux:text>
                Um den Chat zu nutzen, müssen alle erforderlichen Parameter (API-Key, Model, Endpoint) bereitgestellt werden.
            </flux:text>
        </flux:callout>
    </flux:card>
@endif
