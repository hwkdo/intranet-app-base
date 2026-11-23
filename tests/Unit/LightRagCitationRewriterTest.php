<?php

declare(strict_types=1);

use Hwkdo\IntranetAppBase\Contracts\LightRagCitationLinker;
use Hwkdo\IntranetAppBase\Services\LightRagCitationRegistry;
use Hwkdo\IntranetAppBase\Services\LightRagCitationRewriter;

/**
 * @param  array<string, array{url: string, title: string}>  $resolved
 */
function citationRewriter(array $resolved): LightRagCitationRewriter
{
    app(LightRagCitationRegistry::class)->register(new class($resolved) implements LightRagCitationLinker
    {
        /**
         * @param  array<string, array{url: string, title: string}>  $resolved
         */
        public function __construct(private array $resolved) {}

        public function instance(): string
        {
            return 'arbeitsschutz';
        }

        public function pattern(): string
        {
            return '/(?<![\p{L}\p{N}_-])arbeitsschutz-\d+\.[A-Za-z0-9]{1,8}(?![\p{L}\p{N}_-])/iu';
        }

        public function resolveMany(array $fileNames): array
        {
            $matches = [];
            foreach ($fileNames as $fileName) {
                $key = strtolower(basename(str_replace('\\', '/', $fileName)));
                if (isset($this->resolved[$key])) {
                    $matches[$key] = $this->resolved[$key];
                }
            }

            return $matches;
        }
    });

    return app(LightRagCitationRewriter::class);
}

it('replaces light rag filenames in prose and inline code', function () {
    $rewriter = citationRewriter([
        'arbeitsschutz-41.pdf' => ['url' => 'https://intranet.test/open/41', 'title' => 'Brandschutz [Werkstatt]'],
        'arbeitsschutz-41.md' => ['url' => 'https://intranet.test/open/41', 'title' => 'Brandschutz [Werkstatt]'],
    ]);

    $linked = $rewriter->rewrite(
        'arbeitsschutz',
        'siehe arbeitsschutz-41.pdf und `arbeitsschutz-41.md`.',
    );

    expect($linked)->toBe(
        'siehe [Brandschutz \[Werkstatt\]](https://intranet.test/open/41) und [Brandschutz \[Werkstatt\]](https://intranet.test/open/41).',
    );
});

it('keeps unknown names, code fences and existing links', function () {
    $rewriter = citationRewriter([
        'arbeitsschutz-41.pdf' => ['url' => 'https://intranet.test/open/41', 'title' => 'Brandschutz'],
    ]);

    $markdown = "Unbekannt: arbeitsschutz-99.pdf\n\n```\narbeitsschutz-41.pdf\n```\n\n[arbeitsschutz-41.pdf](https://intranet.test/schon-da)";

    $linked = $rewriter->rewrite('arbeitsschutz', $markdown);

    expect($linked)
        ->toContain('Unbekannt: arbeitsschutz-99.pdf')
        ->toContain("```\narbeitsschutz-41.pdf\n```")
        ->toContain('[arbeitsschutz-41.pdf](https://intranet.test/schon-da)')
        ->not->toContain('[Brandschutz]');
});

it('leaves text unchanged for another light rag instance', function () {
    $rewriter = citationRewriter([
        'arbeitsschutz-41.pdf' => ['url' => 'https://intranet.test/open/41', 'title' => 'Brandschutz'],
    ]);

    expect($rewriter->rewrite('dokumente', 'siehe arbeitsschutz-41.pdf'))
        ->toBe('siehe arbeitsschutz-41.pdf');
});

it('turns reference filenames into unique document links', function () {
    $rewriter = citationRewriter([
        'arbeitsschutz-41.md' => ['url' => 'https://intranet.test/open/41', 'title' => 'Brandschutz'],
        'arbeitsschutz-28.md' => ['url' => 'https://intranet.test/open/28', 'title' => 'Erste Hilfe'],
    ]);

    $references = $rewriter->references('arbeitsschutz', [
        ['file_path' => 'input/arbeitsschutz-41.md'],
        ['file_path' => 'arbeitsschutz-41.pdf'],
        ['file_path' => 'arbeitsschutz-28.md'],
        ['file_path' => 'arbeitsschutz-999.md'],
    ]);

    expect($references)->toBe([
        ['file_path' => 'input/arbeitsschutz-41.md', 'url' => 'https://intranet.test/open/41', 'title' => 'Brandschutz'],
        ['file_path' => 'arbeitsschutz-28.md', 'url' => 'https://intranet.test/open/28', 'title' => 'Erste Hilfe'],
        ['file_path' => 'arbeitsschutz-999.md', 'url' => null, 'title' => 'arbeitsschutz-999.md'],
    ]);
});
