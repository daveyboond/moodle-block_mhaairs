<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(dirname(__FILE__). '/lib.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * PHPUnit mhaairs gradebook test case base class.
 *
 * @package     block_mhaairs
 * @category    phpunit
 * @group       block_mhaairs_backup
 * @copyright   2015 Itamar Tzadok <itamar@substantialmethods.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_mhaairs_backup_restore_testcase extends block_mhaairs_testcase {

    /**
     * Tests restored grade items after course dupliction.
     *
     * @return void
     */
    public function test_restore_grade_items() {
        global $DB;

        $itemtype = 'manual';
        $itemmodule = 'mhaairs';
        $countparams = array('itemtype' => $itemtype, 'itemmodule' => $itemmodule);

        $this->set_user('admin');

        // There are no grade items yet.
        $this->assertEquals(0, $DB->count_records('grade_items', $countparams));

        // Let's add a couple.
        $addcallback = 'block_mhaairs_gradebookservice_external::update_grade';

        // Update data.
        $updatedata = array();
        $updatedata['source'] = 'mhaairs';
        $updatedata['courseid'] = 'tc1';
        $updatedata['itemtype'] = $itemtype;
        $updatedata['itemmodule'] = $itemmodule;
        $updatedata['iteminstance'] = 0;
        $updatedata['itemnumber'] = 0;
        $updatedata['grades'] = null;
        $updatedata['itemdetails'] = null;

        // Item details.
        $itemdetails = array();
        $itemdetails['categoryid'] = '';
        $itemdetails['courseid'] = '';
        $itemdetails['identity_type'] = '';
        $itemdetails['itemtype'] = $itemtype;
        $itemdetails['idnumber'] = 0;
        $itemdetails['gradetype'] = 1;
        $itemdetails['grademax'] = 100;
        $itemdetails['iteminfo'] = '';

        // Create assignments.
        $assignments = array(
            2538 => 'mhaairs101',
            92557 => 'mhaairs102',
        );
        foreach ($assignments as $key => $name) {
            $itemdetails['itemname'] = $name;
            $itemdetailsjson = urlencode(json_encode($itemdetails));
            $updatedata['itemdetails'] = $itemdetailsjson;
            $updatedata['iteminstance'] = $key;

            $result = call_user_func_array($addcallback, $updatedata);
            $this->assertEquals(GRADE_UPDATE_OK, $result);
        }

        // There should be two grade items now.
        $this->assertEquals(2, $DB->count_records('grade_items', $countparams));

        // Duplicate the course.
        $course2 = $this->getDataGenerator()->create_course();
        $this->duplicate_course($this->course, $course2);

        // There should be 4 grade items now.
        $this->assertEquals(4, $DB->count_records('grade_items', $countparams));
        foreach (array($this->course->id, $course2->id) as $courseid) {
            foreach ($assignments as $key => $name) {
                $params = array(
                    'courseid' => $courseid,
                    'itemtype' => $itemtype,
                    'itemmodule' => $itemmodule,
                    'iteminstance' => $key,
                    'itemname' => $name
                );
                $this->assertEquals(1, $DB->count_records('grade_items', $params));
            }
        }

    }

    protected function duplicate_course($source, $target) {
        global $USER;

        // Do backup.
        $bc = new backup_controller(
            backup::TYPE_1COURSE,
            $source->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_IMPORT,
            $USER->id
        );
        $backupid = $bc->get_backupid();
        $backupbasepath = $bc->get_plan()->get_basepath();
        $bc->execute_plan();
        $bc->destroy();

        // Do restore.
        $rc = new restore_controller(
            $backupid,
            $target->id,
            backup::INTERACTIVE_NO,
            backup::MODE_IMPORT,
            $USER->id,
            backup::TARGET_CURRENT_ADDING
        );

        if (!$rc->execute_precheck()) {
            $precheckresults = $rc->get_precheck_results();
            if (is_array($precheckresults) && !empty($precheckresults['errors'])) {
                if (empty($CFG->keeptempdirectoriesonbackup)) {
                    fulldelete($backupbasepath);
                }
            }
        }

        $rc->execute_plan();
        $rc->destroy();

        if (empty($CFG->keeptempdirectoriesonbackup)) {
            fulldelete($backupbasepath);
        }

        // Clear the time limit, otherwise phpunit complains.
        set_time_limit(0);
    }

}
