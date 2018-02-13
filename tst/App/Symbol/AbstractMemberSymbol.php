<?php
namespace App\Symbol;

abstract class AbstractMemberSymbol {
    protected $type;
    protected $name;
    protected $static = false;
    public $from = null;

    public function is_static() {
        return $this->static;
    }

    public function get_name() {
        return $this->name;
    }

    public function get_type() {
        return $this->type;
    }

}
