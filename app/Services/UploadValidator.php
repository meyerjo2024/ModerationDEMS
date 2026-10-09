<?php

namespace App\Services;

use App\Enums\AttachmentKind;
use App\Exceptions\WorkflowException;

/** Validates extension against the slot and checks the file's magic bytes. */
class UploadValidator
{
    private const TYPES = [
        'pdf' => ['ext' => ['pdf'], 'mime' => 'application/pdf'],
        'docx' => ['ext' => ['docx'], 'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'doc' => ['ext' => ['doc'], 'mime' => 'application/msword'],
        'xls' => ['ext' => ['xls'], 'mime' => 'application/vnd.ms-excel'],
        'xlsx' => ['ext' => ['xlsx'], 'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'csv' => ['ext' => ['csv'], 'mime' => 'text/csv'],
        'png' => ['ext' => ['png'], 'mime' => 'image/png'],
        'jpg' => ['ext' => ['jpg', 'jpeg'], 'mime' => 'image/jpeg'],
    ];

    /** @return array{mime: string, filename: string} */
    public function validate(AttachmentKind $kind, string $filename, string $data): array
    {
        $max = config('dems.max_upload_mb') * 1048576;
        if ($data === '') {
            throw new WorkflowException('That file is empty.');
        }
        if (strlen($data) > $max) {
            throw new WorkflowException('Files are limited to '.config('dems.max_upload_mb').' MB.');
        }
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $key = collect($kind->allowedTypes())->first(fn ($k) => in_array($ext, self::TYPES[$k]['ext'], true));
        if (! $key) {
            throw new WorkflowException('Allowed file types here: '.collect($kind->allowedTypes())->map(fn ($k) => '.'.self::TYPES[$k]['ext'][0])->implode(', ').'.');
        }
        if (! $this->magicOk($key, $data)) {
            throw new WorkflowException('The file contents do not match its extension.');
        }
        $safe = mb_substr(preg_replace('/[^\w.\- ()]+/u', '_', $filename), -120);

        return ['mime' => self::TYPES[$key]['mime'], 'filename' => $safe];
    }

    /** A password-protected .docx is an OLE container holding an "EncryptedPackage" stream. */
    private function isEncryptedOffice(string $d): bool
    {
        return str_starts_with($d, "\xD0\xCF\x11\xE0") && str_contains($d, "E\0n\0c\0r\0y\0p\0t\0e\0d\0P\0a\0c\0k\0a\0g\0e\0");
    }

    private function magicOk(string $key, string $d): bool
    {
        return match ($key) {
            'pdf' => str_starts_with($d, '%PDF'),
            'docx' => str_starts_with($d, "PK\x03\x04") || $this->isEncryptedOffice($d),
            'xlsx' => str_starts_with($d, "PK\x03\x04"),
            'doc', 'xls' => str_starts_with($d, "\xD0\xCF\x11\xE0"),
            'png' => str_starts_with($d, "\x89PNG"),
            'jpg' => str_starts_with($d, "\xFF\xD8\xFF"),
            'csv' => ! str_contains(substr($d, 0, 4096), "\0"),
        };
    }
}
