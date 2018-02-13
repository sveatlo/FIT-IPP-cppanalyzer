<?php
namespace App\Symbol;

/**
 * MethodSymbol class contains all informations about one method in class
 */
class MethodSymbol extends AbstractMemberSymbol {
    private $arguments = [];
    private $virtual = 0; // 0 - not virtual, 1 - not pure virtual, 2 - pure virtual
    private $inheritable = true;

    function __construct($type, $name, $arguments, $virtual, $static) {
        $this->type = $type;
        $this->name = $name;
        $this->arguments = $arguments;
        $this->virtual = $virtual;
        $this->static = $static;
    }

    // helper function for conflicts checking
    public function __toString() {
        return md5(''.$this->type.$this->name.implode(',', $this->arguments));
    }

    public function set_inheritable($inheritable = true) {
        $this->inheritable = $inheritable;
    }

    public function get_inheritable() {
        return $this->inheritable;
    }

    public function is_virtual() {
        return $this->virtual;
    }

    public function is_inheritable() {
        return $this->inheritable;
    }

    public function get_arguments() {
        return $this->arguments;
    }
}
