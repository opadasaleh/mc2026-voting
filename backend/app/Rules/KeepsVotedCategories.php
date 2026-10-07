<?php

namespace App\Rules;

use App\Models\Category;
use App\Models\Exhibitor;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An exhibitor cannot be removed from a category in which it already has votes
 * (the database blocks it too; this gives the admin a readable message).
 */
class KeepsVotedCategories implements ValidationRule
{
    public function __construct(private readonly ?Exhibitor $exhibitor) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->exhibitor === null) {
            return;
        }

        $selected = array_map('intval', (array) $value);
        $voted = $this->exhibitor->votes()->distinct()->pluck('category_id')->all();
        $removed = array_diff($voted, $selected);

        if ($removed !== []) {
            $names = Category::whereKey($removed)->pluck('name')->implode(', ');
            $fail("Cannot remove categories that already have votes for this exhibitor: {$names}.");
        }
    }
}
