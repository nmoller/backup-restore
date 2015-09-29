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

require __DIR__.'/command.php';

class restore extends command {

    public static  function run() {
        // On a besoin du path où mettre le fichier de backup
        $opts = array(
            'file'=>'y', //chemin pour le fichier de backup
            'cat' => 'y' // cours shortname
        );
        $r = command::get_runner($opts);
        $options = getopt($r->shortoptions, $r->options);

        $error = false;
        if (!isset($options['file']) ) {
            logger::shout('loption --file doit etre indiquee');
            $error = true;
        }

        if (!file_exists($options['file'])) {
            cli_error("Backup file '" . $options['file'] . "' does not exist.");
            $error = true;
        }
        if (!is_readable($options['file'])) {
            cli_error("Backup file '" . $options['file'] . "' is not readable.");
            $error = true;
        }

        if (!isset($options['cat']) || !is_int($options['cat'])) {
            logger::shout('indiquer la categorie --cat ');
            $error = true;
        }

        if ($error) return;

        // $permissions = fileperms($options['path']);
        // logger::shout($options['path'] . ' permissions:' . $permissions. PHP_EOL);
        backup::execute($options['file'], $options['cat']);
    }

    /**
     * https://github.com/tmuras/moosh/blob/master/Moosh/Command/Moodle23/Course/CourseRestore.php
     * @param $bkpfile
     * @param $categoryId
     */
    public static function execute($bkpfile, $categoryId) {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . "/backup/util/includes/backup_includes.php");
        require_once($CFG->dirroot . "/backup/util/includes/restore_includes.php");

        if (empty($CFG->tempdir)) {
            $CFG->tempdir = $CFG->dataroot . DIRECTORY_SEPARATOR . 'temp';
        }
        $error = false;
        // Check if category is OK.
        if ($categoryId) {
            $category = $DB->get_record('course_categories', array('id' => $categoryId), '*', MUST_EXIST);
            $error = true;
        }




        if (!$error) {
            //unzip into $CFG->tempdir / "backup" / "auto_restore_" . $split[1];
            $backupdir = "moosh_restore_" . uniqid();
            $path = $CFG->tempdir . DIRECTORY_SEPARATOR . "backup" . DIRECTORY_SEPARATOR . $backupdir;

            /** @var $fp file_packer */
            $fp = get_file_packer('application/vnd.moodle.backup');
            $fp->extract_to_pathname($bkpfile, $path);
        }

        //extract original full & short names
        $xmlfile = $path . DIRECTORY_SEPARATOR . "course" . DIRECTORY_SEPARATOR . "course.xml";
        // Different XML file in Moodle 1.9 backup
        if (!file_exists($xmlfile)) {
            $xmlfile = $path . DIRECTORY_SEPARATOR . "moodle.xml";
        }
        $xml = simplexml_load_file($xmlfile);
        $fullname = $xml->xpath('/course/fullname');
        if (!$fullname) {
            $fullname = $xml->xpath('/MOODLE_BACKUP/COURSE/HEADER/FULLNAME');
        }
        $shortname = $xml->xpath('/course/shortname');
        if (!$shortname) {
            $shortname = $xml->xpath('/MOODLE_BACKUP/COURSE/HEADER/SHORTNAME');
        }
        $fullname = (string)($fullname[0]);
        $shortname = (string)($shortname[0]);
        if (!$shortname) {
            cli_error('No shortname in the backup file.');
        }
        if (!$fullname) {
            $fullname = $shortname;
        }

        $courseid = restore_dbops::create_new_course($fullname, $shortname, $category->id);
        $rc = new restore_controller($backupdir, $courseid, backup::INTERACTIVE_NO,
            backup::MODE_GENERAL, $USER->id, backup::TARGET_NEW_COURSE);

        echo "Restoring (new course id,shortname,destination category): $courseid,$shortname," . $category->id . "\n";
        if ($rc->get_status() == backup::STATUS_REQUIRE_CONV) {
            $rc->convert();
        }
        $plan = $rc->get_plan();
        $tasks = $plan->get_tasks();
        foreach ($tasks as &$task) {
            $setting = $task->get_setting('enrol_migratetomanual');
            $setting->set_value('0');
        }
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();
        echo "New course ID for '$shortname': $courseid in {$category->id}\n";
    }

}