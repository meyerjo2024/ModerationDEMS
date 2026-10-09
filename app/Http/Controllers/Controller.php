<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use Illuminate\Http\Request;

abstract class Controller
{
    /** @return array{ip: ?string, ua: ?string} */
    protected function meta(Request $r): array
    {
        return ['ip' => $r->ip(), 'ua' => $r->userAgent()];
    }

    /** @return array{image: ?string, password: ?string} */
    protected function signature(Request $r): array
    {
        return ['image' => $r->input('signature.image'), 'password' => $r->input('signature.password')];
    }

    /** 404 (not 403) when the user may not see the record, so existence isn't leaked. */
    protected function visible(Request $r, Assessment $a): Assessment
    {
        $a->loadMissing('subject');
        abort_unless($a->isVisibleTo($r->user()), 404);

        return $a;
    }
}
