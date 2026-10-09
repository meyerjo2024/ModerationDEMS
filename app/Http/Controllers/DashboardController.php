<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        // no scheduler on the free host: run the daily reminder check the first time anyone opens the dashboard each day
        if (\Illuminate\Support\Facades\Cache::add('dems.deadlines.'.now('UTC')->toDateString(), 1, now('UTC')->endOfDay())) {
            try {
                app(\App\Services\DeadlineReminders::class)->run();
            } catch (\Throwable $e) {
                report($e);
            }
        }
        $items = Assessment::with(['subject', 'examiner', 'internalModerator'])
            ->visibleTo($user)->orderByDesc('updated_at')->get();

        return view('dashboard', ['items' => $items, 'user' => $user]);
    }
}
