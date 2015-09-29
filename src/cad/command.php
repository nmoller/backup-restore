<?php
/**
 *
 * PHP Version 5.5
 * @category:
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @author: <nmoller@crosemont.qc.ca>
 * @link http://github.com/nmoller GitHub
 * 
 */

namespace cad;


class command {
    const MOODLE_ROOT = "/var/www/html/moodle/";

    /**
     * @param $opt array
     *
     * format array(
     *  'param1' => 'y', //required value
     *  'param2' => 'n' // optional param
     * )
     */
    function __construct($opts ) {
        $my_options = array();
        $short_options= '';
        foreach ($opts as $param => $req){

            $op = ($req == 'y')?':':'::';
            if (count_chars($param) == 1 ) {
                $short_options .= $param . $op;
                continue;
            }
            // S'il y ades espaces changer à _
            $param = preg_replace('/\s+/', '_', $param);

            $my_options[] = $param . $op;

        }

        $this->options = $my_options;
        $this->shortoptions = $short_options;
    }


    static function get_runner($opts) {

        define('CLI_SCRIPT', true);
        define('CACHE_DISABLE_ALL', true);
        // initialiser MDL
        require_once self::MOODLE_ROOT.'config.php';

        $r = new command($opts);
        return $r;
    }

}

class logger {
    public static function shout($message) {
        echo $message . PHP_EOL;

    }
}

