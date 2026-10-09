<?php

namespace App\Http\Controllers;

use App\Enums\AssessmentStatus as S;
use App\Enums\AttachmentKind;
use App\Models\Assessment;
use App\Models\Attachment;
use App\Models\DocumentComment;
use App\Services\Audit;
use Illuminate\Http\Request;

/** Reviewer comments on the examiner's Word documents (paper / memorandum). */
class DocumentCommentController extends Controller
{
    private const KINDS = [AttachmentKind::Paper, AttachmentKind::Memo];

    public function index(Request $request, Attachment $attachment)
    {
        $a = $this->scope($request, $attachment);
        // every version of the same document, so comments survive a re-upload
        $ids = Attachment::where('assessment_id', $a->id)->where('kind', $attachment->kind->value)->pluck('id');
        $comments = DocumentComment::with('author:id,name')->where('assessment_id', $a->id)->whereIn('attachment_id', $ids)->orderBy('created_at')->get();

        return response()->json([
            'comments' => $comments->map(fn ($c) => $this->row($c, $attachment, $request))->values(),
            'can' => ['comment' => $this->canComment($request, $a), 'address' => $this->canAddress($request, $a)],
        ]);
    }

    public function store(Request $request, Attachment $attachment, Audit $audit)
    {
        $a = $this->scope($request, $attachment);
        abort_unless($this->canComment($request, $a), 403, 'You cannot comment on this document at this stage.');
        $d = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:3000'],
            'quote' => ['nullable', 'string', 'max:600'],
            'start_offset' => ['nullable', 'integer', 'min:0', 'max:5000000'],
        ]);
        $c = DocumentComment::create([
            'assessment_id' => $a->id, 'attachment_id' => $attachment->id, 'user_id' => $request->user()->id,
            'body' => trim($d['body']), 'quote' => $d['quote'] ?? null, 'start_offset' => $d['start_offset'] ?? null,
        ]);
        $audit->log('DOCUMENT_COMMENTED', $a->id, $request->user()->id, ['filename' => $attachment->filename], $request->ip());

        return response()->json($this->row($c->load('author:id,name'), $attachment, $request), 201);
    }

    public function destroy(Request $request, DocumentComment $comment)
    {
        $a = $this->visible($request, Assessment::findOrFail($comment->assessment_id));
        abort_unless($comment->user_id === $request->user()->id && $this->canComment($request, $a), 403);
        $comment->delete();

        return response()->json(['ok' => true]);
    }

    /** The examiner marks a comment as dealt with, optionally with a short reply. */
    public function address(Request $request, DocumentComment $comment)
    {
        $a = $this->visible($request, Assessment::findOrFail($comment->assessment_id));
        abort_unless($this->canAddress($request, $a), 403);
        $d = $request->validate(['addressed' => ['required', 'boolean'], 'reply' => ['nullable', 'string', 'max:1000']]);
        $comment->update(['addressed' => $d['addressed'], 'reply' => $d['reply'] ?? $comment->reply]);

        return response()->json(['ok' => true, 'addressed' => $comment->addressed, 'reply' => $comment->reply]);
    }

    private function scope(Request $request, Attachment $attachment): Assessment
    {
        abort_unless(in_array($attachment->kind, self::KINDS, true), 404);

        return $this->visible($request, $attachment->assessment);
    }

    private function canComment(Request $request, Assessment $a): bool
    {
        $u = $request->user()->id;

        return match ($a->status) {
            S::PendingPreModeration, S::PendingFinalModeration => $a->internal_moderator_id === $u,
            S::PendingExternalModeration => $a->external_moderator_id === $u,
            default => false,
        };
    }

    private function canAddress(Request $request, Assessment $a): bool
    {
        return $a->examiner_id === $request->user()->id && in_array($a->status, [S::Draft, S::RevisionRequested], true);
    }

    private function row(DocumentComment $c, Attachment $current, Request $request): array
    {
        return [
            'id' => $c->id, 'author' => $c->author->name, 'mine' => $c->user_id === $request->user()->id,
            'body' => $c->body, 'quote' => $c->quote, 'offset' => $c->start_offset, 'addressed' => $c->addressed, 'reply' => $c->reply,
            'current' => $c->attachment_id === $current->id, 'at' => $c->created_at->utc()->format('j M Y, H:i'),
        ];
    }
}
