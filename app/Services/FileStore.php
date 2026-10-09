<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Pluggable file storage. Keys are "<driver>:<id>".
 *  - db   : bytes live in PostgreSQL (default; no extra infrastructure)
 *  - disk : any Laravel filesystem disk (DEMS_DISK), e.g. local or an S3-compatible disk
 */
class FileStore
{
    public function put(string $bytes): string
    {
        if (config('dems.storage') === 'disk') {
            $key = 'dems/'.Str::uuid();
            Storage::disk(config('dems.disk'))->put($key, $bytes);

            return 'disk:'.$key;
        }
        $id = (string) Str::ulid();
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $bytes);
        rewind($stream);
        DB::table('file_blobs')->insert(['id' => $id, 'data' => $stream]);
        fclose($stream);

        return 'db:'.$id;
    }

    public function get(string $storageKey): string
    {
        [$driver, $id] = explode(':', $storageKey, 2);
        if ($driver === 'disk') {
            return Storage::disk(config('dems.disk'))->get($id) ?? throw new RuntimeException('Stored file is missing.');
        }
        $row = DB::table('file_blobs')->where('id', $id)->first();
        if (! $row) {
            throw new RuntimeException('Stored file is missing.');
        }

        return is_resource($row->data) ? stream_get_contents($row->data) : (string) $row->data;
    }
}
