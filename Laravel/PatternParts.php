<?php

namespace App\Rules;

use App\Models\Pattern;
use Illuminate\Contracts\Validation\Rule;

class PatternParts implements Rule
{
    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $actual = 0;
        foreach(Pattern::getPartList(true) as $partId) {
            $actual += ($value & $partId);
        }

        return ($actual != 0);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Please select the part(s) to which the pattern will be applied';
    }
}
