<?php
declare(strict_types=1);
namespace LSlim\Validation\Rules;

use Respect\Validation\Rules\AbstractRule;

class Hiragana extends AbstractRule
{
    public function validate($input)
    {
        if ($input === null) {
            return false;
        }

        if (preg_match('|^[ぁ-ゞ]+$|u', $input)) {
            return true;
        }

        return false;
    }
}
