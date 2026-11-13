<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Minimal client for Groq's OpenAI-compatible chat API. Finds a chat model your key can use if the configured one is not available. */
class GroqClient
{
    /** Best first. Anything not listed is tried after these, in alphabetical order. */
    private const PREFERRED = ['llama-3.3-70b-versatile', 'openai/gpt-oss-120b', 'openai/gpt-oss-20b', 'qwen/qwen3-32b', 'moonshotai/kimi-k2-instruct', 'meta-llama/llama-4-scout-17b-16e-instruct', 'llama-3.1-8b-instant'];

    /** Models that are not general chat models. */
    private const SKIP = ['whisper', 'guard', 'tts', 'playai', 'orpheus', 'embed', 'compound', 'safeguard'];

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
        $model = Cache::get($this->cacheKey()) ?: (string) config('dems.groq.model');
        $res = $this->send($model, $messages, true);

        if ($res->status() === 400 && str_contains(mb_strtolower((string) $res->body()), 'response_format')) { // this model has no JSON mode
            $res = $this->send($model, $messages, false);
        }
        if ($res->status() === 404 || ($res->status() === 400 && str_contains(mb_strtolower((string) $res->json('error.code')), 'model'))) {
            $alt = $this->pick($model);
            $model = $alt;
            $res = $this->send($model, $messages, true);
            if ($res->status() === 400 && str_contains(mb_strtolower((string) $res->body()), 'response_format')) {
                $res = $this->send($model, $messages, false);
            }
            if ($res->successful()) {
                Cache::put($this->cacheKey(), $model, now()->addDay());
            }
        }

        return $this->text($res);
    }

    /** Chat models this key can use. @return list<string> */
    public function models(): array
    {
        $res = Http::withToken((string) config('dems.groq.key'))->acceptJson()->timeout(20)->get($this->base().'/models');
        if (! $res->successful()) {
            throw new RuntimeException('Groq could not list the models for this key (HTTP '.$res->status().').');
        }
        $ids = collect($res->json('data', []))->pluck('id')->filter(fn ($id) => is_string($id) && ! collect(self::SKIP)->contains(fn ($s) => str_contains(mb_strtolower($id), $s)))->unique()->values();
        $rank = fn ($id) => ($i = array_search($id, self::PREFERRED, true)) === false ? 100 : $i;

        return $ids->sortBy(fn ($id) => sprintf('%03d %s', $rank($id), $id))->values()->all();
    }

    private function pick(string $failed): string
    {
        $models = array_values(array_diff($this->models(), [$failed]));
        if (! $models) {
            throw new RuntimeException("The model “{$failed}” is not available to this Groq key and no other chat model was found. Check the key's access at console.groq.com.");
        }

        return $models[0];
    }

    private function send(string $model, array $messages, bool $json): Response
    {
        return Http::withToken((string) config('dems.groq.key'))->acceptJson()->timeout((int) config('dems.groq.timeout', 60))
            ->post((string) config('dems.groq.url'), array_filter([
                'model' => $model, 'messages' => $messages, 'temperature' => 0.3, 'max_tokens' => 2200,
                'response_format' => $json ? ['type' => 'json_object'] : null,
            ]));
    }

    private function text(Response $res): string
    {
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

    private function base(): string
    {
        return rtrim(preg_replace('#/chat/completions/?$#', '', (string) config('dems.groq.url')), '/');
    }

    private function cacheKey(): string
    {
        return 'dems.groq.model.'.md5((string) config('dems.groq.model').'|'.substr(md5((string) config('dems.groq.key')), 0, 8));
    }
}
