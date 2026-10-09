<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $items = Notification::where('user_id', $request->user()->id)->orderByDesc('id')->limit(20)->get()
            ->map(fn ($n) => ['id' => $n->id, 'message' => $n->message, 'read' => $n->read, 'at' => $n->created_at->utc()->format('j M Y, H:i').' UTC',
                'url' => $n->assessment_id ? route('assessments.show', $n->assessment_id) : url('/')]);

        return response()->json(['notifications' => $items, 'unread' => $items->where('read', false)->count()]);
    }

    public function markRead(Request $request)
    {
        Notification::where('user_id', $request->user()->id)->where('read', false)->update(['read' => true]);

        return response()->json(['ok' => true]);
    }
}
