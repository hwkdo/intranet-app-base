<?php

namespace Hwkdo\IntranetAppBase\Services;

class SseStreamParser
{
    private string $pending = '';

    /**
     * @return array<int, string>
     */
    public function push(string $chunk): array
    {
        $this->pending .= $chunk;

        $lines = explode("\n", $this->pending);
        if (! str_ends_with($this->pending, "\n")) {
            $this->pending = array_pop($lines) ?? '';
        } else {
            $this->pending = '';
        }

        $dataLines = [];

        foreach ($lines as $line) {
            $line = rtrim($line, "\r");

            if ($line === '' || ! str_starts_with($line, 'data:')) {
                continue;
            }

            $dataLines[] = ltrim(substr($line, 5));
        }

        return $dataLines;
    }
}
