<?php
namespace App\Token;

/**
 *  Base class for all other tokens
 */
abstract class AbstractToken {
    protected $type = NULL;

    public function get_type() {
        return $this->type;
    }
}

?>
