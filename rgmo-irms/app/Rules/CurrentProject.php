<?php

namespace App\Rules;

use App\Models\Project;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CurrentProject implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Project::query()->current()->whereKey($value)->exists()) {
            $fail('The selected project is not currently available.');
        }
    }
}
