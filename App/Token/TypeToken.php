<?php
namespace App\Token;

/**
 *  Represents tokens for variable types (int, double, float, etc.)
 */
class TypeToken extends AbstractToken {
    protected $type = 'T_TYPE';
    private $variableType;

    function __construct($variableType) {
        $this->variableType = $variableType;
    }

    public function to_string() {
        return $this->variableType;
    }
}
