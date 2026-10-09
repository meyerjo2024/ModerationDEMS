<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $items = Assessment::with(['subject', 'examiner', 'internalModerator'])
            ->visibleTo($user)->orderByDesc('updated_at')->get();

        return view('dashboard', ['items' => $items, 'user' => $user]);
    }
}
