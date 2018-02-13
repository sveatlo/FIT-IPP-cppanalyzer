<?php
namespace App;

/**
 * Class accumulating methods and variables, which can be use in the whole project
 */
class Utils {
    public static function print_and_die($code = 101) {
        if($code !== 0) {
            fwrite(STDERR, "An error occured: ".Utils::getErrorMessage($code)."\n");
        }
        die($code);
    }

    public static function getErrorMessage($code) {
        if($code === 1) {
            return "Wrong format parameter";
        } elseif ($code === 2) {
            return "Cannot open input file";
        } elseif($code === 3) {
            return "Cannot open output file";
        } elseif($code === 4) {
            return "Wrong input file format";
        } elseif($code === 21) {
            return "Class inheritance error";
        } elseif($code === 22) {
            return "Unknown class";
        }elseif($code >= 10 && $code <= 99) {
            return "Assignment-specific error code";
        } else {
            return "Different error";
        }
    }

    public static function close_file($handle) {
        if(gettype($handle) === 'resource') {
            fclose($handle);
        }
    }

    public static function find_class($name, &$class_list) {
        if(!$name) {
            return null;
        }

        $classes = [];
        foreach ($class_list as $class) {
            if($class->get_name() === $name) {
                $classes[] = $class;
            }
        }


        if(count($classes) > 1) {
            return Utils::print_and_die(4);
        }

        return count($classes) > 0 ? $classes[0] : null;
    }
}
