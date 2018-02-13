<?php
namespace App\Token;

/**
 *  Class used for saving informations about variable names = identifiers
 */
class IdentifierToken extends AbstractToken {
    protected $type = 'T_IDENTIFIER';
    private $identifier;

    function __construct($id) {
        $this->identifier = $id;
    }

    public function get_identifier() {
        return $this->identifier;
    }
}
