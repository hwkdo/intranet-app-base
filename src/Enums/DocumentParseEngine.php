<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Enums;

enum DocumentParseEngine: string
{
    case LlamaParse = 'llama-parse';
    case PdfToText = 'pdf-to-text';
    case Vision = 'vision';
    case HwkAdmin = 'hwk-admin';

    public function label(): string
    {
        return match ($this) {
            self::LlamaParse => 'LlamaParse',
            self::PdfToText => 'pdftotext (lokal)',
            self::Vision => 'Vision-OCR',
            self::HwkAdmin => 'HWK Admin',
        };
    }
}
