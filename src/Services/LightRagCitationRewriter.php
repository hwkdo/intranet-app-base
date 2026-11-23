<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Services;

use Hwkdo\IntranetAppBase\Contracts\LightRagCitationLinker;

class LightRagCitationRewriter
{
    /**
     * @var array<string, array<string, array{url: string, title: string}|null>>
     */
    private array $resolved = [];

    public function __construct(private readonly LightRagCitationRegistry $registry) {}

    public function rewrite(?string $instance, string $markdown): string
    {
        $linker = $this->linker($instance);
        if ($linker === null || $markdown === '') {
            return $markdown;
        }

        $parts = preg_split('/(```[\s\S]*?```|`[^`\n]*`)/', $markdown, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $markdown;
        }

        $rewritten = '';
        foreach ($parts as $part) {
            if (str_starts_with($part, '```')) {
                $rewritten .= $part;

                continue;
            }

            if (preg_match('/^`([^`\n]*)`$/', $part, $code) === 1) {
                $linked = $this->replaceSegment($instance, $linker, $code[1]);
                $rewritten .= $linked === $code[1] ? $part : $linked;

                continue;
            }

            $rewritten .= $this->replaceSegment($instance, $linker, $part);
        }

        return $rewritten;
    }

    /**
     * @param  list<array{file_path?: string}>  $references
     * @return list<array{file_path: string, url: string|null, title: string}>
     */
    public function references(?string $instance, array $references): array
    {
        $fileNames = [];
        foreach ($references as $reference) {
            $filePath = $reference['file_path'] ?? null;
            if (is_string($filePath) && $filePath !== '') {
                $fileNames[] = $filePath;
            }
        }

        $linker = $this->linker($instance);
        $resolved = $linker === null ? [] : $this->resolve($instance, $linker, $fileNames);

        $linked = [];
        $seen = [];
        foreach ($fileNames as $fileName) {
            $hit = $resolved[$this->key($fileName)] ?? null;
            $url = is_array($hit) ? $hit['url'] : null;
            $title = is_array($hit) && trim($hit['title']) !== '' ? trim($hit['title']) : $this->key($fileName);
            $dedupeKey = $url ?? $this->key($fileName);
            if (isset($seen[$dedupeKey])) {
                continue;
            }

            $seen[$dedupeKey] = true;
            $linked[] = [
                'file_path' => $fileName,
                'url' => $url,
                'title' => $title,
            ];
        }

        return $linked;
    }

    private function linker(?string $instance): ?LightRagCitationLinker
    {
        if ($instance === null || trim($instance) === '') {
            return null;
        }

        return $this->registry->for($instance);
    }

    private function replaceSegment(?string $instance, LightRagCitationLinker $linker, string $segment): string
    {
        $matchCount = preg_match_all($linker->pattern(), $segment, $matches, PREG_OFFSET_CAPTURE);
        if ($segment === '' || $matchCount === false || $matchCount === 0) {
            return $segment;
        }

        $occurrences = [];
        foreach ($matches[0] as [$name, $offset]) {
            if (! is_string($name) || ! is_int($offset) || $this->insideMarkdownLink($segment, $offset, strlen($name))) {
                continue;
            }

            $occurrences[] = ['name' => $name, 'offset' => $offset];
        }

        if ($occurrences === []) {
            return $segment;
        }

        $resolved = $this->resolve($instance, $linker, array_column($occurrences, 'name'));
        usort($occurrences, fn (array $left, array $right): int => $right['offset'] <=> $left['offset']);

        foreach ($occurrences as $occurrence) {
            $hit = $resolved[$this->key($occurrence['name'])] ?? null;
            if (! is_array($hit) || $hit['url'] === '') {
                continue;
            }

            $segment = substr_replace(
                $segment,
                $this->markdownLink($hit['title'], $hit['url'], $occurrence['name']),
                $occurrence['offset'],
                strlen($occurrence['name']),
            );
        }

        return $segment;
    }

    /**
     * @param  list<string>  $fileNames
     * @return array<string, array{url: string, title: string}>
     */
    private function resolve(?string $instance, LightRagCitationLinker $linker, array $fileNames): array
    {
        if ($instance === null) {
            return [];
        }

        $this->resolved[$instance] ??= [];
        $missing = [];
        foreach ($fileNames as $fileName) {
            $key = $this->key($fileName);
            if ($key === '' || array_key_exists($key, $this->resolved[$instance])) {
                continue;
            }

            $missing[$key] = $fileName;
        }

        if ($missing !== []) {
            $found = $linker->resolveMany(array_values($missing));
            foreach ($missing as $key => $fileName) {
                $hit = $found[$key] ?? null;
                $this->resolved[$instance][$key] = is_array($hit) && is_string($hit['url'] ?? null)
                    ? ['url' => $hit['url'], 'title' => is_string($hit['title'] ?? null) ? $hit['title'] : '']
                    : null;
            }
        }

        $result = [];
        foreach ($fileNames as $fileName) {
            $hit = $this->resolved[$instance][$this->key($fileName)] ?? null;
            if (is_array($hit)) {
                $result[$this->key($fileName)] = $hit;
            }
        }

        return $result;
    }

    private function insideMarkdownLink(string $text, int $offset, int $length): bool
    {
        $before = substr($text, 0, $offset);
        $after = substr($text, $offset + $length);

        if (preg_match('/\[[^\]\n]*$/', $before) === 1 && preg_match('/^[^\[\n]*\]\(/', $after) === 1) {
            return true;
        }

        return preg_match('/\]\([^)\n]*$/', $before) === 1 && preg_match('/^[^)\n]*\)/', $after) === 1;
    }

    private function markdownLink(string $title, string $url, string $fallback): string
    {
        $label = trim($title) !== '' ? trim($title) : $fallback;
        $label = str_replace(['[', ']'], ['\\[', '\\]'], preg_replace('/\s+/', ' ', $label) ?? $label);
        $url = str_replace([' ', ')'], ['%20', '%29'], $url);

        return '['.$label.']('.$url.')';
    }

    private function key(string $fileName): string
    {
        return strtolower(basename(str_replace('\\', '/', trim($fileName))));
    }
}
