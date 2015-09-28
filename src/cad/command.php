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
        foreach ($opts as $param => $req){
            // S'il y ades espaces changer à _
            $param = preg_replace('/\s+/', '_', $param);
            $op = ($req == 'y')?':':'::';
            $my_options[] = $param . $op;

        }

        $this->options = $my_options;
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

class backup extends command {
    public static function run() {
        // On a besoin du path où mettre le fichier de backup
        $opts = array(
            'path'=>'y', //chemin pour le fichier de backup
            'sn' => 'y' // cours shortname
        );
        $r = command::get_runner($opts);
        $options = getopt("", $r->options);

        $error = false;
        if (!isset($options['path']) || !is_dir($options['path']) ) {
            logger::shout('loption --path nest pas valide');
            $error = true;
        }

        if (!isset($options['sn'])) {
            logger::shout('indiquer le shortname --sn ');
            $error = true;
        }

        if ($error) return;


        //var_dump($options);
        $permissions = fileperms($options['path']);
        logger::shout($options['path'] . ' permissions:' . $permissions. PHP_EOL);
        backup::execute($options['sn'], $options['path']);


    }

    /**
     * C'est une copie de la commande moosh
     * https://github.com/tmuras/moosh/blob/master/Moosh/Command/Moodle23/Course/CourseBackup.php
     * @param $shortname
     * @param $savepath
     */
    public static function execute($shortname, $savepath) {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        error_reporting(E_ALL);
        ini_set('display_errors',true);
        //check if course id exists
        $course = $DB->get_record('course', array('shortname' => $shortname), '*', MUST_EXIST);
        $shortname = str_replace(' ', '_', $course->shortname);
        $filename = $savepath . '/backup_' . str_replace('/','_',$shortname) . '_' . date('Y.m.d') . '.mbz';

        //check if destination file does not exist and can be created
        if (file_exists($filename)) {
            cli_error("File '{$filename}' already exists, I will not over-write it.");
        }
        $bc = new \backup_controller(\backup::TYPE_1COURSE, $course->id , \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_YES, \backup::MODE_GENERAL, 2); // 2 correspond à user admin!

        $tasks = $bc->get_plan()->get_tasks();
        foreach ($tasks as &$task) {
            if ($task instanceof \backup_root_task) {
                $setting = $task->get_setting('users');
                $setting->set_value('0');
                $setting = $task->get_setting('anonymize');
                $setting->set_value('1');
                $setting = $task->get_setting('role_assignments');
                $setting->set_value('1');
                $setting = $task->get_setting('filters');
                $setting->set_value('0');
                $setting = $task->get_setting('comments');
                $setting->set_value('0');
                $setting = $task->get_setting('logs');
                $setting->set_value('0');
                $setting = $task->get_setting('grade_histories');
                $setting->set_value('0');
            }
        }
        
        $bc->set_status(\backup::STATUS_AWAITING);
        $bc->execute_plan();
        $result = $bc->get_results();
        if(isset($result['backup_destination']) && $result['backup_destination']) {
            $file = $result['backup_destination'];
            /** @var $file stored_file */
            if(!$file->copy_content_to($filename)) {
                cli_error("Problems copying final backup to '". $filename . "'");
            } else {
                printf("%s\n", $filename);
            }
        } else {
            echo $bc->get_backupid();
        }
    }
}