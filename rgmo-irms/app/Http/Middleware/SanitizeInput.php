<?php

namespace App\Http\Middleware;

use App\Support\InputSanitizer;
use Illuminate\Foundation\Http\Middleware\TransformsRequest;

class SanitizeInput extends TransformsRequest
{
    protected function transform($key, $value)
    {
        return InputSanitizer::clean($key, $value);
    }
}
