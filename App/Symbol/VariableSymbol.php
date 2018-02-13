<?php
namespace App\Symbol;

/**
 * VariableSymbol class contains all informations about attributes in class and function arguments
 */
class VariableSymbol extends AbstractMemberSymbol {
    function __construct($type, $name, $static) {
        $this->type = $type;
        $this->name = $name;
        $this->static = $static;
    }

    // helper function for conflicts checking
    public function __toString() {
        return md5(''.$this->name);
    }
}
