<?php

use App\Pdf\PdfRenderer;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

uses(TestCase::class);

test('a PDF temp folder that cannot be created says what to fix', function () {
    $file = tempnam(sys_get_temp_dir(), 'khazna');

    try {
        // A folder cannot be made inside a file.
        $renderer = new PdfRenderer($file.'/mpdf');

        expect(fn () => $renderer->render('pdf.footer', ['app' => 'Khazna'], 'Test'))
            ->toThrow(RuntimeException::class, 'cannot be created');
    } finally {
        @unlink($file);
    }
});

test('a PDF temp folder that exists but is not writable says what to fix', function () {
    $directory = sys_get_temp_dir().'/khazna-readonly-'.uniqid();
    mkdir($directory, 0555);

    try {
        if (is_writable($directory)) {
            $this->markTestSkipped('The filesystem user can write to read-only folders (running as root).');
        }

        $renderer = new PdfRenderer($directory);

        expect(fn () => $renderer->render('pdf.footer', ['app' => 'Khazna'], 'Test'))
            ->toThrow(RuntimeException::class, 'not writable');
    } finally {
        @rmdir($directory);
    }
});

test('the renderer makes a missing temp folder on first use', function () {
    $directory = sys_get_temp_dir().'/khazna-new-'.uniqid().'/mpdf';

    try {
        $pdf = (new PdfRenderer($directory))->render('pdf.footer', ['app' => 'Khazna'], 'Test');

        expect(is_dir($directory))->toBeTrue()
            ->and(substr($pdf, 0, 4))->toBe('%PDF');
    } finally {
        File::deleteDirectory(dirname($directory));
    }
});
