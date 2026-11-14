<?php

namespace App\Actions\Contracts;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreContractAttachment
{
    /**
     * @return array{attachment_path: string, attachment_name: string, attachment_mime: string}
     */
    public function handle(UploadedFile $file): array
    {
        $path = Storage::disk('local')->putFile('contracts', $file);

        if ($path === false) {
            throw ValidationException::withMessages([
                'attachment' => __('The attachment could not be stored.'),
            ]);
        }

        return [
            'attachment_path' => $path,
            'attachment_name' => Str::limit(basename($file->getClientOriginalName()), 240, ''),
            'attachment_mime' => $file->getMimeType() ?? 'application/octet-stream',
        ];
    }
}
