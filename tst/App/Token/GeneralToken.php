<?php
namespace App\Token;

/**
 *  Token representing any general characters, such as parentheses, commas, colons, etc.
 */
class GeneralToken extends AbstractToken {
    function __construct($_type) {
        if(!$_type) {
            throw new \Exception("Invalid token type", 1);
        }

        $this->type = $_type;
    }
}
