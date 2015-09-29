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
            return;
        }

        if (!file_exists($options['file'])) {
            cli_error("Backup file '" . $options['file'] . "' does not exist.");
            $error = true;
        }
        if (!is_readable($options['file'])) {
            cli_error("Backup file '" . $options['file'] . "' is not readable.");
            $error = true;
        }

        if (!isset($options['cat'])) {
            logger::shout('indiquer la categorie --cat ');
            $error = true;
        }

        global $DB;
        // Check if category is OK.
        if (isset($options['cat'])) {
            $category = $DB->get_record('course_categories', array('id' => $options['cat']), '*', MUST_EXIST);
            if (!isset($category->id)) {
                logger::shout('La categorie ' . $options['cat'] . ' nexiste pas.');
                $error = true;
            }
        }

        if ($error) return;

        // $permissions = fileperms($options['path']);
        // logger::shout($options['path'] . ' permissions:' . $permissions. PHP_EOL);
        restore::execute($options['file'], $options['cat']);
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

        //unzip into $CFG->tempdir / "backup" / "auto_restore_" . $split[1];
        $backupdir = "moosh_restore_" . uniqid();
        $path = $CFG->tempdir . DIRECTORY_SEPARATOR . "backup" . DIRECTORY_SEPARATOR . $backupdir;

        /** @var $fp file_packer */
        $fp = get_file_packer('application/vnd.moodle.backup');
        $fp->extract_to_pathname($bkpfile, $path);


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

        $courseid = \restore_dbops::create_new_course($fullname, $shortname, $categoryId);
        $rc = new \restore_controller($backupdir, $courseid, \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL, 2, \backup::TARGET_NEW_COURSE);

        echo "Restoring (new course id,shortname,destination category): $courseid, $shortname," . $categoryId . "\n";
        if ($rc->get_status() == \backup::STATUS_REQUIRE_CONV) {
            $rc->convert();
        }
        $plan = $rc->get_plan();

        //TODO: valider les options réquises.
        $restopt = array(
            'activities' => 1,
            'blocks' => 1,
            'filters' => 1,
            'users' => 0,
            'role_assignments' => 1,
            'comments' => 0,
            'logs' => 0
        );

        foreach ($restopt as $name => $value) {
            $setting = $plan()->get_setting($name);
            if ($setting->get_status() == \backup_setting::NOT_LOCKED) {
                $setting->set_value($value);
            }
        }

        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();
        echo "New course ID for '$shortname': $courseid in {$categoryId}\n";
        // Ajouter le idnumber dans le nouveau cours.
        $c = $DB->get_record('course',array('id'=> $courseid));
        $c->idnumber = self::get_idnumber($shortname);
        $DB->update_record('course', $c);
    }

    /**
     * @param $shortname format attendu XXX-XXX-XX-XX-XX
     * @return string XXXXXXXX-XX-XX
     */
    private static function get_idnumber($shortname) {
        $comps = explode('-', $shortname);
         return $comps[0].$comps[1].$comps[2].'-'.$comps[3].'-'.$comps[4];
    }

}