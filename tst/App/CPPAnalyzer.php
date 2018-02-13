<?php
namespace App;

class CPPAnalyzer {
    static $input_file = false;
    static $output_file = false;
    static $pretty_indent = 2;
    static $details_mode = false;

    private $parser;
    private $xml = null;
    private $classes = [];

    function __construct($__argc, $__argv) {
        $this->parse_arguments($__argc, $__argv);
        $this->parser = new Parser();
        $this->xml = new \XMLWriter();
        $this->xml->openMemory();
        $this->xml->startDocument('1.0','UTF-8');
        $this->xml->setIndent(true);
        $this->run();
        fwrite(CPPAnalyzer::$output_file, preg_replace_callback('/^( +)</m', function($a) {
            return str_repeat(' ', strlen($a[1])*CPPAnalyzer::$pretty_indent).'<';
        }, $this->xml->outputMemory()));

    }

    function __destruct() {
        Utils::close_file(CPPAnalyzer::$input_file);
        Utils::close_file(CPPAnalyzer::$output_file);
    }

    private function parse_arguments($argc, $argv) {
        $options = getopt('i:o:p::d::h', array(
            'input:',
            'output:',
            'pretty-xml::',
            'details::',
            'help'
        ));

        if(
            ((count($options) !== $argc - 1) ||
            (isset($options['help']) || isset($options['h'])) && $argc > 2) ||
            (isset($options['i']) && isset($options['input'])) ||
            (isset($options['o']) && isset($options['output'])) ||
            (isset($options['p']) && isset($options['pretty-xml'])) ||
            (isset($options['d']) && isset($options['details']))
        ) {
            return Utils::print_and_die(1);
        }

        if(isset($options['help']) || isset($options['h'])) {
            $this->printHelp();
            return Utils::print_and_die(0);
        }

        $input_file = false;
        if(!isset($options['i']) && !isset($options['input'])) {
            $input_file = 'php://stdin';
        } elseif(!isset($options['i']) && isset($options['input'])) {
            $input_file = $options['input'];
        } elseif(isset($options['i']) && !isset($options['input'])) {
            $input_file = $options['i'];
        }
        CPPAnalyzer::$input_file = $input_file;

        $output_file = false;
        if(!isset($options['o']) && !isset($options['output'])) {
            $output_file = 'php://stdout';
        } elseif(!isset($options['o']) && isset($options['output'])) {
            $output_file = $options['output'];
        } elseif(isset($options['o']) && !isset($options['output'])) {
            $output_file = $options['o'];
        }
        CPPAnalyzer::$output_file = $output_file;

        $pretty_indent = CPPAnalyzer::$pretty_indent;
        if(!isset($options['p']) && isset($options['pretty-xml'])) {
            $pretty_indent = (bool)$options['pretty-xml'] ? $options['pretty-xml'] : CPPAnalyzer::$pretty_indent;
        } elseif(isset($options['p']) && !isset($options['pretty-xml'])) {
            $pretty_indent = (bool)$options['p'] ? $options['p'] : CPPAnalyzer::$pretty_indent;
        }
        if($pretty_indent < 0) {
            return Utils::print_and_die(1);
        }
        CPPAnalyzer::$pretty_indent = $pretty_indent;

        $details_mode = true;
        if(!isset($options['d']) && !isset($options['details'])) {
            $details_mode = false;
        } else if(!isset($options['d']) && isset($options['details'])) {
            $details_mode = $options['details'] === false ? true : $options['details'];
        } elseif(isset($options['d']) && !isset($options['details'])) {
            $details_mode = $options['d'] === false ? true : $options['d'];
        }
        CPPAnalyzer::$details_mode = $details_mode;



        // prepare files for reading/writing
        CPPAnalyzer::$input_file = @fopen(CPPAnalyzer::$input_file, 'r') or Utils::print_and_die(2);
        CPPAnalyzer::$output_file = @fopen(CPPAnalyzer::$output_file, 'w') or Utils::print_and_die(3);
    }

    private function printHelp() {
        echo "IPP project solution (assignment CLS). Created by xhanze10.\n";
        echo "Arguments:\n";
        echo "\t--input=file\t\tInput text file\n";
        echo "\t--output=file\t\tFile used for output. If not supplied, stdout will be used\n";
        echo "\t--pretty-xml=k\t\tPretty prints XML indented by k spaces\n";
        echo "\t--details=class\t\tEchoes information about class members\n";
        return;
    }

    private function run() {
        $this->classes = &$this->parser->parse(CPPAnalyzer::$input_file);
        // var_dump($this->classes);

        if(CPPAnalyzer::$details_mode) {
            $this->run_details();
        } else {
            $this->run_inheritance();
        }
    }

    private function run_inheritance() {
        $this->xml->startElement('model');
        foreach ($this->classes as $class) {
            if(!$class->is_extends()) {
                $this->print_class_inheritance_model($class);
            }
        }
        $this->xml->endElement();
    }

    private function print_class_inheritance_model(&$class) {
        $this->xml->startElement('class');
            $this->xml->writeAttribute('name', $class->get_name());
            $this->xml->writeAttribute('kind', $class->is_abstract() ? 'abstract' : 'concrete');
            foreach($this->find_extending_classes($class) as $extending_class) {
                $this->print_class_inheritance_model($extending_class);
            }
        $this->xml->endElement();
    }

    private function find_extending_classes($extended_class) {
        $extending_classes = [];
        foreach ($this->classes as $class) {
            foreach ($class->get_extends() as $extending_class) {
                if($extending_class[1] === $extended_class->get_name()) {
                    $extending_classes[] = $class;
                }
            }
        }
        return $extending_classes;
    }


    private function run_details() {
        if(CPPAnalyzer::$details_mode === true) {
            $this->xml->startElement('model');
            foreach ($this->classes as $class) {
                $this->print_class_details($class);
            }
            $this->xml->endElement();
        } else {
            $class = &Utils::find_class(CPPAnalyzer::$details_mode, $this->classes);
            if($class === null) {
                return Utils::print_and_die(22);
            }
            $this->print_class_details($class);
        }
    }

    private function print_class_details(&$class) {
        // var_dump($class);

        $this->xml->startElement('class');
        $this->xml->writeAttribute('name', $class->get_name());
        $this->xml->writeAttribute('kind', $class->is_abstract() ? 'abstract' : 'concrete');

        // inheritance
        if(count($class->get_extends()) > 0) {
            $this->xml->startElement('inheritance');
            foreach ($class->get_extends() as $extended) {
                $this->xml->startElement('from');
                $this->xml->writeAttribute('name', $extended[1]);
                $this->xml->writeAttribute('privacy', $extended[0]);
                $this->xml->endElement();
            }
            $this->xml->endElement();
        }

        $this->print_privacy_member_group('private', $class);
        $this->print_privacy_member_group('public', $class);
        $this->print_privacy_member_group('protected', $class);

        $this->xml->endElement();
    }

    private function print_privacy_member_group($privacy, &$class) {
        $members = &$class->get_members($privacy);
        if(count($members['methods']) + count($members['attributes']) !== 0) {
            $this->xml->startElement($privacy);

            if(count($members['attributes']) !== 0) {
                $this->xml->startElement('attributes');
                foreach($members['attributes'] as $attribute) {
                    $this->print_attribute($attribute, $class);
                }
                $this->xml->endElement();
            }


            if(count($members['methods']) !== 0) {
                $this->xml->startElement('methods');
                foreach($members['methods'] as $method) {
                    $this->print_method($method, $class);
                }
                $this->xml->endElement();
            }

            $this->xml->endElement();
        }
    }

    private function print_attribute(&$attribute, &$class) {
        $this->xml->startElement('attribute');

        $this->xml->writeAttribute('name', $attribute->get_name());
        $this->xml->writeAttribute('type', $attribute->get_type());
        $this->xml->writeAttribute('scope', $attribute->is_static() ? 'static' : 'instance');

        if($attribute->from) {
            $this->xml->startElement('from');
            $this->xml->writeAttribute('name', $attribute->from);
            $this->xml->endElement();
        }

        $this->xml->endElement();
    }

    private function print_method(&$method, &$class) {
        $this->xml->startElement('method');

        $this->xml->writeAttribute('name', $method->get_name());
        $this->xml->writeAttribute('type', $method->get_type());
        $this->xml->writeAttribute('scope', $method->is_static() ? 'static' : 'instance');

        if($method->is_virtual()) {
            $this->xml->startElement('virtual');
            $this->xml->writeAttribute('pure', $method->is_virtual() === 2 ? 'yes' : 'no');
            $this->xml->endElement();
        }

        if($method->from) {
            $this->xml->startElement('from');
            $this->xml->writeAttribute('name', $method->from);
            $this->xml->endElement();
        }

        $this->xml->startElement('arguments');
        foreach ($method->get_arguments() as $argument) {
            $this->xml->startElement('argument');
            $this->xml->writeAttribute('name', $argument->get_name());
            $this->xml->writeAttribute('type', $argument->get_type());
            $this->xml->endElement();
        }
        $this->xml->endElement();

        $this->xml->endElement();
    }
}
