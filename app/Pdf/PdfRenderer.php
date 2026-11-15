<?php

namespace App\Pdf;

use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * Turns a Blade view into a PDF with mPDF, the library chosen after the Arabic
 * test (see doc/phases/04-exports-and-documents.md, 4.3).
 *
 * Two rules the templates follow:
 *  - A phone number, an ID or any run of digits next to Arabic text goes inside
 *    `<bdi dir="ltr">...</bdi>`, or its groups come out reversed.
 *  - Layout is tables and blocks. mPDF does not do flex or grid.
 *
 * Arabic shaping and fonts need `autoScriptToLang` and `autoLangToFont`.
 */
class PdfRenderer
{
    /**
     * @param  view-string  $view
     * @param  array<string, mixed>  $data
     */
    public function render(string $view, array $data, string $title): string
    {
        $directory = storage_path('framework/mpdf');

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $directory,
            'margin_top' => 18,
            'margin_bottom' => 20,
            'margin_left' => 16,
            'margin_right' => 16,
            'default_font_size' => 10,
        ]);

        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;
        $mpdf->SetTitle($title);
        $mpdf->SetCreator(config('app.name'));
        $mpdf->SetAuthor(config('app.name'));
        $mpdf->SetHTMLFooter(view('pdf.footer', ['app' => config('app.name')])->render());
        $mpdf->WriteHTML(view($view, [...$data, 'title' => $title, 'app' => config('app.name')])->render());

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    /**
     * @param  view-string  $view
     * @param  array<string, mixed>  $data
     */
    public function response(string $view, array $data, string $title, string $filename): Response
    {
        return response($this->render($view, $data, $title), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** A safe ASCII file name part. Arabic names slug to nothing, so a fallback is used. */
    public static function slug(string $name, string $fallback): string
    {
        $slug = Str::slug($name);

        return $slug === '' ? $fallback : Str::limit($slug, 40, '');
    }
}
