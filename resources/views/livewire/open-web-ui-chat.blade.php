<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use function Livewire\Volt\{state, mount};

state([
    'messages' => [],
    'prompt' => '',
    'streamingAnswer' => '',
    'isStreaming' => false,
    'model' => null,
    'apiKey' => null,
    'endpoint' => null,
]);

mount(function ($model = null, $apiKey = null, $endpoint = null) {
    $this->model = $model;
    $this->apiKey = $apiKey;
    $this->endpoint = $endpoint;
});

$sendMessage = function () {
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

    // Reset streaming answer
    $this->streamingAnswer = '';
    $this->isStreaming = true;

    // Trigger streaming
    $this->js('$wire.streamResponse()');
};

$streamResponse = function () {
    if (empty($this->apiKey) || empty($this->model) || empty($this->endpoint)) {
        $this->isStreaming = false;
        return;
    }

    // Build messages array for API
    $apiMessages = array_map(function ($msg) {
        return [
            'role' => $msg['role'],
            'content' => $msg['content'],
        ];
    }, $this->messages);

    try {
        $client = new Client([
            'timeout' => 120,
            'stream' => true,
        ]);

        $response = $client->post($this->endpoint, [
            'headers' => [
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'text/event-stream',
            ],
            'json' => [
                'model' => $this->model,
                'messages' => $apiMessages,
                'stream' => true,
            ],
        ]);

        $buffer = '';
        $parser = app()->make('Hwkdo\\IntranetAppBase\\Services\\SseStreamParser');
        $stream = $response->getBody();

        while (! $stream->eof()) {
            $chunk = $stream->read(1024);

            if (empty($chunk)) {
                continue;
            }

            foreach ($parser->push($chunk) as $data) {
                if ($data === '[DONE]') {
                    break 2;
                }

                $json = json_decode($data, true);

                if (! is_array($json)) {
                    continue;
                }

                // Extract content from OpenWebUI response format
                $content = $json['choices'][0]['delta']['content'] ?? null;

                if ($content) {
                    $buffer .= $content;
                    // Stream raw markdown text (don't render during streaming to avoid character loss)
                    $this->stream(to: 'streamingAnswer', content: $buffer, replace: true);
                }
            }
        }

        // Add complete answer to messages
        if (! empty($buffer)) {
            $this->messages[] = [
                'role' => 'assistant',
                'content' => $buffer,
            ];
        }

        $this->streamingAnswer = '';
        $this->isStreaming = false;
    } catch (RequestException $e) {
        $this->isStreaming = false;
        $errorMessage = 'Fehler: ';
        if ($e->hasResponse()) {
            $errorMessage .= $e->getResponse()->getStatusCode().' - '.$e->getResponse()->getBody()->getContents();
        } else {
            $errorMessage .= $e->getMessage();
        }
        $this->streamingAnswer = $errorMessage;
    } catch (\Exception $e) {
        $this->isStreaming = false;
        $this->streamingAnswer = 'Fehler: '.$e->getMessage();
    }
};

?>

<div class="flex flex-col h-full w-full">
    <flux:card class="flex-1 flex flex-col min-h-0">
        <div class="flex-1 overflow-y-auto p-4 space-y-4 min-h-0" id="chat-messages">
            @if (empty($messages) && !$isStreaming)
                <div class="flex items-center justify-center h-full text-zinc-500 dark:text-zinc-400">
                    <flux:text>Stellen Sie eine Frage, um zu beginnen...</flux:text>
                </div>
            @else
                @foreach ($messages as $message)
                    <div class="flex gap-3 {{ $message['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-[80%] rounded-lg p-3 {{ $message['role'] === 'user' ? 'bg-primary-500 text-white' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100' }}">
                            @if ($message['role'] === 'user')
                                <flux:text class="whitespace-pre-wrap">{{ $message['content'] }}</flux:text>
                            @else
                                <x-markdown class="text-sm">
                                    {{ $message['content'] }}
                                </x-markdown>
                            @endif
                        </div>
                    </div>
                @endforeach

                @if ($isStreaming)
                    <div class="flex gap-3 justify-start">
                        <div class="max-w-[80%] rounded-lg p-3 bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100">
                            <div class="text-sm" wire:stream.replace="streamingAnswer">
                                @if (empty($streamingAnswer))
                                    <div class="flex items-center gap-2 text-zinc-500 dark:text-zinc-400">
                                        <flux:icon.loading class="size-4 animate-spin" />
                                        <flux:text>Antwort wird geladen...</flux:text>
                                    </div>
                                @else
                                    @php
                                        $rendered = app(\Spatie\LaravelMarkdown\MarkdownRenderer::class)->toHtml($streamingAnswer);
                                    @endphp
                                    {!! $rendered !!}
                                    <span class="inline-block w-2 h-4 bg-zinc-500 dark:bg-zinc-400 animate-pulse">|</span>
                                @endif
                            </div>
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
        
        Livewire.hook('commit', ({ component, commit, respond, succeed, fail }) => {
            succeed(({ snapshot, effect }) => {
                if (container) {
                    container.scrollTop = container.scrollHeight;
                }
            });
        });
    });
</script>
