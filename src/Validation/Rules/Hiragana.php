<?php
declare(strict_types=1);
namespace LSlim\Validation\Rules;

use Respect\Validation\Rules\Core\Simple;

class Hiragana extends Simple
{
    public function isValid(mixed $input): bool
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
