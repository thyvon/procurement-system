<?php

use App\Support\PrintPageFooter;
use Spatie\LaravelPdf\Support\DummyPdf;

it('counts every leaf page and skips the page-tree container', function () {
    $pdf = <<<'PDF'
%PDF-1.7
1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj
2 0 obj << /Type /Pages /Kids [3 0 R 4 0 R 5 0 R] /Count 3 >> endobj
3 0 obj << /Type /Page /Parent 2 0 R >> endobj
4 0 obj << /Type /Page /Parent 2 0 R >> endobj
5 0 obj << /Type /Page /Parent 2 0 R >> endobj
PDF;

    expect(PrintPageFooter::pageCount($pdf))->toBe(3);
});

it('reads the renderer dummy pdf as a single page', function () {
    expect(PrintPageFooter::pageCount(DummyPdf::content()))->toBe(1);
});

it('never reports fewer than one page', function () {
    expect(PrintPageFooter::pageCount('not a pdf'))->toBe(1);
});
