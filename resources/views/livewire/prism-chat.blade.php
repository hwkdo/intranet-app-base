<?php

use App\Models\User;
use Hwkdo\IntranetAppBase\IntranetAppBase;
use Hwkdo\IntranetAppBase\Services\McpServerService;
use Hwkdo\IntranetAppBase\Support\AiUsage;
use Hwkdo\OpenwebuiApiLaravel\Services\OpenWebUiUserService;
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

    /** Wenn true: Layout für Einbettung in Dashboard-Widgets (Höhe begrenzt, Nachrichtenbereich scrollt). */
    public bool $embedded = false;

    /**
     * Wenn true: derselbe System-Prompt wie beim OAuth-Sync nach Open WebUI (OpenWebUiUserService::setSystemPrompt)
     * wird lokal aus Config + Benutzer gebaut und an die Completion angehängt (OWUI-API macht das nicht von selbst).
     */
    public bool $injectOpenWebUiUserSystemPrompt = true;

    /** App-spezifischer Zusatz zum System-Prompt (z. B. Fuhrpark-Buchungsregeln). */
    public ?string $additionalSystemPrompt = null;

    /** Relative Datumsangaben («morgen») per aktuellem Zeitbezug auflösen. */
    public bool $injectTemporalContext = true;

    /**
     * Prompt-Vorschläge für den leeren Chat (klickbar, werden direkt gesendet).
     *
     * @var list<string>
     */
    public array $suggestions = [];

    /** Anzeigename der App im Chat-Header. */
    public ?string $appName = null;

    /**
     * Immer der OpenAI-kompatible Chat-Completions-Client (llama.cpp über Open WebUI).
     *
     * @var string|Provider|null
     */
    public string|Provider|null $provider = null;

    public function mount($model = null, $apiKey = null, $baseUrl = null, $useMcpTools = true, $appIdentifier = null, $provider = null, $injectOpenWebUiUserSystemPrompt = true, $embedded = false, $additionalSystemPrompt = null, $injectTemporalContext = true, $suggestions = null): void
    {
        abort_unless(AiUsage::allowed(), 403);

        $this->model = $model ?? config('openwebui-api-laravel.default_model', 'gpt-4o-mini');
        $this->apiKey = $apiKey;
        $this->baseUrl = $this->normalizeBaseUrl($baseUrl);
        $this->useMcpTools = $useMcpTools;
        $this->appIdentifier = $appIdentifier;
        $this->injectOpenWebUiUserSystemPrompt = (bool) $injectOpenWebUiUserSystemPrompt;
        $this->embedded = (bool) $embedded;
        $this->injectTemporalContext = (bool) $injectTemporalContext;
        $this->additionalSystemPrompt = is_string($additionalSystemPrompt) && trim($additionalSystemPrompt) !== ''
            ? trim($additionalSystemPrompt)
            : null;
        $this->provider = 'openwebui-completions';
        $this->appName = is_string($appIdentifier) && $appIdentifier !== ''
            ? IntranetAppBase::displayNameForAppIdentifier($appIdentifier)
            : null;
        $this->suggestions = is_array($suggestions)
            ? array_values(array_filter($suggestions, fn ($suggestion): bool => is_string($suggestion) && trim($suggestion) !== ''))
            : [
                'Was kannst du für mich tun?',
                'Fasse die wichtigsten Funktionen dieser App zusammen.',
                'Welche Informationen brauchst du von mir?',
            ];
    }

    public function sendSuggestion(string $suggestion): void
    {
        $this->prompt = $suggestion;
        $this->sendMessage();
    }

    public function resetChat(): void
    {
        $this->messages = [];
        $this->prompt = '';
        $this->streamingData = '';
        $this->isStreaming = false;
    }

    private function resolveProvider(): string
    {
        return 'openwebui-completions';
    }

    private function normalizeBaseUrl(mixed $baseUrl): string
    {
        $completionsUrl = rtrim((string) config('openwebui-api-laravel.base_api_url', 'https://chat.ai.hwk-do.com/api'), '/');
        $ollamaUrl = rtrim((string) config('openwebui-api-laravel.base_api_url_ollama', ''), '/');
        $given = is_string($baseUrl) ? rtrim(trim($baseUrl), '/') : '';

        if ($given === '' || ($ollamaUrl !== '' && $given === $ollamaUrl)) {
            return $completionsUrl;
        }

        return $given;
    }

    private function resolveSystemPrompt(): string
    {
        $parts = [];

        if ($this->injectOpenWebUiUserSystemPrompt && class_exists(OpenWebUiUserService::class)) {
            $intranetUser = Auth::user();
            if ($intranetUser instanceof User) {
                $localSystem = app(OpenWebUiUserService::class)->buildSystemPromptForUser($intranetUser);
                if ($localSystem !== '') {
                    $parts[] = $localSystem;
                }
            }
        }

        if ($this->injectTemporalContext) {
            $parts[] = $this->buildTemporalContext();
        }

        if ($this->additionalSystemPrompt !== null) {
            $parts[] = $this->additionalSystemPrompt;
        }

        return implode("\n\n", array_filter($parts));
    }

    private function buildTemporalContext(): string
    {
        $now = now()->locale('de');
        $timezone = (string) config('app.timezone', 'Europe/Berlin');

        return sprintf(
            'Zeitbezug (Referenz für alle relativen Datums- und Zeitangaben): Heute ist %s, aktuelle Uhrzeit %s (%s). '.
            'Löse «heute», «morgen», «übermorgen», «nächsten Montag» usw. selbst in konkrete ISO-8601-Werte auf (z. B. morgen 08:00 → %sT08:00:00). '.
            'Frage nicht nach dem Datum, wenn die relative Angabe eindeutig ist — handle direkt mit den MCP-Tools.',
            $now->isoFormat('dddd, D. MMMM YYYY'),
            $now->format('H:i'),
            $timezone,
            $now->copy()->addDay()->toDateString()
        );
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

            $baseUrl = $this->normalizeBaseUrl($this->baseUrl);

            $prismRequest = Prism::text()
                ->using($this->resolveProvider(), $this->model, [
                    'url' => $baseUrl,
                    'base_url' => $baseUrl,
                    'api_key' => $this->apiKey,
                ])
                ->withMessages($prismMessages);

            $systemPrompt = $this->resolveSystemPrompt();
            if ($systemPrompt !== '') {
                $prismRequest = $prismRequest->withSystemPrompt($systemPrompt);
            }

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
                                        ->withMaxSteps(10);
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

            if (empty(trim($streamData['text'])) && ! empty($streamData['toolCalls'])) {
                $streamData['text'] = 'Die Anfrage wurde bearbeitet, aber es konnte keine Antwort formuliert werden. '
                    .'Bitte die Frage erneut stellen oder in der App prüfen, ob die Aktion erfolgreich war.';

                try {
                    $this->stream(
                        json_encode($streamData),
                        true,
                        'streamingData'
                    );
                } catch (\Throwable) {
                    // Stream may already be closed
                }
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

<div
    @class([
        'prism-chat relative isolate flex w-full flex-col overflow-hidden rounded-2xl border border-blue-100/80 bg-white/75 shadow-xl shadow-blue-950/5 backdrop-blur-xl dark:border-white/10 dark:bg-slate-950/30 dark:shadow-black/30',
        'h-full min-h-0 flex-1' => $embedded,
        'h-[min(78dvh,56rem)] min-h-[32rem]' => ! $embedded,
    ])
    x-data="{
        atBottom: true,
        scrollToBottom(smooth = true) {
            const scroller = this.$refs.scroller;
            scroller.scrollTo({ top: scroller.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
        },
        updateAtBottom() {
            const scroller = this.$refs.scroller;
            this.atBottom = scroller.scrollHeight - scroller.scrollTop - scroller.clientHeight < 80;
        },
        init() {
            this.$nextTick(() => this.scrollToBottom(false));
            new MutationObserver(() => {
                if (this.atBottom) {
                    this.scrollToBottom(false);
                }
            }).observe(this.$refs.scroller, { childList: true, subtree: true, characterData: true });
        },
    }"
>
    @assets
        <style>
            .prism-chat-message { animation: prism-chat-in .35s cubic-bezier(.21, 1.02, .73, 1) both; }
            @keyframes prism-chat-in { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }

            .prism-chat-shimmer {
                background: linear-gradient(90deg, rgb(100 116 139) 0%, rgb(99 102 241) 50%, rgb(100 116 139) 100%);
                background-size: 200% 100%;
                -webkit-background-clip: text;
                background-clip: text;
                color: transparent;
                animation: prism-chat-shimmer 2.2s linear infinite;
            }
            .dark .prism-chat-shimmer { background-image: linear-gradient(90deg, rgb(148 163 184) 0%, rgb(199 210 254) 50%, rgb(148 163 184) 100%); }
            @keyframes prism-chat-shimmer { from { background-position: 200% 0; } to { background-position: -200% 0; } }

            @media (prefers-reduced-motion: reduce) {
                .prism-chat-message, .prism-chat-shimmer { animation: none; }
            }

            .prism-chat [data-flux-composer] textarea::placeholder { color: rgb(100 116 139); }
            .dark .prism-chat [data-flux-composer] textarea::placeholder { color: rgb(255 255 255 / .5); }
            .dark .prism-chat [data-flux-composer] textarea { color: rgb(255 255 255 / .92); }

            .prism-chat-markdown { font-size: .875rem; line-height: 1.7; overflow-wrap: anywhere; color: rgb(30 41 59); }
            .dark .prism-chat-markdown { color: rgb(226 232 240); }
            .prism-chat-markdown > * + * { margin-top: .75em; }
            .prism-chat-markdown > :first-child { margin-top: 0; }
            .prism-chat-markdown > :last-child { margin-bottom: 0; }
            .prism-chat-markdown h1, .prism-chat-markdown h2, .prism-chat-markdown h3, .prism-chat-markdown h4 { font-weight: 600; line-height: 1.35; color: rgb(15 23 42); margin-top: 1.25em; }
            .dark .prism-chat-markdown h1, .dark .prism-chat-markdown h2, .dark .prism-chat-markdown h3, .dark .prism-chat-markdown h4 { color: #fff; }
            .prism-chat-markdown h1 { font-size: 1.25rem; }
            .prism-chat-markdown h2 { font-size: 1.125rem; }
            .prism-chat-markdown h3, .prism-chat-markdown h4 { font-size: 1rem; }
            .prism-chat-markdown strong { font-weight: 600; color: rgb(15 23 42); }
            .dark .prism-chat-markdown strong { color: #fff; }
            .prism-chat-markdown ul, .prism-chat-markdown ol { padding-left: 1.35rem; }
            .prism-chat-markdown ul { list-style: disc; }
            .prism-chat-markdown ol { list-style: decimal; }
            .prism-chat-markdown li + li { margin-top: .3em; }
            .prism-chat-markdown li > ul, .prism-chat-markdown li > ol { margin-top: .3em; }
            .prism-chat-markdown li::marker { color: rgb(99 102 241); }
            .dark .prism-chat-markdown li::marker { color: rgb(165 180 252); }
            .prism-chat-markdown a { color: rgb(37 99 235); font-weight: 600; text-decoration: underline; text-decoration-thickness: 2px; text-underline-offset: 3px; text-decoration-color: rgb(37 99 235 / .35); transition: text-decoration-color .15s; }
            .prism-chat-markdown a:hover { text-decoration-color: rgb(37 99 235); }
            .dark .prism-chat-markdown a { color: rgb(147 197 253); text-decoration-color: rgb(147 197 253 / .4); }
            .dark .prism-chat-markdown a:hover { text-decoration-color: rgb(147 197 253); }
            .prism-chat-markdown :not(pre) > code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .85em; padding: .12em .4em; border-radius: .375rem; background: rgb(99 102 241 / .1); color: rgb(67 56 202); }
            .dark .prism-chat-markdown :not(pre) > code { background: rgb(165 180 252 / .15); color: rgb(199 210 254); }
            .prism-chat-markdown pre { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .8rem; line-height: 1.6; padding: .9rem 1rem; border-radius: .75rem; overflow-x: auto; background: rgb(15 23 42); color: rgb(226 232 240); border: 1px solid rgb(255 255 255 / .06); }
            .prism-chat-markdown blockquote { border-left: 3px solid rgb(129 140 248); padding-left: .9rem; color: rgb(71 85 105); font-style: italic; }
            .dark .prism-chat-markdown blockquote { color: rgb(203 213 225); }
            .prism-chat-markdown hr { border: 0; border-top: 1px solid rgb(203 213 225 / .7); margin: 1.25em 0; }
            .dark .prism-chat-markdown hr { border-top-color: rgb(255 255 255 / .12); }
            .prism-chat-markdown table { display: block; max-width: 100%; overflow-x: auto; border-collapse: separate; border-spacing: 0; border: 1px solid rgb(203 213 225 / .8); border-radius: .75rem; font-size: .8rem; }
            .dark .prism-chat-markdown table { border-color: rgb(255 255 255 / .12); }
            .prism-chat-markdown th, .prism-chat-markdown td { padding: .5rem .75rem; text-align: left; border-bottom: 1px solid rgb(226 232 240); }
            .dark .prism-chat-markdown th, .dark .prism-chat-markdown td { border-bottom-color: rgb(255 255 255 / .08); }
            .prism-chat-markdown tr:last-child td { border-bottom: 0; }
            .prism-chat-markdown th { font-weight: 600; background: rgb(238 242 255); color: rgb(30 41 59); white-space: nowrap; }
            .dark .prism-chat-markdown th { background: rgb(255 255 255 / .06); color: #fff; }
            .prism-chat-markdown--compact { font-size: .75rem; line-height: 1.6; color: inherit; }
        </style>
    @endassets

    <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
        <div class="absolute -top-32 left-1/2 size-[30rem] -translate-x-1/2 rounded-full bg-linear-to-br from-blue-400/25 via-indigo-400/15 to-transparent blur-3xl dark:from-blue-500/25 dark:via-indigo-500/15"></div>
        <div class="absolute -right-24 -bottom-40 size-96 rounded-full bg-linear-to-tr from-sky-300/20 via-violet-300/10 to-transparent blur-3xl dark:from-sky-500/10 dark:via-violet-500/10"></div>
    </div>

    @unless ($embedded)
        <div class="flex shrink-0 items-center gap-3 border-b border-blue-100/70 px-4 py-3 sm:px-5 dark:border-white/10">
            <div class="relative shrink-0">
                <div class="flex size-10 items-center justify-center rounded-xl bg-linear-to-br from-blue-500 via-indigo-500 to-violet-500 text-white shadow-lg shadow-indigo-500/30 ring-1 ring-white/30">
                    <flux:icon.sparkles variant="solid" class="size-5" />
                </div>
                <span class="absolute -right-0.5 -bottom-0.5 size-3 rounded-full border-2 border-white bg-emerald-500 dark:border-slate-900"></span>
            </div>

            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <flux:heading size="lg" class="truncate">KI-Assistent</flux:heading>
                    @if ($useMcpTools && $appIdentifier)
                        <flux:badge size="sm" color="indigo" icon="bolt" class="max-sm:hidden">App-Werkzeuge</flux:badge>
                    @endif
                </div>
                <flux:text size="sm" class="truncate">
                    <span wire:loading.remove wire:target="sendMessage,sendSuggestion,streamResponse">{{ $appName ? $appName.' · ' : '' }}Bereit</span>
                    <span wire:loading wire:target="sendMessage,sendSuggestion,streamResponse" class="prism-chat-shimmer font-medium">Antwortet …</span>
                </flux:text>
            </div>

            @if (! empty($messages))
                <flux:button
                    size="sm"
                    variant="ghost"
                    icon="pencil-square"
                    wire:click="resetChat"
                    wire:loading.attr="disabled"
                    wire:target="streamResponse"
                >
                    <span class="max-sm:hidden">Neuer Chat</span>
                </flux:button>
            @endif
        </div>
    @endunless

    <div
        x-ref="scroller"
        x-on:scroll.passive="updateAtBottom()"
        class="min-h-0 flex-1 overflow-y-auto overscroll-contain"
    >
        <div @class([
            'mx-auto flex min-h-full w-full max-w-3xl flex-col gap-6',
            'px-3 py-4' => $embedded,
            'px-4 py-6 sm:px-6' => ! $embedded,
        ])>
            @if (empty($messages) && ! $isStreaming)
                <div class="flex flex-1 flex-col items-center justify-center py-6 text-center">
                    <div class="relative mb-6">
                        <div class="absolute inset-0 animate-pulse rounded-3xl bg-linear-to-br from-blue-500 to-violet-500 opacity-40 blur-2xl"></div>
                        <div @class([
                            'relative flex items-center justify-center rounded-2xl bg-linear-to-br from-blue-500 via-indigo-500 to-violet-500 text-white shadow-xl shadow-indigo-500/30 ring-1 ring-white/30',
                            'size-12' => $embedded,
                            'size-16' => ! $embedded,
                        ])>
                            <flux:icon.sparkles variant="solid" :class="$embedded ? 'size-6' : 'size-8'" />
                        </div>
                    </div>

                    <h2 @class([
                        'bg-linear-to-r from-blue-700 via-indigo-600 to-violet-600 bg-clip-text font-semibold tracking-tight text-transparent dark:from-sky-300 dark:via-indigo-200 dark:to-violet-300',
                        'text-lg' => $embedded,
                        'text-2xl sm:text-3xl' => ! $embedded,
                    ])>
                        Hallo{{ auth()->user()?->vorname ? ' '.auth()->user()->vorname : '' }}!
                    </h2>
                    <flux:text class="mt-2 max-w-md">
                        Wie kann ich Ihnen {{ $appName ? 'in '.$appName.' ' : '' }}helfen?
                    </flux:text>

                    @if (! empty($suggestions))
                        <div @class([
                            'mt-8 grid w-full gap-3',
                            'sm:grid-cols-2' => ! $embedded,
                        ])>
                            @foreach ($suggestions as $suggestion)
                                <button
                                    type="button"
                                    wire:key="prism-suggestion-{{ $loop->index }}"
                                    wire:click="sendSuggestion(@js($suggestion))"
                                    x-on:click="atBottom = true"
                                    class="group flex items-start gap-3 rounded-xl border border-blue-100 bg-white/80 p-3.5 text-left text-sm text-slate-700 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-indigo-300 hover:shadow-md hover:shadow-indigo-500/10 focus-visible:ring-2 focus-visible:ring-indigo-500/40 focus-visible:outline-none dark:border-white/10 dark:bg-white/5 dark:text-slate-200 dark:hover:border-indigo-400/50 dark:hover:bg-white/10"
                                >
                                    <flux:icon.chat-bubble-left-ellipsis class="mt-0.5 size-4 shrink-0 text-indigo-500 dark:text-indigo-300" />
                                    <span class="flex-1">{{ $suggestion }}</span>
                                    <flux:icon.arrow-up-right class="mt-0.5 size-4 shrink-0 text-indigo-400 opacity-0 transition group-hover:opacity-100" />
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            @else
                @foreach ($messages as $message)
                    @if ($message['role'] === 'user')
                        <div wire:key="prism-message-{{ $loop->index }}" class="prism-chat-message flex justify-end">
                            <div class="max-w-[85%] rounded-2xl rounded-br-md bg-linear-to-br from-blue-600 to-indigo-600 px-4 py-2.5 text-sm leading-relaxed whitespace-pre-wrap break-words text-white shadow-md shadow-indigo-600/20">{{ $message['content'] }}</div>
                        </div>
                    @else
                        @php($isError = str_starts_with((string) ($message['content'] ?? ''), 'Fehler:'))
                        <div wire:key="prism-message-{{ $loop->index }}" class="prism-chat-message group flex items-start gap-3" x-data="markdownProcessor">
                            <div @class([
                                'mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg text-white shadow-md',
                                'bg-linear-to-br from-blue-500 via-indigo-500 to-violet-500 shadow-indigo-500/25' => ! $isError,
                                'bg-red-500 shadow-red-500/25' => $isError,
                            ])>
                                @if ($isError)
                                    <flux:icon.exclamation-triangle variant="micro" class="size-4" />
                                @else
                                    <flux:icon.sparkles variant="micro" class="size-4" />
                                @endif
                            </div>

                            <div class="min-w-0 flex-1 space-y-2">
                                <span x-ref="raw" class="hidden">{{ json_encode(['text' => $message['content'] ?? '', 'thinking' => $message['thinking'] ?? '']) }}</span>

                                @if (! empty($message['thinking'] ?? ''))
                                    @include('intranet-app-base::livewire.partials.prism-chat-thinking')
                                @endif

                                @if (! empty($message['toolCalls'] ?? []))
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach ($message['toolCalls'] as $toolCall)
                                            <flux:badge size="sm" color="sky" icon="wrench-screwdriver">
                                                <span class="font-mono text-[11px]">{{ $toolCall['name'] ?? 'unknown' }}</span>
                                            </flux:badge>
                                        @endforeach
                                    </div>
                                @endif

                                <div @class([
                                    'w-fit max-w-full rounded-2xl rounded-tl-md border px-4 py-3 shadow-sm',
                                    'border-blue-100/80 bg-white/90 dark:border-white/10 dark:bg-white/5' => ! $isError,
                                    'border-red-200 bg-red-50/90 dark:border-red-400/30 dark:bg-red-500/10' => $isError,
                                ])>
                                    <article wire:ignore class="prism-chat-markdown" x-html="html"></article>
                                </div>

                                @unless ($isError)
                                    <div x-data="{ copied: false }" class="flex items-center gap-1 transition sm:opacity-0 sm:group-hover:opacity-100 sm:focus-within:opacity-100">
                                        <flux:button
                                            size="xs"
                                            variant="ghost"
                                            x-on:click="navigator.clipboard.writeText(streamData.text); copied = true; setTimeout(() => copied = false, 1600)"
                                        >
                                            <flux:icon.clipboard-document variant="micro" class="size-3.5" x-show="!copied" />
                                            <flux:icon.check variant="micro" class="size-3.5 text-emerald-500" x-show="copied" x-cloak />
                                            <span x-text="copied ? 'Kopiert' : 'Kopieren'">Kopieren</span>
                                        </flux:button>
                                    </div>
                                @endunless
                            </div>
                        </div>
                    @endif
                @endforeach

                @if ($isStreaming)
                    <div wire:key="prism-message-streaming" class="prism-chat-message flex items-start gap-3" x-data="markdownProcessor">
                        <div class="relative mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-linear-to-br from-blue-500 via-indigo-500 to-violet-500 text-white shadow-md shadow-indigo-500/25">
                            <span class="absolute inset-0 animate-ping rounded-lg bg-indigo-400/40 [animation-duration:2s]"></span>
                            <flux:icon.sparkles variant="micro" class="relative size-4" />
                        </div>

                        <div class="min-w-0 flex-1 space-y-2">
                            <span x-ref="raw" class="hidden" wire:stream="streamingData" wire:replace>{{ $streamingData ?: '{}' }}</span>

                            <div x-show="!streamData.text" class="flex h-8 items-center gap-2.5 text-sm">
                                <span class="flex items-center gap-1">
                                    <span class="size-1.5 animate-bounce rounded-full bg-indigo-500 [animation-delay:-0.3s] dark:bg-indigo-300"></span>
                                    <span class="size-1.5 animate-bounce rounded-full bg-indigo-500 [animation-delay:-0.15s] dark:bg-indigo-300"></span>
                                    <span class="size-1.5 animate-bounce rounded-full bg-indigo-500 dark:bg-indigo-300"></span>
                                </span>
                                <span class="prism-chat-shimmer font-medium" x-text="hasToolCalls() ? 'Nutzt App-Werkzeuge …' : (hasThinking() ? 'Denkt nach …' : 'Schreibt …')">Schreibt …</span>
                            </div>

                            @include('intranet-app-base::livewire.partials.prism-chat-thinking')

                            <div x-show="hasToolCalls()" x-cloak class="flex flex-wrap gap-1.5">
                                <template x-for="(toolCall, index) in streamData.toolCalls" :key="index">
                                    <flux:badge size="sm" color="sky" icon="wrench-screwdriver">
                                        <span class="font-mono text-[11px]" x-text="toolCall.name"></span>
                                    </flux:badge>
                                </template>
                            </div>

                            <div x-show="html.length > 0" x-cloak class="w-fit max-w-full rounded-2xl rounded-tl-md border border-blue-100/80 bg-white/90 px-4 py-3 shadow-sm dark:border-white/10 dark:bg-white/5">
                                <article wire:ignore class="prism-chat-markdown" x-html="html"></article>
                            </div>
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </div>

    <div class="relative h-0">
        <button
            type="button"
            x-show="!atBottom"
            x-cloak
            x-transition.opacity.scale.90
            x-on:click="scrollToBottom()"
            aria-label="Zum Ende springen"
            class="absolute -top-12 left-1/2 z-20 flex size-9 -translate-x-1/2 items-center justify-center rounded-full border border-blue-100 bg-white/95 text-slate-600 shadow-lg backdrop-blur transition hover:text-indigo-600 dark:border-white/10 dark:bg-slate-800/90 dark:text-slate-200 dark:hover:text-white"
        >
            <flux:icon.arrow-down variant="micro" class="size-4" />
        </button>
    </div>

    <div @class([
        'shrink-0',
        'px-2 pt-1 pb-2' => $embedded,
        'px-3 pt-2 pb-3 sm:px-5 sm:pb-4' => ! $embedded,
    ])>
        <form wire:submit="sendMessage" x-on:submit="atBottom = true" class="mx-auto w-full max-w-3xl">
            <flux:composer
                wire:model="prompt"
                submit="enter"
                :rows="$embedded ? 1 : 2"
                max-rows="8"
                :inline="$embedded"
                label="Nachricht"
                label:sr-only
                placeholder="Nachricht an den KI-Assistenten …"
                class="shadow-lg shadow-blue-950/5 transition focus-within:border-indigo-300 focus-within:ring-4 focus-within:ring-indigo-500/10 dark:focus-within:border-indigo-400/40"
            >
                <x-slot name="actionsLeading">
                    @if ($embedded && ! empty($messages))
                        <flux:tooltip content="Neuer Chat">
                            <flux:button size="sm" variant="subtle" icon="pencil-square" wire:click="resetChat" aria-label="Neuer Chat" />
                        </flux:tooltip>
                    @elseif (! $embedded)
                        <flux:text size="sm" class="flex items-center gap-1.5 px-1 pt-1.5 text-xs max-sm:hidden">
                            <flux:icon.sparkles variant="micro" class="size-3.5 text-indigo-500 dark:text-indigo-300" />
                            KI-Antworten können fehlerhaft sein.
                        </flux:text>
                    @endif
                </x-slot>

                <x-slot name="actionsTrailing">
                    <flux:button
                        type="submit"
                        size="sm"
                        variant="primary"
                        icon="arrow-up"
                        aria-label="Senden"
                        wire:loading.attr="disabled"
                        wire:target="sendMessage,sendSuggestion,streamResponse"
                    />
                </x-slot>
            </flux:composer>
            @unless ($embedded)
                <p class="mt-2 text-center text-[11px] text-slate-500 dark:text-slate-400">
                    <kbd class="rounded border border-slate-300/80 bg-white/70 px-1 font-sans dark:border-white/15 dark:bg-white/5">Enter</kbd> senden
                    · <kbd class="rounded border border-slate-300/80 bg-white/70 px-1 font-sans dark:border-white/15 dark:bg-white/5">Shift</kbd> + <kbd class="rounded border border-slate-300/80 bg-white/70 px-1 font-sans dark:border-white/15 dark:bg-white/5">Enter</kbd> neue Zeile
                </p>
            @endunless
        </form>
    </div>
</div>
