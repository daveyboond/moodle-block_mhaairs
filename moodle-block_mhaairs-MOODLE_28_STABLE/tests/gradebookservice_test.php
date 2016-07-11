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

/**
 * PHPUnit Mhaairs gradebook service tests.
 *
 * @package     block_mhaairs
 * @category    phpunit
 * @copyright   2014 Itamar Tzadok <itamar@substantialmethods.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(dirname(__FILE__). '/lib.php');
require_once("$CFG->dirroot/blocks/mhaairs/externallib.php");
require_once("$CFG->libdir/gradelib.php");

/**
 * PHPUnit mhaairs gradebook service test case.
 *
 * @package     block_mhaairs
 * @category    phpunit
 * @group       block_mhaairs
 * @group       block_mhaairs_service
 * @group       block_mhaairs_gradebookservice
 * @copyright   2014 Itamar Tzadok <itamar@substantialmethods.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_mhaairs_gradebookservice_testcase extends block_mhaairs_testcase {

    /**
     * Gradebookservice update grade should fail when sync grades is disabled
     * in the plugin site settings.
     *
     * @return void
     */
    public function test_update_grade_no_sync() {
        global $DB;

        $callback = 'block_mhaairs_gradebookservice_external::gradebookservice';
        $this->set_user('admin');

        // Item details.
        $itemdetails = $this->get_test_item_details(0);
        $itemdetailsjson = urlencode(json_encode($itemdetails));

        // Service params.
        $serviceparams = $this->get_test_item_params(0);
        $serviceparams['itemdetails'] = $itemdetailsjson;

        // No sync.
        $DB->set_field('config', 'value', 0, array('name' => 'block_mhaairs_sync_gradebook'));

        $result = call_user_func_array($callback, $serviceparams);
        $this->assertEquals(GRADE_UPDATE_FAILED, $result);
    }

    /**
     * Gradebookservice update grade should fail when the target course is not found.
     *
     * @return void
     */
    public function test_update_grade_invalid_course() {
        global $DB;

        $callback = 'block_mhaairs_gradebookservice_external::gradebookservice';
        $this->set_user('admin');

        // Item details.
        $itemdetails = $this->get_test_item_details(0);
        $itemdetailsjson = urlencode(json_encode($itemdetails));

        // Service params.
        $serviceparams = $this->get_test_item_params(0);
        $serviceparams['itemdetails'] = $itemdetailsjson;

        // There is only one course in this test,
        // So get an id that is different from the course's.
        $serviceparams['courseid'] = $this->course->id + 1;

        $result = call_user_func_array($callback, $serviceparams);
        $this->assertEquals(GRADE_UPDATE_FAILED, $result);
    }

    /**
     * Tests the gradebookservice update grade function for adding,
     * updating and deleting an item.
     *
     * @return void
     */
    public function test_create_update_delete_grade_item() {
        global $DB;

        $callback = 'block_mhaairs_gradebookservice_external::gradebookservice';
        $this->set_user('admin');

        // Item details.
        $itemdetails = $this->get_test_item_details(0);
        $itemdetailsjson = urlencode(json_encode($itemdetails));

        // Service params.
        $serviceparams = $this->get_test_item_params(0);
        $serviceparams['itemdetails'] = $itemdetailsjson;

        // Grade item fetch params.
        $giparams = array(
            'itemtype' => 'manual',
            'itemmodule' => 'mhaairs',
            'iteminstance' => 0,
            'courseid' => $this->course->id,
            'itemnumber' => 0
        );

        // CREATE.
        $result = call_user_func_array($callback, $serviceparams);
        $this->assertEquals(GRADE_UPDATE_OK, $result);

        // Check grade item created.
        // Expected 2: one for the course and one for the item.
        $this->assertEquals(2, $DB->count_records('grade_items'));

        $gitem = grade_item::fetch($giparams);
        $this->grade_item_assert_equals($gitem, $itemdetails);

        // UPDATE.
        // Identify course by idnumber.
        $serviceparams['courseid'] = 'tc1';
        // Item details.
        $itemdetails['grademax'] = 95;
        $itemdetailsjson = urlencode(json_encode($itemdetails));
        $serviceparams['itemdetails'] = $itemdetailsjson;

        $result = call_user_func_array($callback, $serviceparams);
        $this->assertEquals(GRADE_UPDATE_OK, $result);

        // Check grade item updated.
        // Expected 2: one for the course and one for the item.
        $this->assertEquals(2, $DB->count_records('grade_items'));

        $gitem = grade_item::fetch($giparams);
        $this->grade_item_assert_equals($gitem, $itemdetails);

        // UPDATE the should fail.
        // Identify course by idnumber.
        $serviceparams['courseid'] = 'tc1';
        // Try to update by id only.
        $itemdetails['identity_type'] = 'internal';
        $itemdetailsjson = urlencode(json_encode($itemdetails));
        $serviceparams['itemdetails'] = $itemdetailsjson;

        $result = call_user_func_array($callback, $serviceparams);
        $this->assertEquals(GRADE_UPDATE_FAILED, $result);

        // DELETE ITEM.
        // Identify course by id.
        $serviceparams['courseid'] = $this->course->id;
        // Set delete in item details.
        $itemdetails['deleted'] = true;
        $itemdetailsjson = urlencode(json_encode($itemdetails));
        $serviceparams['itemdetails'] = $itemdetailsjson;

        $result = call_user_func_array($callback, $serviceparams);
        $this->assertEquals(GRADE_UPDATE_OK, $result);

        $this->assertEquals(1, $DB->count_records('grade_items'));
    }

    /**
     * Tests the gradebookservice update grade with cases that should
     * result in success.
     *
     * @return void
     */
    public function test_update_grade_item_valid() {
        global $DB;

        $callback = 'block_mhaairs_gradebookservice_external::update_grade';
        $this->set_user('admin');

        // CREATE/UPDATE.
        $cases = $this->get_update_grade_servicedata_cases('tc_update_grade_valid');
        foreach ($cases as $case) {
            if (!empty($case->hidden)) {
                continue;
            }

            $result = call_user_func_array($callback, $case->servicedata);
            $this->assertEquals(GRADE_UPDATE_OK, $result);

            // Get the user id (assuming username given).
            if ($gradeusername = $case->grade_username) {
                $userid = $this->$gradeusername->id;

                // Grade item fetch params.
                $giparams = array(
                    'itemtype' => $case->itemtype,
                    'itemmodule' => $case->itemmodule,
                    'iteminstance' => 0,
                    'courseid' => $this->course->id,
                    'itemnumber' => 0
                );

                $gitem = grade_item::fetch($giparams);
                $usergrades = grade_grade::fetch_users_grades($gitem, array($userid), false);
                if (!$usergrades) {
                    $this->assertEquals(true, false);
                } else {
                    $grade = reset($usergrades);
                    $this->assertEquals(95, $grade->finalgrade);
                }
            }
        }
    }

    /**
     * Tests the gradebookservice update grade with cases that relate
     * to the fix in CONTRIB_5853, that is, removing the ";,-" characters
     * cleanup that was performed on categoryid, itemtype and userid.
     *
     * @return void
     */
    public function test_update_grade_item_userid_with_hyphen() {
        global $DB;

        $callback = 'block_mhaairs_gradebookservice_external::update_grade';
        $this->set_user('admin');

        // Service data.
        $servicedata = array();
        $servicedata['source'] = 'mhaairs';
        $servicedata['courseid'] = 'tc1';
        $servicedata['itemtype'] = 'manual';
        $servicedata['itemmodule'] = 'mhaairs';
        $servicedata['iteminstance'] = 0;
        $servicedata['itemnumber'] = 0;
        $servicedata['grades'] = null;
        $servicedata['itemdetails'] = null;

        // Item details.
        $itemdetails = array();
        $itemdetails['categoryid'] = '';
        $itemdetails['courseid'] = '';
        $itemdetails['identity_type'] = '';
        $itemdetails['itemname'] = 'assignment5853';
        $itemdetails['itemtype'] = 'mod';
        $itemdetails['idnumber'] = 0;
        $itemdetails['gradetype'] = 1;
        $itemdetails['grademax'] = 100;
        $itemdetails['iteminfo'] = '';
        $itemdetailsjson = urlencode(json_encode($itemdetails));

        $servicedata['itemdetails'] = $itemdetailsjson;

        // Grade for username student1.
        $grades = array();
        $grades['userid'] = 'student1';
        $grades['rawgrade'] = 93;
        $gradesjson = urlencode(json_encode($grades));

        $servicedata['grades'] = $gradesjson;

        $result = call_user_func_array($callback, $servicedata);
        $this->assertEquals(GRADE_UPDATE_OK, $result);

        // Grade for username student-1.
        $user = $this->getDataGenerator()->create_user(array('username' => 'student-1'));
        $this->getDataGenerator()->enrol_user($user->id, $this->course->id, $this->roles['student']);

        $grades = array();
        $grades['userid'] = 'student-1';
        $grades['rawgrade'] = 93;
        $gradesjson = urlencode(json_encode($grades));

        $servicedata['grades'] = $gradesjson;

        $result = call_user_func_array($callback, $servicedata);
        $this->assertEquals(GRADE_UPDATE_OK, $result);
    }

    /**
     * Tests the gradebookservice update grade with cases that should
     * result in failure.
     *
     * @return void
     */
    public function test_update_grade_item_invalid() {
        global $DB;

        $callback = 'block_mhaairs_gradebookservice_external::update_grade';
        $this->set_user('admin');

        // CREATE/UPDATE.
        $cases = $this->get_update_grade_servicedata_cases('tc_update_grade_invalid');
        foreach ($cases as $case) {
            if (!empty($case->hidden)) {
                continue;
            }
            $result = call_user_func_array($callback, $case->servicedata);
            $this->assertEquals(GRADE_UPDATE_FAILED, $result);
        }
    }

    /**
     * Tests the migration of an old mhaairs (mod/quiz) grade item without grade.
     * (CONTRIB_5863)
     *
     * @return void
     */
    public function test_grade_item_migration_via_update_no_score() {
        global $DB;

        $this->set_user('admin');

        $quizitemparams = array('itemtype' => 'mod', 'itemmodule' => 'quiz');
        $mhaairsitemparams = array('itemtype' => 'manual', 'itemmodule' => 'mhaairs');

        $this->assertEquals(0, $DB->count_records('grade_items'));

        $service = 'block_mhaairs_gradebookservice_external::update_grade';
        $iteminstance = 24993;
        $itemdetails = array(
            'itemname' => 'mhaairs-quiz',
            'itemtype' => 'mod',
            'idnumber' => '0',
        );
        $itemdetailsjson = urlencode(json_encode($itemdetails));
        $grades = array(
            'userid' => 'student1',
            'rawgrade' => 93,
        );
        $gradesjson = urlencode(json_encode($grades));

        // Create a mod/quiz grade item directly.
        $result = grade_update(
            'mhaairs',
            $this->course->id,
            'mod',
            'quiz',
            $iteminstance,
            0,
            null,
            $itemdetails
        );

        $itemcount = $DB->count_records('grade_items', $quizitemparams);
        $this->assertEquals(1, $itemcount);

        // Update item via service.
        $servicedata = array();
        $servicedata['source'] = 'mhaairs';
        $servicedata['courseid'] = 'tc1';
        $servicedata['itemtype'] = 'manual';
        $servicedata['itemmodule'] = 'mhaairs';
        $servicedata['iteminstance'] = $iteminstance;
        $servicedata['itemnumber'] = 0;
        $servicedata['grades'] = null;
        $servicedata['itemdetails'] = $itemdetailsjson;

        $result = call_user_func_array($service, $servicedata);

        // No quiz items.
        $itemcount = $DB->count_records('grade_items', $quizitemparams);
        $this->assertEquals(0, $itemcount);

        // 1 mhaairs item.
        $itemcount = $DB->count_records('grade_items', $mhaairsitemparams);
        $this->assertEquals(1, $itemcount);

        // Update score.
        $servicedata['grades'] = $gradesjson;
        $result = call_user_func_array($service, $servicedata);
        $this->assertEquals(1, $itemcount);

        // Verify score updated.
        $usergrade = $DB->get_field('grade_grades', 'finalgrade', array('userid' => $this->student1->id));
        $this->assertEquals(93, (int) $usergrade);

        // No quiz items.
        $itemcount = $DB->count_records('grade_items', $quizitemparams);
        $this->assertEquals(0, $itemcount);

        // 1 mhaairs item.
        $itemcount = $DB->count_records('grade_items', $mhaairsitemparams);
        $this->assertEquals(1, $itemcount);
    }

    /**
     * Tests the migration of an old mhaairs (mod/quiz) grade item with grade.
     * (CONTRIB_5863)
     *
     * @return void
     */
    public function test_grade_item_migration_via_update_with_score() {
        global $DB;

        $this->set_user('admin');

        $quizitemparams = array('itemtype' => 'mod', 'itemmodule' => 'quiz');
        $mhaairsitemparams = array('itemtype' => 'manual', 'itemmodule' => 'mhaairs');

        $this->assertEquals(0, $DB->count_records('grade_items'));

        $service = 'block_mhaairs_gradebookservice_external::update_grade';
        $iteminstance = 24993;
        $itemdetails = array(
            'itemname' => 'mhaairs-quiz',
            'itemtype' => 'mod',
            'idnumber' => '0',
        );
        $itemdetailsjson = urlencode(json_encode($itemdetails));
        $grades = array(
            'userid' => 'student1',
            'rawgrade' => 93,
        );
        $gradesjson = urlencode(json_encode($grades));

        // Create a mod/quiz grade item directly.
        $result = grade_update(
            'mhaairs',
            $this->course->id,
            'mod',
            'quiz',
            $iteminstance,
            0,
            null,
            $itemdetails
        );

        $itemcount = $DB->count_records('grade_items', $quizitemparams);
        $this->assertEquals(1, $itemcount);

        // Update item via service.
        $servicedata = array();
        $servicedata['source'] = 'mhaairs';
        $servicedata['courseid'] = 'tc1';
        $servicedata['itemtype'] = 'manual';
        $servicedata['itemmodule'] = 'mhaairs';
        $servicedata['iteminstance'] = $iteminstance;
        $servicedata['itemnumber'] = 0;
        $servicedata['grades'] = $gradesjson;
        $servicedata['itemdetails'] = $itemdetailsjson;

        $result = call_user_func_array($service, $servicedata);
        $this->assertEquals(GRADE_UPDATE_OK, $result);

        // No quiz items.
        $itemcount = $DB->count_records('grade_items', $quizitemparams);
        $this->assertEquals(0, $itemcount);

        // 1 mhaairs item.
        $itemcount = $DB->count_records('grade_items', $mhaairsitemparams);
        $this->assertEquals(1, $itemcount);

        // Verify score updated.
        $usergrade = $DB->get_field('grade_grades', 'finalgrade', array('userid' => $this->student1->id));
        $this->assertEquals(93, (int) $usergrade);
    }

    /**
     * Tests the gradebookservice get grade with cases that should
     * result in success.
     *
     * @return void
     */
    public function test_get_grade_valid() {
        global $DB;

        $callback = 'block_mhaairs_gradebookservice_external::get_grade';
        $this->set_user('admin');

        // CREATE/UPDATE.
        $cases = $this->get_update_grade_servicedata_cases('tc_get_grade_valid');
        foreach ($cases as $case) {
            if (!empty($case->hidden)) {
                continue;
            }

            // Run the update grade function which creates/updates the grade.
            $updateparams = $case->servicedata;
            $updateparams['courseid'] = $this->course->id;
            $updateparams['itemdetails'] = json_decode(urldecode($updateparams['itemdetails']), true);
            $updateparams['grades'] = json_decode(urldecode($updateparams['grades']), true);
            if (!empty($updateparams['grades']['userid'])) {
                if ($updateparams['itemdetails']['identity_type'] != 'internal') {
                    $username = $updateparams['grades']['userid'];
                    $updateparams['grades']['userid'] = $this->$username->id;
                }
            }

            call_user_func_array('grade_update', $updateparams);

            $result = call_user_func_array($callback, $case->servicedata);
            $this->assertNotEquals(null, $result);
        }
    }

    /**
     * Asserts equals grade item values and expected values.
     *
     * @param grade_item $gitem
     * @param array $expected
     * @return void
     */
    protected function grade_item_assert_equals($gitem, array $expected) {
        // Some fields should be asserted separately.
        unset($expected['courseid']);
        unset($expected['categoryid']);

        $this->assertEquals($this->course->id, $gitem->courseid);
        foreach ($gitem as $var => $value) {
            if (array_key_exists($var, $expected)) {
                $this->assertEquals($expected[$var], $value);
            }
        }
    }

    /**
     *
     * @return array
     */
    protected function get_update_grade_servicedata_cases($fixturename) {
        // Test cases.
        $fixture = __DIR__. "/fixtures/$fixturename.csv";
        $dataset = $this->createCsvDataSet(array('cases' => $fixture));
        $rows = $dataset->getTable('cases');
        $columns = $dataset->getTableMetaData('cases')->getColumns();

        $cases = array();
        for ($r = 0; $r < $rows->getRowCount(); $r++) {
            $cases[] = (object) array_combine($columns, $rows->getRow($r));
        }

        // Item details.
        $itemdetailsparams = array(
            'categoryid',
            'courseid',
            'identity_type',
            'itemname',
            'itemtype',
            'idnumber',
            'gradetype',
            'grademax',
            'iteminfo',
        );

        // Service params.
        $serviceparams = array(
            'source', // Source.
            'courseid', // Course id.
            'itemtype', // Item type.
            'itemmodule', // Item module.
            'iteminstance', // Item instance.
            'itemnumber', // Item number.
            'grades', // Grades.
            'itemdetails', // Item details.
        );

        foreach ($cases as $case) {
            // Compile the item details.
            $itemdetails = array();
            foreach ($itemdetailsparams as $param) {
                $caseparam = "item_$param";
                if (isset($case->$caseparam)) {
                    $itemdetails[$param] = $case->$caseparam;
                }
            }
            $itemdetails = $itemdetails ? $itemdetails : null;
            $itemdetailsjson = urlencode(json_encode($itemdetails));

            // Compile the grades, userid and rawgrade.
            // Assign the username to userid as is or converted according the identity type.
            $grades = null;
            if (!empty($case->grade_username)) {
                $grades = array();

                $gradeusername = $case->grade_username;
                if ($case->item_identity_type == 'internal') {
                    $grades['userid'] = $this->$gradeusername->id;
                } else {
                    $grades['userid'] = $gradeusername;
                }
                $grades['rawgrade'] = $case->grade_rawgrade;
            }
            $gradesjson = urlencode(json_encode($grades));

            // Compile the service params.
            $servicedata = array();
            foreach ($serviceparams as $param) {
                $value = isset($case->$param) ? $case->$param : '';
                $servicedata[$param] = $value;
            }
            if (!empty($case->courseinstance)) {
                $thiscourse = $this->{$case->courseinstance};
                $servicedata['courseid'] = $thiscourse->id;
            }
            $servicedata['itemdetails'] = $itemdetailsjson;
            $servicedata['grades'] = $gradesjson;

            $case->servicedata = $servicedata;
        }

        return $cases;
    }

    /**
     *
     * @return array
     */
    protected function get_test_item_details($index = -1) {
        $items = array();

        $items[] = array(
            'categoryid' => '',
            'courseid' => '',
            'identity_type' => '',
            'itemname' => 'testassignment',
            'itemtype' => 'manual',
            'idnumber' => 0,
            'gradetype' => GRADE_TYPE_VALUE,
            'grademax' => 100,
            'iteminfo' => '',
        );

        if (array_key_exists($index, $items)) {
            return $items[$index];
        } else {
            return $items;
        }
    }

    /**
     *
     * @return array
     */
    protected function get_test_item_params($index = -1) {
        $items = array();
        $items[] = array(
            'source' => 'mhaairs', // Source.
            'courseid' => $this->course->id, // Course id.
            'itemtype' => 'manual', // Item type.
            'itemmodule' => 'mhaairs', // Item module.
            'iteminstance' => '0', // Item instance.
            'itemnumber' => '0', // Item number.
            'grades' => null, // Grades.
            'itemdetails' => null, // Item details.
        );

        if (array_key_exists($index, $items)) {
            return $items[$index];
        } else {
            return $items;
        }
    }

}
