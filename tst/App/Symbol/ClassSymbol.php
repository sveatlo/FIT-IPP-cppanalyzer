<?php
namespace App\Symbol;

/**
 *  ClassSymbol class represents a class object (code) from the C++ class definition files.
 *  It contains all the informations about class, including name, members, informations about
 *  classes it extends, etc.
 */
class ClassSymbol {
    private $name;
    private $extends = [];

    private $members = [
        'private' => [
            'attributes' => [],
            'methods' => []
        ],
        'public' => [
            'attributes' => [],
            'methods' => []
        ],
        'protected' => [
            'attributes' => [],
            'methods' => []
        ]
    ];

    public function get_name() {
        return $this->name;
    }
    public function set_name($name) {
        $this->name = $name;
        return $this->name;
    }


    /**
     *  Adds new member to class
     *
     *  @param  string  $privacy    One of [private, protected, public]
     *  @param  Object  $member     VariableSymbol object (attribute) or MethodSymbol object (method)
     */
    public function add_member($privacy, $member) {
        $attribute_class_string = '\App\Symbol\VariableSymbol';
        if(isset($this->members[$privacy][$member instanceof $attribute_class_string ? 'attributes' : 'methods'][(string)$member])) {
            return \App\Utils::print_and_die(4);
        }

        $this->members[$privacy][$member instanceof $attribute_class_string ? 'attributes' : 'methods'][(string)$member] = $member;
    }

    /**
     *  Return all members including those inherited from other classes.
     *  The optional parameter specifies which privacy group to return. If null,
     *  all members are returned.
     *
     *  @param  string  $privacy    One of [private, protected, public]
     */
    public function get_members($privacy = null, $private_inherited = false) {
        $extended_classes_members = [];
        foreach ($this->extends as $extended_class) {
            $tmp = [];
            $tmp['public'] = isset($extended_class[2]['public']) ? $extended_class[2]['public'] : [];
            $tmp['protected'] = isset($extended_class[2]['protected']) ? $extended_class[2]['protected'] : [];
            $tmp['private'] = isset($extended_class[2]['private']) ? $extended_class[2]['private'] : [];
            if($private_inherited && isset($extended_class[2]['blocked'])) {
                $tmp['private'] = array_merge_recursive($extended_class[2]['blocked'], $tmp['private']);
            }

            $extended_classes_members[] = $tmp;
        }
        $tmp = $this->members;
        $tmp['blocked'] = $this->members['private'];
        $extended_classes_members[] = $tmp;

        $merged_members = call_user_func_array('array_replace_recursive', $extended_classes_members);
        return $privacy === null ? $merged_members : $merged_members[$privacy];
    }

    /**
     *  Return md5 hash of all members (including the ones inherited).
     *  Useful for conflict checking
     */
    public function get_all_members_hash() {
        $members = [];
        foreach ($this->get_members() as $member) {
            foreach ($member['attributes'] as $attribute) {
                $members[] = (string)$attribute;
            }
            foreach ($member['methods'] as $method) {
                if(!$method->is_inheritable()) {
                    continue;
                }
                $members[] = (string)$method;
            }
        }

        return $members;
    }


    public function add_extends($extends) {
        $this->extends[] = $extends;
    }

    public function get_extends() {
        return $this->extends;
    }


    public function is_extends() {
        return count($this->extends) !== 0;
    }

    /**
     *  Checks whether at least one of the methods in this class is pure virtual method,
     *  which would mark this class as abstract.
     */
    public function is_abstract() {
        $abstract = false;
        foreach($this->get_members(null, true) as $privacy_level) {
            foreach ($privacy_level['methods'] as $method) {
                if($method->is_virtual() === 2) {
                    $abstract = true;
                }
            }
        }
        return $abstract;
    }

    /**
     *  Processes class inheritance, looks for conflicts from multiple inheritance,
     *  adds new members to class, etc.
     *
     *  @param  ClassSymbol[]    $classes_list  List of all ClasSymbols processed before this class
     */
    public function process_inheritance(&$classes_list) {
        if(count($this->extends) === 0) {
            return;
        }

        $tmp_inheritance = [];
        $conflicts_params = [];
        foreach ($this->extends as $extended_class) {
            $class = \App\Utils::find_class($extended_class[1], $classes_list);
            if($class === null) {
                return \App\Utils::print_and_die(4);
            }
            $hashes = $class->get_all_members_hash();
            $tmp_inheritance[] = [$class, $hashes];
            $conflicts_params[] = $hashes;
        }

        $conflicts = [];
        if(count($conflicts_params) >= 2) {
            // find conflicts
            $conflicts = call_user_func_array('array_intersect', $conflicts_params);
            if(count($conflicts) > 0) {
                return \App\Utils::print_and_die(21);
            }
        }

        // no conflicts possible
        // do the extending
        foreach ($this->extends as $class_key => $extended_class) {
            $class = \App\Utils::find_class($extended_class[1], $classes_list);
            foreach ($class->get_members(null, true) as $privacy => $privacy_level) {
                // change privacy levels
                // bocked = private in original class
                // @see http://stackoverflow.com/a/30696123/1419318
                if($extended_class[0] === 'private') {
                    if($privacy === 'public') {
                        $privacy = 'private';
                    } else if($privacy === 'protected') {
                        $privacy = 'private';
                    } else {
                        $privacy = 'blocked';
                    }
                } else if($extended_class[0] === 'protected') {
                    if($privacy === 'public') {
                        $privacy = 'protected';
                    } else if($privacy === 'protected') {
                        $privacy = 'protected';
                    } else {
                        $privacy = 'blocked';
                    }
                } else if($extended_class[0] === 'public') {
                    if($privacy === 'public') {
                        $privacy = 'public';
                    } else if($privacy === 'protected') {
                        $privacy = 'protected';
                    } else {
                        $privacy = 'blocked';
                    }
                }

                foreach ($privacy_level['attributes'] as $member_key => $member) {
                    $do = true;
                    foreach ($this->get_members() as $inner_privacy_key => $inner_privacy) {
                        if(isset($inner_privacy['attributes'][$member_key])) {
                            $do = false;
                        }
                    }
                    if(!$do) {
                        continue;
                    }
                    $this->extends[$class_key][2][$privacy]['attributes'][$member_key] = clone $member;
                    $this->extends[$class_key][2][$privacy]['attributes'][$member_key]->from = $class->get_name();
                }
                foreach ($privacy_level['methods'] as $member_key => $member) {
                    $do = true;
                    foreach ($this->get_members() as $inner_privacy_key => $inner_privacy) {
                        if(isset($inner_privacy['methods'][$member_key])) {
                            $do = false;
                        }
                    }
                    if(!$do) {
                        continue;
                    }
                    $this->extends[$class_key][2][$privacy]['methods'][$member_key] = clone $member;
                    $this->extends[$class_key][2][$privacy]['methods'][$member_key]->from = $class->get_name();
                }
            }
        }
    }
}
