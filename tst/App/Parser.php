<?php
namespace App;

/**
 * Parses tokens to symbols
 */
class Parser
{
    private $scanner;
    private $current_token = null;

    private $classes = [];
    private $current_clas = null;
    private $current_privacy = 'private';

    public function __construct()
    {
        // scan file token by token
        $this->scanner = new Scanner();
    }

    /**
     * Wrapper around Scanner's get_next_token
     */
    private function get_next_token() {
        $this->current_token = $this->scanner->get_next_token();
        return $this->current_token;
    }


    /**
     * Wrapper around Scanner's get_previous_token
     */
    private function get_prev_token() {
        $this->current_token = $this->scanner->get_previous_token();
        return $this->current_token;
    }


    /**
     *  Starts the parsing of $file
     *
     *  @param  resource    $file   The input file (resource) to parse
     */
    public function parse(&$file) {
        $this->scanner->scan_file($file);

        $this->class_rule();
        // var_dump($this->classes);
        return $this->classes;
    }

    /**
     * Parses the outer class structure including inheritance
     */
    private function class_rule() {
        if($this->get_next_token()->get_type() !== 'T_CLASS') {
            return Utils::print_and_die(4);
        }
        if($this->get_next_token()->get_type() !== 'T_IDENTIFIER') {
            return Utils::print_and_die(4);
        }

        $this->classes[] = new \App\Symbol\ClassSymbol();
        $this->current_class = &end($this->classes);

        $this->current_class->set_name($this->current_token->get_identifier());
        $this->current_privacy = 'private'; // reset default privacy level


        $this->get_next_token();
        // process inheritance if any
        if ($this->current_token->get_type() === 'T_COLON') {
            while($this->get_next_token()->get_type() !== 'T_LEFT_BRACE') {
                $privacy = 'private';
                if($this->current_token->get_type() === 'T_PRIVACY') {
                    $privacy = $this->current_token->get_privacy();
                    $this->get_next_token();
                }

                if($this->current_token->get_type() !== 'T_IDENTIFIER') {
                    return Utils::print_and_die(4);
                }

                $class_name = $this->current_token->get_identifier();

                $this->current_class->add_extends([$privacy, $class_name, []]);
                // is there more?
                if($this->get_next_token()->get_type() !== 'T_COMMA') {
                    // nope
                    $this->get_prev_token();
                } else {
                    // probably
                }
            }
        }

        if($this->current_token->get_type() !== 'T_LEFT_BRACE') {
            return Utils::print_and_die(4);
        }

        // parse the inside of the class
        $this->class_members_rule();
        if($this->current_token->get_type() !== 'T_RIGHT_BRACE') {
            return Utils::print_and_die(4);
        }
        if($this->get_next_token()->get_type() !== 'T_SEMICOLON') {
            return Utils::print_and_die(4);
        }

        $this->current_class->process_inheritance($this->classes);

        try {
            $this->get_next_token();
            $this->get_prev_token();
            $this->class_rule();
        } catch (\Exception $e) {
            // ok, finish parsing the file
        }

    }

    private function class_members_rule() {
        while($this->get_next_token()->get_type() !== 'T_RIGHT_BRACE') {
            $this->class_member_rule();
        }
    }

    /**
     * Parse everything that might be inside of a class
     */
    private function class_member_rule() {
        if($this->current_token->get_type() === 'T_PRIVACY') {
            // privacy level change
            $this->current_privacy = $this->current_token->get_privacy();
            if($this->get_next_token()->get_type() !== 'T_COLON') {
                return Utils::print_and_die(4);
            }
        } else if($this->current_token->get_type() === 'T_TYPE' || $this->current_token->get_type() === 'T_TYPE_SPECIFIER') {
            // method or attribute
            $this->method_attribute_member_rule(0, false);
        } else if ($this->current_token->get_type() === 'T_VIRTUAL') {
            // virtual method
            $this->get_next_token();
            if($this->current_token->get_type() !== 'T_TYPE' && $this->current_token->get_type() !== 'T_TYPE_SPECIFIER') {
                return Utils::print_and_die(4);
            }
            $this->method_attribute_member_rule(1, false);
        } else if($this->current_token->get_type() === 'T_STATIC') {
            // some static member
            $this->get_next_token();
            if($this->current_token->get_type() !== 'T_TYPE' && $this->current_token->get_type() !== 'T_TYPE_SPECIFIER') {
                return Utils::print_and_die(4);
            }
            $this->method_attribute_member_rule(0, true);
        } else if($this->current_token->get_type() === 'T_IDENTIFIER') {
            // maybe constructor or syntax error
            if($this->current_token->get_identifier() !== $this->current_class->get_name()) {
                return Utils::print_and_die(4);
            }
            if($this->get_next_token()->get_type() !== 'T_LEFT_PARENTHESE') {
                return Utils::print_and_die(4);
            }
            $parameters_list = $this->parse_method_parameters();
            if($this->get_next_token()->get_type() !== 'T_LEFT_BRACE') {
                return Utils::print_and_die(4);
            }
            if($this->get_next_token()->get_type() !== 'T_RIGHT_BRACE') {
                return Utils::print_and_die(4);
            }
            // check for optional semicolon
            if($this->get_next_token()->get_type() !== 'T_SEMICOLON') {
                $this->get_prev_token();
            }

            // QUESTION: chceck constructor default privacy level
            $constructor = new \App\Symbol\MethodSymbol('void', $this->current_class->get_name(), $parameters_list, 0, false, true);
            $constructor->set_inheritable(false);
            $this->current_class->add_member('private', $constructor);
        } else if($this->current_token->get_type() === 'T_TILDA') {
            if($this->get_next_token()->get_type() !== 'T_IDENTIFIER') {
                return Utils::print_and_die(4);
            }
            if($this->current_token->get_identifier() !== $this->current_class->get_name()) {
                return Utils::print_and_die(4);
            }
            if($this->get_next_token()->get_type() !== 'T_LEFT_PARENTHESE') {
                return Utils::print_and_die(4);
            }
            if($this->get_next_token()->get_type() !== 'T_RIGHT_PARENTHESE') {
                return Utils::print_and_die(4);
            }
            if($this->get_next_token()->get_type() !== 'T_LEFT_BRACE') {
                return Utils::print_and_die(4);
            }
            if($this->get_next_token()->get_type() !== 'T_RIGHT_BRACE') {
                return Utils::print_and_die(4);
            }
            // check for optional semicolon
            if($this->get_next_token()->get_type() !== 'T_SEMICOLON') {
                $this->get_prev_token();
            }

            // QUESTION: chceck destructor default privacy level
            $constructor = new \App\Symbol\MethodSymbol('void', '~'.$this->current_class->get_name(), [], 0, false, true);
            $constructor->set_inheritable(false);
            $this->current_class->add_member('private', $constructor);
        } else if($this->current_token->get_type() === 'T_USING') {
            // using CLASS
            if($this->get_next_token()->get_type() !== 'T_IDENTIFIER') {
                return Utils::print_and_die(4);
            }
            $class_name = $this->current_token->get_identifier();

            // check for ::
            if($this->get_next_token()->get_type() !== 'T_COLON') {
                return Utils::print_and_die(4);
            }
            if($this->get_next_token()->get_type() !== 'T_COLON') {
                return Utils::print_and_die(4);
            }

            if($this->get_next_token()->get_type() !== 'T_IDENTIFIER') {
                return Utils::print_and_die(4);
            }
            $member_name = $this->current_token->get_identifier();
            if($this->get_next_token()->get_type() !== 'T_SEMICOLON') {
                return Utils::print_and_die(4);
            }


            // find class from using
            $class = Utils::find_class($class_name, $this->classes);
            if($class === null) {
                return Utils::print_and_die(4);
            }
            foreach($class->get_members() as $privacy => $privacy_level) {
                foreach ($privacy_level['attributes'] as $member_key => $member) {
                    if($member->get_name() === $member_name) {
                        if($member->from === null) {
                            $member = clone $member;
                            $member->from = $class->get_name();
                        }
                        $this->current_class->add_member($privacy, $member);
                    }
                }
                foreach ($privacy_level['methods'] as $member_key => $member) {
                    if($member->get_name() === $member_name) {
                        if($member->from === null) {
                            $member = clone $member;
                            $member->from = $class->get_name();
                        }
                        $this->current_class->add_member($privacy, $member);
                    }
                }
            }
        } else if($this->current_token->get_type() === 'T_RIGHT_BRACE') {
        } else {
            return Utils::print_and_die(4);
        }
    }

    private function method_attribute_member_rule($virtual = 0, $static = false) {
        if($static && $virtual) {
            return Utils::print_and_die(4);
        }

        $member_type = $this->variable_type_accumulator();
        if(!$this->validate_variable_type($member_type)) {
            return Utils::print_and_die(4);
        }

        if($this->get_next_token()->get_type() === 'T_ASTERISK') {
            $member_type .= ' *';
        } else {
            $this->get_prev_token();
        }

        if($this->get_next_token()->get_type() === 'T_AMBERSAND') {
            $member_type .= ' &';
        } else {
            $this->get_prev_token();
        }

        if($this->get_next_token()->get_type() !== 'T_IDENTIFIER') {
            return Utils::print_and_die(4);
        }
        $member_name = $this->current_token->get_identifier();

        $this->get_next_token();
        if($this->current_token->get_type() === 'T_LEFT_PARENTHESE') {
            $parameters_list = $this->parse_method_parameters();

            $this->get_next_token();
            if($this->current_token->get_type() === 'T_LEFT_BRACE') {
                if($this->get_next_token()->get_type() !== 'T_RIGHT_BRACE') {
                    return Utils::print_and_die(4);
                }
                // check for optional semicolon
                if($this->get_next_token()->get_type() !== 'T_SEMICOLON') {
                    $this->get_prev_token();
                }
            } else if($this->current_token->get_type() === 'T_EQUALS') {
                if($virtual === 0) {
                    // =0 is not allowed for non-virtual methods
                    return Utils::print_and_die(4);
                }

                $virtual = 2;
                if($this->get_next_token()->get_type() !== 'T_ZERO') {
                    return Utils::print_and_die(4);
                }
                if($this->get_next_token()->get_type() !== 'T_SEMICOLON') {
                    return Utils::print_and_die(4);
                }
            } else {
                return Utils::print_and_die(4);
            }

            $this->current_class->add_member($this->current_privacy, new \App\Symbol\MethodSymbol($member_type, $member_name, $parameters_list, $virtual, $static));
        } else if($this->current_token->get_type() === 'T_SEMICOLON') {
            if($virtual != 0) {
                return Utils::print_and_die(4);
            }

            $this->current_class->add_member($this->current_privacy, new \App\Symbol\VariableSymbol($member_type, $member_name, $static));
        } else {
            return Utils::print_and_die(4);
        }
    }

    private function variable_type_accumulator() {
        $type = '';
        while($this->current_token->get_type() === 'T_TYPE' || $this->current_token->get_type() === 'T_TYPE_SPECIFIER'){
            $type .= ' '.$this->current_token->to_string();
            $this->get_next_token();
        }
        $this->get_prev_token(); // return the invalid token;
        return ltrim($type, ' ');
    }

    private function validate_variable_type($variable) {
        if($variable === 'bool' ||
        $variable === 'char' ||
        $variable === 'char16_t' ||
        $variable === 'char32_t' ||
        $variable === 'wchar_t' ||
        $variable === 'signed char' ||
        $variable === 'short int' ||
        $variable === 'int' ||
        $variable === 'long int' ||
        $variable === 'long long int' ||
        $variable === 'unsigned char' ||
        $variable === 'unsigned short int' ||
        $variable === 'unsigned int' ||
        $variable === 'unsigned long int' ||
        $variable === 'unsigned long long int' ||
        $variable === 'float' ||
        $variable === 'double' ||
        $variable === 'long double' ||
        $variable === 'void') {
            return true;
        } else {
            return false;
        }
    }

    private function parse_method_parameters() {
        $params = [];

        $this->get_next_token();
        if($this->current_token->get_type() === 'T_RIGHT_PARENTHESE') {
            return $params;
        }
        $member_type = $this->variable_type_accumulator();
        if(!$this->validate_variable_type($member_type)) {
            return Utils::print_and_die(4);
        }

        if($this->get_next_token()->get_type() === 'T_ASTERISK') {
            $member_type .= ' *';
        } else {
            $this->get_prev_token();
        }

        if($this->get_next_token()->get_type() === 'T_AMBERSAND') {
            $member_type .= ' &';
        } else {
            $this->get_prev_token();
        }

        if($this->get_next_token()->get_type() !== 'T_IDENTIFIER') {
            if($member_type === 'void' && $this->current_token->get_type() === 'T_RIGHT_PARENTHESE') {
                return [];
            }
            return Utils::print_and_die(4);
        }

        $member_name = $this->current_token->get_identifier();
        $params[] = new \App\Symbol\VariableSymbol($member_type, $member_name, false);


        $this->get_next_token();
        if($this->current_token->get_type() === 'T_RIGHT_PARENTHESE') {
            return $params;
        } else {
            return array_merge($params, $this->parse_method_parameters());
        }

    }
}
