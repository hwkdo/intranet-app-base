<?php

use Hwkdo\IntranetAppBase\Services\McpServerService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Facades\Tool;
use Prism\Prism\Streaming\Events\TextDeltaEvent;
use Prism\Prism\Streaming\Events\ThinkingEvent;
use Prism\Prism\Streaming\Events\ToolCallEvent;
use Prism\Prism\Streaming\Events\ToolResultEvent;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Relay\Facades\Relay;

new class extends Component
{
    public array $messages = [];
    public string $prompt = '';
    public string $streamingData = '';
    public bool $isStreaming = false;
    public ?string $model = null;
    public ?string $apiKey = null;
    public ?string $baseUrl = null;
    public bool $useMcpTools = true;
    public ?string $appIdentifier = null;

    /** @var string|Provider|null Von Livewire gesetzt (z. B. 'openwebui-completions'), wird bei Nutzung normalisiert. */
    public string|Provider|null $provider = null;

    public function mount($model = null, $apiKey = null, $baseUrl = null, $useMcpTools = true, $appIdentifier = null, $provider = null): void
    {
        $this->model = $model ?? config('openwebui-api-laravel.default_model', 'gpt-4o-mini');
        $this->apiKey = $apiKey;
        $this->baseUrl = $baseUrl ?? config('openwebui-api-laravel.base_api_url', 'https://chat.ai.hwk-do.com/api');
        $this->useMcpTools = $useMcpTools;
        $this->appIdentifier = $appIdentifier;
        $this->provider = $this->normalizeProvider($this->provider ?? $provider);
    }

    private function resolveProvider(): Provider
    {
        return $this->provider instanceof Provider
            ? $this->provider
            : $this->normalizeProvider($this->provider);
    }

    private function normalizeProvider(mixed $provider): Provider
    {
        if ($provider instanceof Provider) {
            return $provider;
        }
        if ($provider === 'openwebui-completions') {
            return Provider::Ollama;
        }
        if (is_string($provider)) {
            return Provider::tryFrom($provider) ?? Provider::Ollama;
        }

        return Provider::Ollama;
    }

    public function sendMessage(): void
    {
        if (empty(trim($this->prompt))) {
            return;
        }

        $userMessage = trim($this->prompt);
        $this->prompt = '';

        // Add user message to history
        $this->messages[] = [
            'role' => 'user',
            'content' => $userMessage,
        ];

        // Reset streaming data
        $this->streamingData = '';
        $this->isStreaming = true;

        // Trigger streaming
        $this->js('$wire.streamResponse()');
    }

    public function streamResponse(): void
    {
        // Initialize stream data structure early to ensure valid JSON
        $streamData = [
            'text' => '',
            'thinking' => '',
            'toolCalls' => [],
            'toolResults' => [],
        ];

        if (empty($this->apiKey) || empty($this->model) || empty($this->baseUrl)) {
            $this->isStreaming = false;
            $this->streamingData = json_encode($streamData);

            return;
        }

        // Build messages array for Prism
        // WICHTIG: Wir übergeben nur den Text-Content, KEINE Tool-Calls aus der History
        // da diese als Arrays gespeichert sind und Prism ToolCall-Objekte erwartet
        $prismMessages = [];
        foreach ($this->messages as $msg) {
            if ($msg['role'] === 'user') {
                $prismMessages[] = new UserMessage($msg['content']);
            } elseif ($msg['role'] === 'assistant') {
                // Übergebe nur den Content ohne Tool-Calls, da Tool-Calls
                // nicht korrekt serialisiert/deserialisiert werden können
                $prismMessages[] = new AssistantMessage($msg['content']);
            }
        }

        try {

            // Configure Prism with OpenWebUI Completions provider
            $baseUrl = rtrim($this->baseUrl, '/');

            $prismRequest = Prism::text()
                ->using($this->resolveProvider(), $this->model, [
                    'url' => $baseUrl,
                    'base_url' => $baseUrl,
                    'api_key' => $this->apiKey,
                ])
                ->withMessages($prismMessages);

            // Füge MCP-Tools hinzu wenn aktiviert
            if ($this->useMcpTools && ! empty($this->appIdentifier)) {
                try {
                    /** @var \App\Models\User $user */
                    $user = Auth::user();
                    if ($user) {
                        // Hole oder erstelle den API Token für MCP-Zugriff
                        $accessToken = $user->settings->ai->intranetV3ApiToken;

                        // Wenn kein Token gespeichert ist, erstelle einen neuen
                        if (empty($accessToken)) {
                            $tokenResult = $user->createToken('intranet-v3-mcp-access', ['mcp:use']);
                            $accessToken = $tokenResult->accessToken;

                            // Speichere den Token in den User Settings
                            $settings = $user->settings;
                            $aiSettings = new \App\Data\UserAiSettings(
                                openWebUiApiToken: $settings->ai->openWebUiApiToken,
                                intranetV3ApiToken: $accessToken,
                            );
                            $settings->ai = $aiSettings;
                            $user->settings = $settings;
                            $user->save();
                        }

                        // Konfiguriere MCP-Server für die App
                        if (! empty($accessToken)) {
                            $mcpService = new McpServerService;
                            $mcpService->configureMcpServersForApp($this->appIdentifier, $accessToken);

                            // Lade Server-Namen für die App
                            $serverNames = $mcpService->getMcpServerNamesForApp($this->appIdentifier);

                            if (! empty($serverNames)) {
                                // Sammle Tools von allen Servern
                                $allTools = collect();

                                foreach ($serverNames as $serverName) {
                                    try {
                                        $serverTools = Relay::tools($serverName);
                                        $allTools = $allTools->merge($serverTools);
                                    } catch (\Throwable $e) {
                                        Log::error('Failed to load MCP tools from server', [
                                            'server' => $serverName,
                                            'error' => $e->getMessage(),
                                        ]);
                                        // Fahre mit anderen Servern fort
                                    }
                                }

                                if ($allTools->isNotEmpty()) {
                                    $prismRequest = $prismRequest
                                        ->withTools($allTools->toArray())
                                        ->withMaxSteps(5);
                                }
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    Log::error('Failed to load MCP tools', ['error' => $e->getMessage()]);
                    // Fahre ohne MCP-Tools fort
                }
            }

            // Send initial empty stream data to ensure valid JSON
            $this->stream(
                json_encode($streamData),
                true,
                'streamingData'
            );

            $generator = $prismRequest->asStream();

            $eventCount = 0;
            foreach ($generator as $event) {
                $eventCount++;
                // Process different event types
                if ($event instanceof TextDeltaEvent) {
                    $streamData['text'] .= $event->delta;
                } elseif ($event instanceof ThinkingEvent) {
                    $streamData['thinking'] .= $event->delta;
                } elseif ($event instanceof ToolCallEvent) {
                    $streamData['toolCalls'][] = [
                        'name' => $event->toolCall->name ?? 'unknown',
                        'id' => $event->toolCall->id ?? null,
                        'arguments' => $event->toolCall->arguments() ?? [],
                    ];
                } elseif ($event instanceof ToolResultEvent) {
                    $streamData['toolResults'][] = [
                        'result' => $event->toolResult->result ?? '',
                        'toolName' => $event->toolResult->toolName ?? 'unknown',
                        'toolCallId' => $event->toolResult->toolCallId ?? null,
                        'args' => $event->toolResult->args ?? [],
                    ];
                }

                // Stream update to frontend
                $this->stream(
                    json_encode($streamData),
                    true,
                    'streamingData'
                );
            }

            // Log if no events were received
            if ($eventCount === 0) {
                Log::warning('Prism chat stream returned no events', [
                    'model' => $this->model,
                    'baseUrl' => $baseUrl,
                    'messageCount' => count($prismMessages),
                ]);
            }

            // Add complete answer to messages
            if (! empty($streamData['text']) || ! empty($streamData['toolCalls'])) {
                $this->messages[] = [
                    'role' => 'assistant',
                    'content' => $streamData['text'],
                    'toolCalls' => $streamData['toolCalls'],
                    'toolResults' => $streamData['toolResults'],
                    'thinking' => $streamData['thinking'],
                ];
            }

            // Ensure streamingData is always set to valid JSON
            $this->streamingData = json_encode($streamData);
            $this->isStreaming = false;
        } catch (\Throwable $e) {
            $this->isStreaming = false;

            $errorDetail = $this->extractApiErrorDetail($e);

            Log::error('Prism chat streaming error', [
                'message' => $e->getMessage(),
                'detail' => $errorDetail,
                'trace' => $e->getTraceAsString(),
            ]);

            $errorText = 'Fehler: '.$e->getMessage().($errorDetail !== '' ? ' — '.$errorDetail : '');
            $errorData = [
                'text' => $errorText,
                'thinking' => '',
                'toolCalls' => [],
                'toolResults' => [],
            ];

            $this->streamingData = json_encode($errorData);

            // In Nachrichten übernehmen, damit die Fehlermeldung sichtbar bleibt
            $this->messages[] = [
                'role' => 'assistant',
                'content' => $errorText,
                'toolCalls' => [],
                'toolResults' => [],
                'thinking' => '',
            ];

            // Also send via stream to ensure frontend receives it
            try {
                $this->stream(
                    json_encode($errorData),
                    true,
                    'streamingData'
                );
            } catch (\Throwable $streamError) {
                // Ignore stream errors if stream is already closed
                Log::error('Failed to stream error data', [
                    'error' => $streamError->getMessage(),
                ]);
            }
        }
    }

    /**
     * Liest aus einer HTTP-RequestException (z. B. 401) die API-Antwort aus,
     * damit verständlichere Fehlermeldungen angezeigt werden können.
     */
    private function extractApiErrorDetail(\Throwable $e): string
    {
        $previous = $e->getPrevious();
        if (! $previous instanceof RequestException || ! isset($previous->response)) {
            return '';
        }

        $response = $previous->response;
        $status = $response->status();
        $body = $response->json();
        if (! is_array($body)) {
            $raw = $response->body();
            if ($raw !== '' && strlen($raw) <= 500) {
                return $raw;
            }

            return $status === 401
                ? 'Nicht autorisiert. Bitte prüfen Sie Ihren OpenWebUI API-Token in den Einstellungen.'
                : '';
        }

        $detail = $body['detail'] ?? $body['error'] ?? $body['message'] ?? null;
        if (is_string($detail)) {
            return $detail;
        }
        if (is_array($detail)) {
            return json_encode($detail, JSON_UNESCAPED_UNICODE);
        }
        if ($status === 401) {
            return 'Nicht autorisiert. Bitte prüfen Sie Ihren OpenWebUI API-Token in den Einstellungen.';
        }

        return '';
    }
};

?>

<div class="flex flex-col w-full">
    <flux:card class="flex flex-col glass-card">
        <div class="p-4 space-y-4" id="chat-messages">
            @if (empty($messages) && !$isStreaming)
                <div class="flex items-center justify-center py-8 text-zinc-500 dark:text-zinc-400">
                    <flux:text>Stellen Sie eine Frage, um zu beginnen...</flux:text>
                </div>
            @else
                @foreach ($messages as $message)
                    <div class="flex gap-3 {{ $message['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                        @if ($message['role'] === 'user')
                            <div class="max-w-[80%] rounded-lg p-3 bg-primary-500 text-white">
                                <flux:text class="whitespace-pre-wrap">{{ $message['content'] }}</flux:text>
                            </div>
                        @else
                            <div
                                class="prose prose-sm max-h-fit max-w-fit min-w-24 space-y-2 rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-600 dark:bg-zinc-700"
                                x-data="markdownProcessor"
                            >
                                <flux:heading>AI</flux:heading>

                                <!-- Thinking preview (collapsible) -->
                                @if (!empty($message['thinking'] ?? ''))
                                    <div x-show="hasThinking()" class="border-t border-zinc-200 pt-2 dark:border-zinc-600">
                                        <button
                                            @click="toggleThinking()"
                                            class="flex items-center gap-1 text-sm text-zinc-600 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200"
                                        >
                                            <flux:icon.chevron-right x-show="!showThinking" class="h-3 w-3" />
                                            <flux:icon.chevron-down x-show="showThinking" class="h-3 w-3" />
                                            🧠
                                        </button>
                                        <div x-show="showThinking" x-collapse class="mt-2">
                                            <div class="rounded bg-zinc-100 p-2 text-xs dark:bg-zinc-800">
                                                <article
                                                    wire:ignore
                                                    class="prose prose-zinc prose-sm prose-p:m-0 prose-code:font-mono prose-pre:text-xs dark:prose-invert max-w-none break-words"
                                                    x-html="thinkingHtml"
                                                ></article>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                <!-- Tool calls display -->
                                @if (!empty($message['toolCalls'] ?? []))
                                    <div class="border-t border-zinc-200 pt-2 dark:border-zinc-600">
                                        @foreach ($message['toolCalls'] as $toolCall)
                                            <div class="mt-1 flex items-center gap-2 text-xs">
                                                <flux:icon.wrench-screwdriver class="h-3 w-3 text-blue-500" />
                                                <span class="font-mono text-blue-600 dark:text-blue-400">{{ $toolCall['name'] ?? 'unknown' }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                <!-- Main content -->
                                <flux:text>
                                    <span
                                        x-ref="raw"
                                        class="hidden"
                                    >
                                        {{ json_encode(['text' => $message['content'] ?? '', 'thinking' => $message['thinking'] ?? '']) }}
                                    </span>
                                    <article
                                        wire:ignore
                                        class="prose prose-zinc prose-sm prose-p:m-0 prose-code:font-mono prose-pre:border prose-pre:border-zinc-200 prose-pre:dark:border-zinc-600 prose-pre:rounded-md prose-pre:p-4 prose-pre:mb-1 prose-pre:bg-zinc-100 prose-pre:dark:bg-zinc-800 prose-pre:text-zinc-900 prose-pre:dark:text-zinc-100 prose-table:border-collapse prose-table:w-full prose-th:border prose-th:border-zinc-300 prose-th:dark:border-zinc-600 prose-th:bg-zinc-100 prose-th:dark:bg-zinc-800 prose-th:px-4 prose-th:py-2 prose-td:border prose-td:border-zinc-300 prose-td:dark:border-zinc-600 prose-td:px-4 prose-td:py-2 dark:prose-invert max-w-none break-words"
                                        x-html="html"
                                    ></article>
                                </flux:text>
                            </div>
                        @endif
                    </div>
                @endforeach

                @if ($isStreaming)
                    <div class="flex gap-3 justify-start">
                        <div
                            class="prose prose-sm max-h-fit max-w-fit min-w-24 space-y-2 rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-600 dark:bg-zinc-700"
                            x-data="markdownProcessor"
                        >
                            <flux:heading>AI</flux:heading>

                            <!-- Thinking indicator -->
                            <div x-show="isCurrentlyThinking()" class="flex items-center gap-2 text-zinc-500 dark:text-zinc-400">
                                <div class="flex space-x-1">
                                    <div class="h-1 w-1 animate-bounce rounded-full bg-zinc-400 [animation-delay:-0.3s]"></div>
                                    <div class="h-1 w-1 animate-bounce rounded-full bg-zinc-400 [animation-delay:-0.15s]"></div>
                                    <div class="h-1 w-1 animate-bounce rounded-full bg-zinc-400"></div>
                                </div>
                                <span class="text-sm">Thinking...</span>
                            </div>

                            <!-- Tool usage indicator -->
                            <div x-show="isCurrentlyUsingTools()" class="flex items-center gap-2 text-blue-500 dark:text-blue-400">
                                <flux:icon.wrench-screwdriver class="h-4 w-4 animate-spin" />
                                <span class="text-sm">Using tools...</span>
                            </div>

                            <!-- Thinking preview (collapsible) -->
                            <div x-show="hasThinking()" class="border-t border-zinc-200 pt-2 dark:border-zinc-600">
                                <button
                                    @click="toggleThinking()"
                                    class="flex items-center gap-1 text-sm text-zinc-600 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200"
                                >
                                    <flux:icon.chevron-right x-show="!showThinking" class="h-3 w-3" />
                                    <flux:icon.chevron-down x-show="showThinking" class="h-3 w-3" />
                                    🧠
                                </button>
                                <div x-show="showThinking" x-collapse class="mt-2">
                                    <div class="rounded bg-zinc-100 p-2 text-xs dark:bg-zinc-800">
                                        <article
                                            wire:ignore
                                            class="prose prose-zinc prose-sm prose-p:m-0 prose-code:font-mono prose-pre:text-xs dark:prose-invert max-w-none break-words"
                                            x-html="thinkingHtml"
                                        ></article>
                                    </div>
                                </div>
                            </div>

                            <!-- Tool calls display -->
                            <div x-show="hasToolCalls()" class="border-t border-zinc-200 pt-2 dark:border-zinc-600">
                                <template x-for="toolCall in streamData.toolCalls" :key="toolCall.id">
                                    <div class="mt-1 flex items-center gap-2 text-xs">
                                        <flux:icon.wrench-screwdriver class="h-3 w-3 text-blue-500" />
                                        <span x-text="toolCall.name" class="font-mono text-blue-600 dark:text-blue-400"></span>
                                    </div>
                                </template>
                            </div>

                            <!-- Main content -->
                            <flux:text>
                                <span
                                    x-ref="raw"
                                    class="hidden"
                                    wire:stream="streamingData"
                                    wire:replace
                                >
                                    {{ $streamingData ?: '{}' }}
                                </span>
                                <article
                                    wire:ignore
                                    class="prose prose-zinc prose-sm prose-p:m-0 prose-code:font-mono prose-pre:border prose-pre:border-zinc-200 prose-pre:dark:border-zinc-600 prose-pre:rounded-md prose-pre:p-4 prose-pre:mb-1 prose-pre:bg-zinc-100 prose-pre:dark:bg-zinc-800 prose-pre:text-zinc-900 prose-pre:dark:text-zinc-100 prose-table:border-collapse prose-table:w-full prose-th:border prose-th:border-zinc-300 prose-th:dark:border-zinc-600 prose-th:bg-zinc-100 prose-th:dark:bg-zinc-800 prose-th:px-4 prose-th:py-2 prose-td:border prose-td:border-zinc-300 prose-td:dark:border-zinc-600 prose-td:px-4 prose-td:py-2 dark:prose-invert max-w-none break-words"
                                    x-html="html"
                                    x-show="html.length > 0"
                                ></article>
                                @if (empty($streamingData))
                                    <div class="flex items-center gap-2 text-zinc-500 dark:text-zinc-400">
                                        <flux:icon.loading class="size-4 animate-spin" />
                                        <flux:text>Antwort wird geladen...</flux:text>
                                    </div>
                                @endif
                            </flux:text>
                        </div>
                    </div>
                @endif
            @endif
        </div>

        <div class="border-t border-zinc-200 dark:border-zinc-700 p-4">
            <form wire:submit="sendMessage" class="flex gap-2">
                <flux:input
                    wire:model="prompt"
                    placeholder="Nachricht eingeben..."
                    class="flex-1"
                    wire:keydown.enter.prevent="sendMessage"
                />
                <flux:button
                    type="submit"
                    variant="primary"
                    wire:loading.attr="disabled"
                    wire:target="sendMessage"
                >
                    <span wire:loading.remove>Senden</span>
                    <span wire:loading>Senden...</span>
                </flux:button>
            </form>
        </div>
    </flux:card>
</div>

<script>
    document.addEventListener('livewire:initialized', () => {
        const container = document.getElementById('chat-messages');
        Livewire.hook('commit', ({ succeed }) => {
            succeed(() => {
                if (container) container.scrollTop = container.scrollHeight;
            });
        });
    });
</script>
