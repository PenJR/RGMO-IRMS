<?php

namespace App\Rules;

use App\Models\Project;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CurrentProject implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $user = auth()->user();
        $query = Project::query()->current()->whereKey($value);
        if ($user && ! $user->isAdmin()) {
            // Empty legacy memberships remain selectable until an administrator assigns members.
            $query->where(fn ($projects) => $projects->whereDoesntHave('participants')
                ->orWhereHas('participants', fn ($members) => $members->whereKey($user->id)));
        }
        if (! $query->exists()) {
            $fail('The selected project is not currently available.');
        }
    }
}
