<?php
namespace App\Token;

/**
 *  Token represeting the keywords private, public, protected
 */
class PrivacyToken extends AbstractToken {
    protected $type = 'T_PRIVACY';
    private $privacyType;

    function __construct($privacyType) {
        $this->privacyType = $privacyType;
    }

    public function get_privacy() {
        return $this->privacyType;
    }
}
