<?php

namespace App\Http\Controllers;

use App\Enums\AttachmentKind;
use App\Models\Attachment;
use App\Services\Audit;
use App\Services\FileStore;
use Illuminate\Http\Request;

class FileController extends Controller
{
    /** Authorised download / inline preview of an attachment. */
    public function show(Request $request, Attachment $attachment, FileStore $files, Audit $audit)
    {
        $a = $this->visible($request, $attachment->assessment);
        $user = $request->user();
        // The raw marks workbook may carry student identifiers — examiner only.
        abort_if($attachment->kind === AttachmentKind::Marks && $a->examiner_id !== $user->id, 403);

        $inline = $request->boolean('inline') && $attachment->isPdf();
        $data = $files->get($attachment->storage_key);
        if ($a->examiner_id !== $user->id && $attachment->kind !== AttachmentKind::FinalReport) {
            $audit->log('FILE_VIEWED', $a->id, $user->id, ['filename' => $attachment->filename, 'kind' => $attachment->kind->value], $request->ip());
        }

        $headers = [
            'Content-Type' => $attachment->mime_type,
            'Content-Length' => (string) strlen($data),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ];
        // `sandbox` would stop the browser's built-in PDF viewer, so it only applies to downloads.
        if (! $inline) {
            $headers['Content-Security-Policy'] = 'sandbox';
        }

        return response($data, 200, $headers)->header(
            'Content-Disposition',
            ($inline ? 'inline' : 'attachment')."; filename*=UTF-8''".rawurlencode($attachment->filename),
        );
    }
}
