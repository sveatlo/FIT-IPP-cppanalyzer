<?php
namespace App\Token;

/**
 * Type specifiers are all the keyword before variable types (short, long, unsigned, etc.)
 */
class TypeSpecifierToken extends AbstractToken {
    protected $type = 'T_TYPE_SPECIFIER';
    private $specifierType;

    function __construct($specifierType) {
        $this->specifierType = $specifierType;
    }

    public function to_string() {
        return $this->specifierType;
    }
}
