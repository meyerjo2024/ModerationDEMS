<?php

namespace App\Services;

use App\Models\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/** Sends an in-app notification to the person's iPhones through Apple Push Notification service (APNs). */
class PushSender
{
    public function enabled(): bool
    {
        $c = config('dems.apns');

        return $c['key_id'] && $c['team_id'] && $c['bundle_id'] && $c['key'];
    }

    public function notify(Notification $n): void
    {
        if (! $this->enabled()) {
            return;
        }
        $tokens = DB::table('device_tokens')->where('user_id', $n->user_id)->pluck('token');
        if ($tokens->isEmpty()) {
            return;
        }
        try {
            $badge = Notification::where('user_id', $n->user_id)->where('read', false)->count();
            $payload = ['aps' => ['alert' => ['title' => 'Moderation', 'body' => $n->message], 'sound' => 'default', 'badge' => $badge],
                'url' => $n->assessment_id ? '/assessments/'.$n->assessment_id : '/'];
            $host = config('dems.apns.production') ? 'api.push.apple.com' : 'api.sandbox.push.apple.com';
            foreach ($tokens as $token) {
                $r = Http::withHeaders(['authorization' => 'bearer '.$this->jwt(), 'apns-topic' => config('dems.apns.bundle_id'), 'apns-push-type' => 'alert', 'apns-priority' => '10'])
                    ->withOptions(['version' => 2.0])->timeout(4)->post("https://{$host}/3/device/{$token}", $payload);
                // the phone no longer wants these (app deleted, token replaced)
                if ($r->status() === 410 || in_array($r->json('reason'), ['BadDeviceToken', 'Unregistered', 'DeviceTokenNotForTopic'], true)) {
                    DB::table('device_tokens')->where('token', $token)->delete();
                }
            }
        } catch (Throwable $e) {
            report($e); // a push problem must never break the workflow
        }
    }

    /** Provider authentication token (ES256), reused for 50 minutes as Apple recommends. */
    public function jwt(): string
    {
        return Cache::remember('dems.apns.jwt.'.md5(config('dems.apns.key_id').config('dems.apns.team_id')), 3000, function () {
            $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
            $head = $b64(json_encode(['alg' => 'ES256', 'kid' => config('dems.apns.key_id')]));
            $claims = $b64(json_encode(['iss' => config('dems.apns.team_id'), 'iat' => time()]));
            $key = openssl_pkey_get_private(str_replace('\\n', "\n", (string) config('dems.apns.key')));
            openssl_sign("{$head}.{$claims}", $der, $key, OPENSSL_ALGO_SHA256);

            return "{$head}.{$claims}.".$b64($this->derToRaw($der));
        });
    }

    /** ECDSA signatures come out as ASN.1 DER; a JWT wants the 64 raw bytes R||S. */
    private function derToRaw(string $der): string
    {
        $pos = 3; // 30 len 02
        $rLen = ord($der[$pos]);
        $r = substr($der, $pos + 1, $rLen);
        $pos += 1 + $rLen + 1; // skip 02
        $sLen = ord($der[$pos]);
        $s = substr($der, $pos + 1, $sLen);
        $fix = fn (string $x) => str_pad(ltrim($x, "\0"), 32, "\0", STR_PAD_LEFT);

        return $fix($r).$fix($s);
    }
}
