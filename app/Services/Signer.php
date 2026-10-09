<?php

namespace App\Services;

use App\Enums\SignatureSection;
use App\Exceptions\WorkflowException;
use App\Models\Assessment;
use App\Models\Signature;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/** Pen signature + password re-confirmation ("authenticated signature token"). */
class Signer
{
    private const PNG = 'data:image/png;base64,';

    public function decode(?string $dataUrl): string
    {
        if (! $dataUrl || ! str_starts_with($dataUrl, self::PNG)) {
            throw new WorkflowException('Please draw your signature in the signature pad.');
        }
        $bin = base64_decode(substr($dataUrl, strlen(self::PNG)), true);
        if ($bin === false || strlen($bin) < 8 || ! str_starts_with($bin, "\x89PNG")) {
            throw new WorkflowException('The signature image is not valid.');
        }
        if (strlen($bin) < 600) {
            throw new WorkflowException('Your signature looks empty. Please sign again.');
        }

        return $bin;
    }

    /** Must be called before a signature is recorded. @param array{image?: ?string, password?: ?string} $sig */
    public function confirm(User $user, array $sig): void
    {
        $this->decode($sig['image'] ?? null);
        if (blank($sig['password'] ?? null)) {
            throw new WorkflowException('Enter your password to sign.');
        }
        $key = 'sign:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 6)) {
            throw new WorkflowException('Too many incorrect passwords. Please wait a few minutes and try again.', 429);
        }
        if (! Hash::check($sig['password'], $user->password)) {
            RateLimiter::hit($key, 900);
            throw WorkflowException::forbidden('That password is not correct, so the signature was not applied.');
        }
        RateLimiter::clear($key);
    }

    /** @param array{ip: ?string, ua: ?string} $meta */
    public function record(Assessment $a, User $user, SignatureSection $section, string $image, string $contentHash, array $meta): Signature
    {
        return Signature::create([
            'assessment_id' => $a->id,
            'user_id' => $user->id,
            'section' => $section,
            'image_data' => $image,
            'content_hash' => $contentHash,
            'ip_address' => $meta['ip'] ?? null,
            'user_agent' => isset($meta['ua']) ? mb_substr($meta['ua'], 0, 300) : null,
            'signed_at' => now('UTC'),
        ]);
    }
}
