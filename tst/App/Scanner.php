<?php
namespace App;

const SS_LEX_ERROR = -1;
const SS_EMPTY = 1;
const SS_KEYWORD_IDENT = 2;

/**
 * Scanner is a singleton class used for scanngin the input file, turning it into an array of tokens
 */
class Scanner {
    private $tokens = []; // the array of tokens to be returned
    private $state = SS_EMPTY; // current state, the scanner is in
    private $current_token_string = '';
    private $do_not_get_another_character = false; // used instead of ungetc

    private $token_position = -1; // current position in tokens array, useful for traversing tokens

    /**
     * Returns next token from the tokens' array
     */
    public function get_next_token() {
        $this->token_position++;
        if($this->token_position > (count($this->tokens) - 1)) {
            throw new \Exception("No more tokens", 1);
        }

        return $this->tokens[$this->token_position];
    }

    /**
     * Returns previous token from the tokens' array
     */
    public function get_previous_token() {
        //go one back to previous
        $this->token_position--;
        if($this->token_position < 0) {
            throw new \Exception("No previous tokens", 1);
        }

        return $this->tokens[$this->token_position];
    }

    public function scan_file(&$file) {
        while (!feof($file)) {
            if(!$this->do_not_get_another_character) {
                $c = fgetc($file);
            }

            // hvězdičkou, ampersandem, identifikátory, dvojtečkou, středníkem, čárkou, vlnkou,závorkami
            switch ($this->state) {
                case SS_EMPTY:
                    $this->current_token_string = '';
                    $this->do_not_get_another_character = false;
                    if(ctype_alpha($c)) {
                        $this->current_token_string .= $c;
                        $this->state = SS_KEYWORD_IDENT;
                    } elseif ($c === '*') {
                        $this->insertNewToken('asterisk');
                        $this->state = SS_EMPTY;
                    } elseif ($c === '&') {
                        $this->insertNewToken('ambersand');
                        $this->state = SS_EMPTY;
                    } elseif ($c === ':') {
                        $this->insertNewToken('colon');
                        $this->state = SS_EMPTY;
                    } elseif ($c === ';') {
                        $this->insertNewToken('semicolon');
                        $this->state = SS_EMPTY;
                    } elseif ($c === ',') {
                        $this->insertNewToken('comma');
                        $this->state = SS_EMPTY;
                    } elseif ($c === '~') {
                        $this->insertNewToken('tilda');
                        $this->state = SS_EMPTY;
                    } elseif ($c === '(') {
                        $this->insertNewToken('left_parenthese');
                        $this->state = SS_EMPTY;
                    } elseif ($c === ')') {
                        $this->insertNewToken('right_parenthese');
                        $this->state = SS_EMPTY;
                    } elseif ($c === '{') {
                        $this->insertNewToken('left_brace');
                        $this->state = SS_EMPTY;
                    } elseif ($c === '}') {
                        $this->insertNewToken('right_brace');
                        $this->state = SS_EMPTY;
                    } elseif($c === '=') {
                        $this->insertNewToken('equals');
                        $this->state = SS_EMPTY;
                    } elseif($c === '0') {
                        $this->insertNewToken('zero');
                        $this->state = SS_EMPTY;
                    }
                    break;

                case SS_KEYWORD_IDENT:
                    if(!ctype_alnum($c) && $c !== '_') {
                        $this->state = SS_EMPTY;
                        $this->do_not_get_another_character = true;
                        $this->insertNewToken('keyword_ident');
                        break;
                    }
                    $this->current_token_string .= $c;
                    break;
            }
        }

        // foreach ($this->tokens as $token) {
        //     echo $token->get_type();
        //     if($token->get_type() === 'T_IDENTIFIER') {
        //         echo ":".$token->get_identifier();
        //     }
        //     echo " ";
        // }
        // echo "\n";
    }

    /**
     * Inserts new GeneralToken
     */
    private function insertNewToken($type) {
        if(!$type) {
            throw new \Exception("Invalid token type: ".$type, 1);
        }

        switch ($type) {
            case 'keyword_ident':
                $this->insertKeywordOrIdent();
                break;

            case 'asterisk':
                $this->tokens[] = new \App\Token\GeneralToken('T_ASTERISK');
                break;
            case 'ambersand':
                $this->tokens[] = new \App\Token\GeneralToken('T_AMBERSAND');
                break;
            case 'colon':
                $this->tokens[] = new \App\Token\GeneralToken('T_COLON');
                break;
            case 'semicolon':
                $this->tokens[] = new \App\Token\GeneralToken('T_SEMICOLON');
                break;
            case 'comma':
                $this->tokens[] = new \App\Token\GeneralToken('T_COMMA');
                break;
            case 'tilda':
                $this->tokens[] = new \App\Token\GeneralToken('T_TILDA');
                break;
            case 'left_parenthese':
                $this->tokens[] = new \App\Token\GeneralToken('T_LEFT_PARENTHESE');
                break;
            case 'right_parenthese':
                $this->tokens[] = new \App\Token\GeneralToken('T_RIGHT_PARENTHESE');
                break;
            case 'left_brace':
                $this->tokens[] = new \App\Token\GeneralToken('T_LEFT_BRACE');
                break;
            case 'right_brace':
                $this->tokens[] = new \App\Token\GeneralToken('T_RIGHT_BRACE');
                break;
            case 'equals':
                $this->tokens[] = new \App\Token\GeneralToken('T_EQUALS');
                break;
            case 'zero':
                $this->tokens[] = new \App\Token\GeneralToken('T_ZERO');
                break;

            default:
                throw new \Exception("Invalid token type: ".$type, 1);
                break;
        }
    }

    /**
     * Function, which decides, whether a given string is a keyword or just an identifier
     */
    private function insertKeywordOrIdent() {
        switch ($this->current_token_string) {
            case 'class':
                $this->tokens[] = new \App\Token\GeneralToken('T_CLASS');
                break;
            case 'static':
                $this->tokens[] = new \App\Token\GeneralToken('T_STATIC');
                break;
            case 'using':
                $this->tokens[] = new \App\Token\GeneralToken('T_USING');
                break;
            case 'virtual':
                $this->tokens[] = new \App\Token\GeneralToken('T_VIRTUAL');
                break;
            case 'public':
                $this->tokens[] = new \App\Token\PrivacyToken('public');
                break;
            case 'private':
                $this->tokens[] = new \App\Token\PrivacyToken('private');
                break;
            case 'protected':
                $this->tokens[] = new \App\Token\PrivacyToken('protected');
                break;

            case 'bool':
                $this->tokens[] = new \App\Token\TypeToken('bool');
                break;
            case 'char':
                $this->tokens[] = new \App\Token\TypeToken('char');
                break;
            case 'char16_t':
                $this->tokens[] = new \App\Token\TypeToken('char16_t');
                break;
            case 'char32_t':
                $this->tokens[] = new \App\Token\TypeToken('char32_t');
                break;
            case 'double':
                $this->tokens[] = new \App\Token\TypeToken('double');
                break;
            case 'float':
                $this->tokens[] = new \App\Token\TypeToken('float');
                break;
            case 'int':
                $this->tokens[] = new \App\Token\TypeToken('int');
                break;
            case 'void':
                $this->tokens[] = new \App\Token\TypeToken('void');
                break;
            case 'wchar_t':
                $this->tokens[] = new \App\Token\TypeToken('wchar_t');
                break;

            case 'unsigned':
                $this->tokens[] = new \App\Token\TypeSpecifierToken('unsigned');
                break;
            case 'signed':
                $this->tokens[] = new \App\Token\TypeSpecifierToken('signed');
                break;
            case 'long':
                $this->tokens[] = new \App\Token\TypeSpecifierToken('long');
                break;
            case 'short':
                $this->tokens[] = new \App\Token\TypeSpecifierToken('short');
                break;
            default:
                $this->tokens[] = new \App\Token\IdentifierToken($this->current_token_string);
                break;
        }
    }
}
