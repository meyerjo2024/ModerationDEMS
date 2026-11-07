<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** The phone app tells the server where to send push notifications for the signed-in person. */
class DeviceController extends Controller
{
    public function store(Request $request)
    {
        $d = $request->validate(['token' => ['required', 'string', 'min:20', 'max:255'], 'platform' => ['nullable', 'in:ios']]);
        // a token belongs to whoever signed in on that phone last
        DB::table('device_tokens')->updateOrInsert(['token' => $d['token']], ['user_id' => $request->user()->id, 'platform' => $d['platform'] ?? 'ios', 'last_seen_at' => now('UTC'), 'updated_at' => now('UTC'), 'created_at' => now('UTC')]);

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request)
    {
        $d = $request->validate(['token' => ['required', 'string', 'max:255']]);
        DB::table('device_tokens')->where('user_id', $request->user()->id)->where('token', $d['token'])->delete();

        return response()->json(['ok' => true]);
    }
}
