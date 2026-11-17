<?php

namespace App\Exceptions;

use App\Models\ProcurementDraft;
use Illuminate\Http\Request;
use RuntimeException;

class ProcurementDraftConflictException extends RuntimeException
{
    public function __construct(public readonly ProcurementDraft $draft, string $message)
    {
        parent::__construct($message);
    }

    public function render(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage(), 'draft' => $this->draft->toClientArray()], 409);
        }

        return back()->withInput()->withErrors(['autosave_draft' => $this->getMessage()]);
    }
}
