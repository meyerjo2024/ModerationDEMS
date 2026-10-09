<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Request;

/** A user-facing rule violation (wrong state, bad input, no permission). */
class WorkflowException extends Exception
{
    public function __construct(string $message, public int $status = 400)
    {
        parent::__construct($message);
    }

    public static function forbidden(string $m = 'You do not have permission to do that.'): self
    {
        return new self($m, 403);
    }

    public static function conflict(string $m): self
    {
        return new self($m, 409);
    }

    public static function notFound(string $m = 'Not found.'): self
    {
        return new self($m, 404);
    }

    public function render(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => $this->getMessage(), 'message' => $this->getMessage()], $this->status);
        }

        return back()->withInput($request->except(['password', 'signature']))->with('error', $this->getMessage());
    }
}
