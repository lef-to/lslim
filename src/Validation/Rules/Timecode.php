<?php
declare(strict_types=1);
namespace LSlim\Validation\Rules;

use Respect\Validation\Rules\AbstractRule;

class Timecode extends AbstractRule
{
    public function validate($input)
    {
        if ($input === null) {
            return false;
        }

        if (preg_match('/^\d{2}:(\d{2}):(\d{2})$/', $input, $m)) {
            if (0 <= $m[1] && $m[1] <= 59 && 0 <= $m[2] && $m[2] <= 59) {
                return true;
            }
        }

        return false;
    }
}
