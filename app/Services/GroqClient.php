<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Minimal client for Groq's OpenAI-compatible chat API. */
class GroqClient
{
    public function configured(): bool
    {
        return (string) config('dems.groq.key') !== '';
    }

    /** @param list<array{role: string, content: string}> $messages */
    public function chat(array $messages): string
    {
        if (! $this->configured()) {
            throw new RuntimeException('The AI writer is not set up yet: add GROQ_API_KEY in Render → Environment and redeploy.');
        }
        $res = Http::withToken((string) config('dems.groq.key'))->acceptJson()->timeout((int) config('dems.groq.timeout', 60))
            ->post((string) config('dems.groq.url'), [
                'model' => config('dems.groq.model'), 'messages' => $messages, 'temperature' => 0.3, 'max_tokens' => 2200,
                'response_format' => ['type' => 'json_object'],
            ]);
        if ($res->status() === 401 || $res->status() === 403) {
            throw new RuntimeException('Groq refused the key (check GROQ_API_KEY).');
        }
        if ($res->status() === 429) {
            throw new RuntimeException('Groq is busy or the free limit was reached. Try again in a minute.');
        }
        if (! $res->successful()) {
            throw new RuntimeException('Groq could not write the narrative (HTTP '.$res->status().'): '.\Illuminate\Support\Str::limit((string) ($res->json('error.message') ?? $res->body()), 200));
        }

        return (string) $res->json('choices.0.message.content');
    }
}
