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
 * This file contains the external api class for the mhaairs-moodle integration.
 *
 * @package     block_mhaairs
 * @copyright   2014 Itamar Tzadok <itamar@substantialmethods.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') or die();

global $CFG;
require_once("$CFG->libdir/externallib.php");
require_once("$CFG->libdir/gradelib.php");
require_once("$CFG->dirroot/blocks/mhaairs/block_mhaairs_util.php");

/**
 * Block mhaairs gradebook web service.
 *
 * @package     block_mhaairs
 * @copyright   2014 Itamar Tzadok <itamar@substantialmethods.com>
 * @copyright   2013-2014 Moodlerooms inc.
 * @author      Teresa Hardy <thardy@moodlerooms.com>
 * @author      Darko MIletic <dmiletic@moodlerooms.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_mhaairs_gradebookservice_external extends external_api {

    const ITEM_DEFAULT_TYPE = 'manual';
    const ITEM_DEFAULT_MODULE = 'mhaairs';

    // UPDATE GRADE.
    /**
     * Allows external systems to push grade items and scores into the course gradebook. The service as to be
     * activated by the site admin.
     * A typical usage requires two calls, the first to create/update the grade item and the second to update
     * the user grade. The create/update grade item call should not pass null for the grades. The update user
     * grade call may require the identity_type flag which can be passed either in the grades parameter or in
     * the itemdetails paramter. The latter is otherwise not required.
     *
     * @param string $source - Any string or empty.
     * @param string $courseid - Expected idnumber or Moodle id; requires identity_type in itemdetails.
     * @param string $itemtype - Ignored; all mhaairs items should be manual.
     * @param string $itemmodule - Ignored; all mhaairs items should be identified as mhaairs.
     * @param string $iteminstance - The mhaairs assignment id.
     * @param string $itemnumber - Any integer.
     * @param string $grades - Url encoded, json encoded, array of the following user grade details:
     *      userid - The user username or internal id (depends on identity_type). Required. PARAM_TEXT.
     *      rawgrade - The user grade. Optional. PARAM_FLOAT.
     *      identity_type - Whether to treat the userid as username or internal id. Optional. PARAM_ALPHA.
     * @param string $itemdetails - Url encoded, json encoded, array of the following item details and flags:
     *      itemname - The item name. Required. PARAM_TEXT.
     *      idnumber - The item idnumber. Optional. PARAM_TEXT.
     *      gradetype - The item grade type. Optional. PARAM_INT. Defaults to point.
     *      grademax - The item grade max. Optional. PARAM_FLOAT. Defaults to 100.
     *      hidden - Whether the item is hidden. Optional. PARAM_INT.
     *      deleted - Whether to delete the item. Optional. PARAM_INT.
     *      categoryid - The name of the target category for the item. Optional. PARAM_TEXT.
     *      identity_type - Whether to treat the courseid as idnumber or internal id. Optional. PARAM_ALPHA.
     *      needsupdate - Optional. PARAM_INT.
     *      useexisting - Whether to use an existing item by name. Optional. PARAM_INT.
     *
     * @return mixed
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function update_grade($source = 'mhaairs', $courseid ='',
                            $itemtype = self::ITEM_DEFAULT_TYPE, $itemmodule = self::ITEM_DEFAULT_MODULE,
                            $iteminstance = '0', $itemnumber = '0',
                            $grades = null, $itemdetails = null) {
        global $USER, $DB;

        $logger = MHLog::instance();
        $logger->log('==================================');
        $logger->log('New webservice request started on '. $logger->time_stamp);
        $logger->log('Entry parameters:');
        $logger->log("source = {$source}");
        $logger->log("courseid = {$courseid}");
        $logger->log("itemtype = {$itemtype}");
        $logger->log("itemmodule = {$itemmodule}");
        $logger->log("iteminstance = {$iteminstance}");
        $logger->log("itemnumber = {$itemnumber}");
        $logger->log("grades = {$grades}");
        $logger->log("itemdetails = {$itemdetails}");

        // Gradebook sync must be enabled by admin in the block's site configuration.
        if (!$syncgrades = get_config('core', 'block_mhaairs_sync_gradebook')) {
            $logger->log('Grade sync is not enabled in global settings. Returning 1.');
            return GRADE_UPDATE_FAILED;
        }

        // Parameters validation.
        $params = self::validate_parameters(
            self::update_grade_parameters(),
            array(
                'source' => $source,
                'courseid' => $courseid,
                'itemtype' => $itemtype,
                'itemmodule' => $itemmodule,
                'iteminstance' => $iteminstance,
                'itemnumber' => $itemnumber,
                'grades' => $grades,
                'itemdetails' => $itemdetails,
            )
        );
        $logger->log('Parameters validated.');

        // Extract the validated parameters to their respective variables.
        foreach ($params as $var => $value) {
            ${$var} = $value;
        }

        // Context validation.
        // OPTIONAL but in most web service it should be present.
        $context = context_user::instance($USER->id);
        self::validate_context($context);
        $logger->log('Context validated.');

        // Capability checking.
        // OPTIONAL but in most web service it should be present.
        require_capability('moodle/user:viewdetails', $context, null, true, 'cannotviewprofile');
        $logger->log('Capability validated.');

        // Validate item details.
        $logger->log("Checking if any item details were sent.");
        $itemdetails = json_decode(urldecode($itemdetails), true);
        $itemdetails = self::validate_item_details($itemdetails);
        $logger->log('Item details validated: '. var_export($itemdetails, true));

        // Get the item details identity type variable.
        $identitytype = self::get_details_itentity_type($itemdetails);

        // Validate grades.
        $logger->log("Checking if any grades were sent.");
        $grades = json_decode(urldecode($grades), true);
        $grades = self::validate_grades($grades);
        // HACK Make sure grades has identity type; take from item details if must.
        if ($grades and !isset($grades['identity_type'])) {
            $grades['identity_type'] = null;
            if ($identitytype) {
                $grades['identity_type'] = $identitytype;
            }
        }
        $logger->log('Grades validated: '. var_export($grades, true));

        // Get the course.
        $course = self::get_course($courseid, $identitytype);
        if ($course === false) {
            // No valid course specified.
            $logger->log("Course id received was not correct. courseid = {$courseid}.");
            $logger->log('Returning '. GRADE_UPDATE_FAILED. '.');
            return GRADE_UPDATE_FAILED;
        }
        $courseid = $course->id;
        $logger->log('Course validated.');

        if (!$grades) {
            // A request without grades is for creating/updating/deleting a grade item.
            $result = self::update_grade_item(
                $source,
                $courseid,
                $iteminstance,
                $itemnumber,
                $itemdetails
            );

            $resultverbose = ($result == GRADE_UPDATE_OK) ? 'completed successfully' : 'failed';
            $logger->log("Grade item update $resultverbose");

        } else {
            $result = self::update_user_grade(
                $source,
                $courseid,
                $iteminstance,
                $itemnumber,
                $grades
            );

            $resultverbose = ($result == GRADE_UPDATE_OK) ? 'completed successfully' : 'failed';
            $logger->log("User grade update $resultverbose");
        }

        return $result;
    }

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function update_grade_parameters() {
        $params = array();

        // Source.
        $desc = 'string $source source of the grade such as "mhaairs"';
        $params['source'] = new external_value(PARAM_TEXT, $desc, VALUE_DEFAULT, 'mhaairs');

        // Courseid.
        $desc = 'string $courseid id of course';
        $params['courseid'] = new external_value(PARAM_TEXT, $desc, VALUE_DEFAULT, 'NULL');

        // Item type.
        $desc = 'string $itemtype type of grade item - mod, block, manual';
        $params['itemtype'] = new external_value(PARAM_TEXT, $desc, VALUE_DEFAULT, self::ITEM_DEFAULT_TYPE);

        // Item module.
        $desc = 'string $itemmodule more specific then $itemtype - assignment,'.
                ' forum, etc.; maybe NULL for some item types or anything for manual.';
        $params['itemmodule'] = new external_value(PARAM_TEXT, $desc, VALUE_DEFAULT, self::ITEM_DEFAULT_MODULE);

        // Item instance.
        $desc = 'ID of the item module';
        $params['iteminstance'] = new external_value(PARAM_TEXT, $desc, VALUE_DEFAULT, '0');

        // Item number.
        $desc = 'int $itemnumber most probably 0, modules can use other '.
                'numbers when having more than one grades for each user';
        $params['itemnumber'] = new external_value(PARAM_INT, $desc, VALUE_DEFAULT, 0);

        // Grades.
        $desc = 'mixed $grades grade (object, array) or several grades '.
                '(arrays of arrays or objects), NULL if updating grade_item definition only';
        $params['grades'] = new external_value(PARAM_TEXT, $desc, VALUE_DEFAULT, 'NULL');

        // Item details.
        $desc = 'mixed $itemdetails object or array describing the grading item, NULL if no change';
        $params['itemdetails'] = new external_value(PARAM_TEXT, $desc, VALUE_DEFAULT, 'NULL');

        return new external_function_parameters($params);
    }

    /**
     * Returns description of method result value.
     *
     * @return external_description
     */
    public static function update_grade_returns() {
        return new external_value(PARAM_TEXT, '0 for success anything else for failure');
    }

    // GET GRADE.
    /**
     * Returns grade item info and grades.
     *
     * @param string $source
     * @param string $courseid
     * @param string $itemtype
     * @param string $itemmodule
     * @param string $iteminstance
     * @param string $itemnumber
     * @param string $grades
     * @param string $itemdetails
     * @return mixed
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function get_grade($source = 'mhaairs', $courseid ='',
                            $itemtype = self::ITEM_DEFAULT_TYPE, $itemmodule = self::ITEM_DEFAULT_MODULE,
                            $iteminstance = '0', $itemnumber = '0',
                            $grades = null, $itemdetails = null) {
        global $USER;

        $result = array();

        // Gradebook sync must be enabled by admin in the block's site configuration.
        if (!$syncgrades = get_config('core', 'block_mhaairs_sync_gradebook')) {
            return $result;
        }

        // Parameters validation.
        $params = self::validate_parameters(
            self::update_grade_parameters(),
            array(
                'source' => $source,
                'courseid' => $courseid,
                'itemtype' => $itemtype,
                'itemmodule' => $itemmodule,
                'iteminstance' => $iteminstance,
                'itemnumber' => $itemnumber,
                'grades' => $grades,
                'itemdetails' => $itemdetails,
            )
        );

        // Extract the validated parameters to their respective variables.
        foreach ($params as $var => $value) {
            ${$var} = $value;
        }

        // Context validation.
        // OPTIONAL but in most web service it should be present.
        $context = context_user::instance($USER->id);
        self::validate_context($context);

        // Capability checking.
        // OPTIONAL but in most web service it should be present.
        require_capability('moodle/user:viewdetails', $context, null, true, 'cannotviewprofile');

        // Validate item details.
        $itemdetails = json_decode(urldecode($itemdetails), true);
        $itemdetails = self::validate_item_details($itemdetails);

        // Get the item details identity type variable.
        $identitytype = self::get_details_itentity_type($itemdetails);

        // Validate grades.
        $grades = json_decode(urldecode($grades), true);
        $grades = self::validate_grades($grades);
        // HACK Make sure grades has identity type; take from item details if must.
        if ($grades and !isset($grades['identity_type'])) {
            $grades['identity_type'] = null;
            if ($identitytype) {
                $grades['identity_type'] = $identitytype;
            }
        }

        // Get the course.
        $course = self::get_course($courseid, $identitytype);
        if ($course === false) {
            // No valid course specified.
            return GRADE_UPDATE_FAILED;
        }
        $courseid = $course->id;

        // Get the grade item.
        $gitem = self::get_grade_item(
            $source,
            $courseid,
            self::ITEM_DEFAULT_TYPE,
            self::ITEM_DEFAULT_MODULE,
            $iteminstance,
            $itemnumber
        );
        if (!$gitem) {
            return $result;
        }

        // Prepare result.
        $result = array(
            'item' => array(
                'courseid' => $courseid,
                'categoryid' => $gitem->categoryid,
                'itemname' => $gitem->itemname,
                'itemtype' => $gitem->itemtype,
                'idnumber' => $gitem->idnumber,
                'gradetype' => $gitem->gradetype,
                'grademax' => $gitem->grademax,
            ),
            'grades' => array(),
        );

        if ($grades) {
            if (!empty($grades['userid'])) {
                $gradegrades = grade_grade::fetch_users_grades($gitem, array($grades['userid']), false);
            } else {
                $gradegrades = grade_grade::fetch_all(array('itemid' => $gitem->id));
            }
            if ($gradegrades) {
                foreach ($gradegrades as $grade) {
                    $result['grades'][] = array(
                        'userid' => $grade->userid,
                        'grade' => $grade->finalgrade,
                    );
                }
            }
        }

        return $result;
    }

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function get_grade_parameters() {
        return self::update_grade_parameters();
    }

    /**
     * Returns description of method result value.
     *
     * @return external_description
     */
    public static function get_grade_returns() {
        return new external_single_structure(
            array(
                'item' => new external_single_structure(
                    array(
                        'courseid' => new external_value(PARAM_INT, 'Course id'),
                        'categoryid' => new external_value(PARAM_INT, 'Grade category id'),
                        'itemname' => new external_value(PARAM_RAW, 'Item name'),
                        'itemtype' => new external_value(PARAM_RAW, 'Item type'),
                        'idnumber' => new external_value(PARAM_INT, 'Course id'),
                        'gradetype' => new external_value(PARAM_INT, 'Grade type'),
                        'grademax' => new external_value(PARAM_FLOAT, 'Maximum grade'),
                    ), 'An array of items associated with the grade item', VALUE_OPTIONAL
                ),
                'grades' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'userid' => new external_value(PARAM_INT, 'Student ID'),
                            'grade' => new external_value(PARAM_FLOAT, 'Student grade'),
                        )
                    ), 'An array of grades associated with the grade item', VALUE_OPTIONAL
                ),
            )
        );
    }

    // DEPRACATED: GRADEBOOKSERVICE.
    /**
     * Allows external services to push grades into the course gradebook.
     * Alias for {@link block_mhaairs_gradebookservice_external::update_grade()}.
     *
     * @return mixed
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function gradebookservice($source = 'mhaairs', $courseid ='',
                            $itemtype = self::ITEM_DEFAULT_TYPE, $itemmodule = self::ITEM_DEFAULT_MODULE,
                            $iteminstance = '0', $itemnumber = '0',
                            $grades = null, $itemdetails = null) {
        return self::update_grade(
            $source,
            $courseid,
            $itemtype,
            $itemmodule,
            $iteminstance,
            $itemnumber,
            $grades,
            $itemdetails
        );
    }

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function gradebookservice_parameters() {
        return self::update_grade_parameters();
    }

    /**
     * Returns description of method result value.
     *
     * @return external_description
     */
    public static function gradebookservice_returns() {
        return self::update_grade_returns();
    }

    // UTILITY.
    /**
     * Creates/updates/delete an mhaairs grade item.
     *
     * @param string $source
     * @param string $courseid
     * @param string $iteminstance
     * @param string $itemnumber
     * @param string $itemdetails
     * @return mixed
     * @throws moodle_exception
     */
    protected static function update_grade_item($source, $courseid, $iteminstance, $itemnumber, $itemdetails) {
        // Create/update/delete the grade item if needed.
        $gitem = self::get_grade_item(
            $source,
            $courseid,
            self::ITEM_DEFAULT_TYPE,
            self::ITEM_DEFAULT_MODULE,
            $iteminstance,
            $itemnumber,
            $itemdetails
        );

        // Are we deleting the item?
        $isdeleting = self::is_deleting($itemdetails);

        // No grade item is either successful deletion of failure on creation.
        if (!$gitem) {
            if (!$isdeleting) {
                return GRADE_UPDATE_FAILED;
            } else {
                return GRADE_UPDATE_OK;
            }
        }

        // We might fail to delete.
        if ($gitem and $isdeleting) {
            return GRADE_UPDATE_FAILED;
        }
        return GRADE_UPDATE_OK;
    }

    /**
     * Updates user grade in an mhaairs grade item.
     *
     * @param string $source
     * @param string $courseid
     * @param string $iteminstance
     * @param string $itemnumber
     * @param string $grades
     * @return mixed
     * @throws moodle_exception
     */
    protected static function update_user_grade($source, $courseid, $iteminstance, $itemnumber, $grades) {
        // Userid and rawgrade must be set.
        if (!isset($grades['userid']) or !isset($grades['rawgrade'])) {
            return GRADE_UPDATE_FAILED;
        }

        // Get the grade item.
        $gitem = self::get_grade_item(
            $source,
            $courseid,
            self::ITEM_DEFAULT_TYPE,
            self::ITEM_DEFAULT_MODULE,
            $iteminstance,
            $itemnumber
        );

        // Verify grade item exists.
        if (!$gitem) {
            return GRADE_UPDATE_FAILED;
        }

        // Update the user grade.
        if (!$gitem->update_final_grade($grades['userid'], $grades['rawgrade'], $source)) {
            return GRADE_UPDATE_FAILED;
        }

        return GRADE_UPDATE_OK;
    }

    /**
     * Cleans the item details data.
     *
     * @param array $itemdetails An array of item details and flags.
     * @throws invalid_parameter_exception if $param is not of given type
     * @return array|null
     */
    protected static function validate_item_details($itemdetails) {
        if (!$itemdetails or $itemdetails == "null") {
            return null;
        }

        // The following are the variables that can be passed via itemdetails.
        // We ignore any other variables.
        $allowed = array(
            'itemname' => PARAM_TEXT,
            'idnumber' => PARAM_TEXT,
            'gradetype' => PARAM_INT,
            'grademax' => PARAM_FLOAT,
            'hidden' => PARAM_INT,
            'deleted' => PARAM_INT,
            'categoryid' => PARAM_TEXT,
            'identity_type' => PARAM_ALPHA,
            'needsupdate' => PARAM_INT,
            'useexisting' => PARAM_INT,
        );

        $details = array();
        // Check type of each parameter.
        foreach ($allowed as $var => $type) {
            if (isset($itemdetails[$var]) and $itemdetails[$var] !== '') {
                $details[$var] = validate_param($itemdetails[$var], $type);
            }
        }

        // Remove empty deleted, b/c it grade_item cannot process it.
        if (empty($details['deleted'])) {
            unset($details['deleted']);
        }

        // Remove empty gradetype, so that it takes default.
        if (empty($details['gradetype'])) {
            unset($details['gradetype']);
        }

        // Remove empty grademax, so that it takes default.
        if (empty($details['grademax'])) {
            unset($details['grademax']);
        }

        return $details;
    }

    /**
     * Cleans the grades data and maps the userid to the internal id.
     *
     * @param array $grades An array user grade details.
     * @throws invalid_parameter_exception if $param is not of given type
     * @return array|null
     */
    protected static function validate_grades($grades) {
        global $DB;

        if (!$grades or $grades == "null") {
            return null;
        }

        // The following are the variables that can be passed via grades.
        // We ignore any other variables.
        $allowed = array(
            'userid' => PARAM_TEXT,
            'rawgrade' => PARAM_FLOAT,
            'identity_type' => PARAM_ALPHA,
        );

        $details = array();
        // Check type of each parameter.
        foreach ($allowed as $var => $type) {
            if (isset($grades[$var]) and $grades[$var] !== '') {
                $details[$var] = validate_param($grades[$var], $type);
            }
        }

        // Map userID to numerical userID if required.
        $idtype = !empty($details['identity_type']) ? $details['identity_type'] : null;
        if (!$idtype or ($idtype != 'lti')) {
            $userid = $DB->get_field('user', 'id', array('username' => $details['userid']));
            if ($userid !== false) {
                $details['userid'] = $userid;
            }
        }

        return $details;
    }

    /**
     * Returns a grade category with the specified by name.
     * If the category does not exist it is created.
     * If the category exists any duplicates are deleted (using the locks).
     * We have to be carefull about MDL-37055 and make sure
     * grade categories and grade items are in order.
     *
     * @param string $catnam
     * @param int $courseid
     * @return grade_category|null
     */
    protected static function get_grade_category($catname, $courseid) {
        global $CFG;

        if (!$catname) {
            return null;
        }

        require_once($CFG->dirroot.'/blocks/mhaairs/lib/lock/abstractlock.php');

        $category = null;

        $instance = new block_mhaairs_locinst();

        // Fetch all grade category items that match the target grade category by fullname.
        // If we have more than one then we need to delete the duplicates.
        $fetchparams = array(
            'fullname' => $catname,
            'courseid' => $courseid
        );
        $categories = grade_category::fetch_all($fetchparams);

        // If the category exists we use it.
        if ($categories) {
            // The first is our target category.
            $category = array_shift($categories);

            // We delete any duplicates.
            if ($categories) {
                if ($instance->lock()->locked()) {
                    // We have exclusive lock so let's do it.
                    try {
                        foreach ($categories as $cat) {
                            if ($cat->set_parent($category->id)) {
                                $cat->delete();
                            }
                        }
                    } catch (Exception $e) {
                        // If we fail there is not much else we can do here.
                    }
                }
            }

        } else {

            // If the category does not exist we create it.
            $gradeaggregation = get_config('core', 'grade_aggregation');
            if ($gradeaggregation === false) {
                $gradeaggregation = GRADE_AGGREGATE_WEIGHTED_MEAN2;
            }
            // Parent category is automatically added(created) during insert.
            $catparams = array(
                'fullname' => $catname,
                'courseid' => $courseid,
                'hidden' => false,
                'aggregation' => $gradeaggregation,
            );
            $category = new grade_category($catparams, false);
            $category->id = $category->insert();
        }

        return $category;
    }

    /**
     * Returns course object by id or idnumber, or false if not found.
     *
     * @param mixed $courseid
     * @param bool $idtype
     * @return false|stdClass
     */
    private static function get_course($courseid, $idtype = null) {
        global $DB;

        $course = false;
        $where = array();
        $params = array();

        // We must have course id.
        if (empty($courseid)) {
            return false;
        }

        // Do we need to look up the course only by internal id?
        $idonly = $idtype ? in_array($idtype, array('internal', 'lti'), true) : false;

        // If courseid is numeric we search by course id.
        if (is_numeric($courseid) and $courseid > 0) {
            $where[] = 'id = ?';
            $params[] = (int) $courseid;
        }

        // We search also by the course idnumber if required.
        if (!$idonly) {
            $where[] = 'idnumber = ?';
            $params[] = $courseid;
        }

        // Fetch the course record.
        if (!empty($where)) {
            $select = implode(' OR ', $where);
            $course = $DB->get_record_select('course', $select, $params, '*', IGNORE_MULTIPLE);
        }

        return $course;
    }

    /**
     * Returns a grade item with the specified data.
     * If the item does not exist it is created.
     *
     * @param  string $source
     * @param  int $courseid        Course id
     * @param  string $itemtype     Item type
     * @param  string $itemmodule   Item module
     * @param  int $iteminstance    Item instance
     * @param  int $itemnumber      Item number
     * @param  array $itemdetails   Item details
     * @return grade_item           A grade_item instance
     */
    private static function get_grade_item($source, $courseid, $itemtype, $itemmodule,
            $iteminstance = 0, $itemnumber = 0, $itemdetails = null) {
        global $DB;

        // TODO: Do we need to fetch course grade item?
        if ($itemtype == 'course') {
            // We are looking for the course grade item,
            // so just return if exists.
            $params = array(
                'courseid' => $courseid,
                'itemtype' => $itemtype,
            );

            return grade_item::fetch($params);
        }

        // Must have item instance.
        if (empty($iteminstance)) {
            return null;
        }

        // The target item params.
        $itemparams = array(
            'courseid' => $courseid,
            'itemtype' => $itemtype,
            'itemmodule' => $itemmodule,
            'iteminstance' => $iteminstance,
            'itemnumber' => $itemnumber,
        );

        // If there are no item details, we just need the item if exists.
        if (!$itemdetails) {
            return grade_item::fetch($itemparams);
        }

        // We are looking for an mhaairs manual item.
        // CONTRIB-5863 - Migrate old mod/quiz grade items to manual/mhaairs.
        if ($itemtype == self::ITEM_DEFAULT_TYPE) {
            // Does a mod quiz item exist for the requested iteminstance?
            $params = array(
                'courseid' => $courseid,
                'itemtype' => 'mod',
                'itemmodule' => 'quiz',
                'iteminstance' => $iteminstance,
                'itemnumber' => $itemnumber,
            );
            if ($gitem = grade_item::fetch($params)) {
                // There is such an item but we need to ensure first that
                // it is not an actual quiz instance in the course.
                $quizparams = array('id' => $iteminstance, 'course' => $courseid);
                if (!$quizexists = $DB->record_exists('quiz', $quizparams)) {
                    // There is no such quiz instance in the course
                    // and we can safely convert to an mhaairs item.
                    $gitem->itemtype = $itemtype;
                    $gitem->itemmodule = $itemmodule;
                    $gitem->update();
                }
            }
        }

        // We may need to create/update/delete the item.
        $useexisting = self::is_using_existing($itemdetails);
        $isdeleting = self::is_deleting($itemdetails);
        $itemname = self::get_details_item_name($itemdetails);
        $catname = self::get_details_category_name($itemdetails);
        // Remove the category id b/c it's not yet a valid value for the item.
        unset($itemdetails['categoryid']);

        // Try to use existing if applicable.
        $existing = false;
        if ($useexisting) {
            if ($itemname) {
                $gitems = null;

                $params = array(
                    'courseid' => $courseid,
                    'itemtype' => self::ITEM_DEFAULT_TYPE,
                    'itemmodule' => self::ITEM_DEFAULT_MODULE,
                    'itemname' => $itemname,
                );

                // Try to fetch all mhaairs items with that name.
                if (!$gitems = grade_item::fetch_all($params)) {
                    // No mhaairs items so try any manual items.
                    unset($params['itemmodule']);
                    $params['itemtype'] = 'manual';
                    $gitems = grade_item::fetch_all($params);
                }

                if ($gitems) {
                    // Take the first item.
                    $gitem = reset($gitems);
                    $gitem->itemmodule = $itemmodule;
                    $gitem->iteminstance = $iteminstance;
                    $gitem->itemnumber = $itemnumber;
                    $gitem->update();
                    $existing = true;
                }
            }
        } else {
            // Fetch the item.
            $gitem = grade_item::fetch($itemparams);
        }

        // Create/update/delete the item.
        if ($itemdetails and (!$gitem or !$existing)) {
            // If creating, must have item name; the rest can default.
            if (!$isdeleting and !$itemname) {
                return null;
            }

            $result = grade_update(
                $source,
                $courseid,
                $itemtype,
                $itemmodule,
                $iteminstance,
                $itemnumber,
                null,
                $itemdetails
            );

            if ($result != GRADE_UPDATE_OK) {
                return null;
            }

            if (!$gitem = grade_item::fetch($itemparams)) {
                return null;
            }

            // Add the item to the specified category if applicable.
            if ($catname) {
                $category = null;
                $category = self::get_grade_category($catname, $courseid);

                // Optional.
                try {
                    $gitem->categoryid = $category->id;
                    $gitem->update();
                } catch (Exception $e) {
                    // Silence the exception.
                }
            }
        }

        return $gitem;
    }

    /**
     * Returns the requested identity type from the item details or null if not provided.
     *
     * @param  array $itemdetails
     * @return string|null
     */
    private static function get_details_itentity_type($itemdetails) {
        if (!empty($itemdetails['identity_type'])) {
            return $itemdetails['identity_type'];
        }
        return null;
    }

    /**
     * Returns the requested item name from the item details or null if not provided.
     *
     * @param  array $itemdetails
     * @return string|null
     */
    private static function get_details_item_name($itemdetails) {
        if (!empty($itemdetails['itemname'])) {
            return $itemdetails['itemname'];
        }
        return null;
    }

    /**
     * Returns the requested category id from the item details or null if not provided.
     *
     * @param  array $itemdetails
     * @return string|null
     */
    private static function get_details_category_name($itemdetails) {
        if (!empty($itemdetails['categoryid'])) {
            return $itemdetails['categoryid'];
        }
        return null;
    }

    /**
     * Returns true if 'deleted' parameter is not empty in item details; false otherwise.
     *
     * @param  array $itemdetails
     * @return bool
     */
    private static function is_deleting($itemdetails) {
        return !empty($itemdetails['deleted']);
    }

    /**
     * Returns true if 'useexisting' parameter is not empty in item details; false otherwise.
     *
     * @param  array $itemdetails
     * @return bool
     */
    private static function is_using_existing($itemdetails) {
        return !empty($itemdetails['useexisting']);
    }

}

/**
 * Block mhaairs util web service.
 *
 * @package     block_mhaairs
 * @copyright   2014 Itamar Tzadok <itamar@substantialmethods.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_mhaairs_utilservice_external extends external_api {

    /**
     * Allows external applications to retrieve the environment info.
     *
     * @return
     */
    public static function get_environment_info() {
        // Get the environment info.
        $result = MHUtil::get_environment_info();
        return $result;
    }

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function get_environment_info_parameters() {
        return new external_function_parameters(array());
    }

    /**
     * Returns description of method result value.
     *
     * @return external_description
     */
    public static function get_environment_info_returns() {
        return new external_single_structure(
            array(
                'system' => new external_value(PARAM_TEXT, 'Operating system'),
                'server' => new external_value(PARAM_TEXT, 'Server api'),
                'phpversion' => new external_value(PARAM_TEXT, 'PHP version'),
                'dbvendor' => new external_value(PARAM_TEXT, 'DB vendor'),
                'dbversion' => new external_value(PARAM_TEXT, 'DB version'),
                'moodleversion' => new external_value(PARAM_TEXT, 'Moodle version'),
                'pluginversion' => new external_value(PARAM_TEXT, 'Plugin version'),
            )
        );
    }

    /**
     * Allows external applications to retrieve MHUserInfo by token.
     *
     * @param string $token
     * @return MHUserInfo object
     */
    public static function get_user_info($token, $identitytype = null) {
        // Require secured connection.
        if ($error = self::require_ssl()) {
            $userinfo = new MHUserInfo(MHUserInfo::FAILURE);
            $userinfo->message = $error;

            return $userinfo;
        }

        // Get the configured secret.
        $secret = self::get_secret();

        // Get the user info.
        $result = MHUtil::get_user_info($token, $secret, $identitytype);

        return $result;
    }

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function get_user_info_parameters() {
        $params = array();

        // Token.
        $desc = 'string $token Token';
        $params['token'] = new external_value(PARAM_TEXT, $desc);

        // Identity type.
        $desc = 'string $identitytype Indicates the user search var; if \'internal\' the user is searched by id;'.
                ' if anything else or empty, the user is searched by username.';
        $params['identitytype'] = new external_value(PARAM_TEXT, $desc, VALUE_DEFAULT, null);

        return new external_function_parameters($params);
    }

    /**
     * Returns description of method result value.
     *
     * @return external_description
     */
    public static function get_user_info_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_INT, 'Result status: 0|1 (SUCCESS|FAILURE).'),
                'user' => new external_single_structure(
                    array(
                        'id' => new external_value(PARAM_INT, 'User id'),
                        'deleted' => new external_value(PARAM_INT, 'Deleted'),
                        'suspended' => new external_value(PARAM_INT, 'Suspended'),
                        'username' => new external_value(PARAM_RAW, 'Username'),
                        'idnumber' => new external_value(PARAM_RAW, 'Id number'),
                        'firstname' => new external_value(PARAM_RAW, 'First name'),
                        'lastname' => new external_value(PARAM_RAW, 'Last name'),
                        'email' => new external_value(PARAM_RAW, 'Email'),
                        'timezone' => new external_value(PARAM_RAW, 'Time zone'),
                    ), 'An array of user info', VALUE_OPTIONAL
                ),
                'courses' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Course id'),
                            'category' => new external_value(PARAM_INT, 'Category id'),
                            'fullname' => new external_value(PARAM_RAW, 'Full name'),
                            'shortname' => new external_value(PARAM_RAW, 'Short name'),
                            'idnumber' => new external_value(PARAM_RAW, 'Id number'),
                            'visible' => new external_value(PARAM_INT, 'Visible'),
                            'rolename' => new external_value(PARAM_RAW, 'User role'),
                        )
                    ), 'An array of courses the user is enrolled in', VALUE_OPTIONAL
                ),
                'message' => new external_value(PARAM_TEXT, 'Error message on failure; empty on success.'),
            )
        );
    }

    /**
     * Allows external services to push grades into the course gradebook.
     * Alias for {@link block_mhaairs_gradebookservice_external::update_grade()}.
     *
     * @return mixed
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function validate_login($token, $username, $password) {
        // Require secured connection.
        if ($error = self::require_ssl()) {
            $authresult = new MHAuthenticationResult(
                MHAuthenticationResult::FAILURE,
                '',
                $error
            );

            return $authresult;
        }

        // Get the configured secret.
        $secret = self::get_secret();

        // Validate login.
        $result = MHUtil::validate_login($token, $secret, $username, $password);

        return $result;
    }

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function validate_login_parameters() {
        $params = array();

        // Token.
        $desc = 'string $token Token';
        $params['token'] = new external_value(PARAM_TEXT, $desc);

        // Username.
        $desc = 'string $username Username';
        $params['username'] = new external_value(PARAM_TEXT, $desc);

        // Password.
        $desc = 'string $password Password';
        $params['password'] = new external_value(PARAM_TEXT, $desc);

        return new external_function_parameters($params);
    }

    /**
     * Returns description of method result value.
     *
     * @return external_description
     */
    public static function validate_login_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_INT, 'Result status: 0|1 (SUCCESS|FAILURE).'),
                'effectiveuserid' => new external_value(PARAM_TEXT, 'The validated user username.'),
                'redirecturl' => new external_value(PARAM_TEXT, 'Error message on failure; empty on success.'),
                'attributes' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                        )
                    )
                ),
                'message' => new external_value(PARAM_TEXT, 'Error message on failure; empty on success.'),
            )
        );
    }

    /**
     * Checks if the plugin is configured to require ssl connection and verifies https connection
     * if needed. Returns null on success, and error message on failure (http access when ssl required).
     *
     * @return string|null
     */
    private static function require_ssl() {
        $notsecured = 'error: connection must be secured with SSL';
        $sslonly = get_config('core', 'block_mhaairs_sslonly');

        // Required only if enabled by admin.
        if (!$sslonly) {
            return null;
        }

        // No https, not secured.
        if (!isset($_SERVER['HTTPS'])) {
            return $notsecured;
        }

        $secured = filter_var($_SERVER['HTTPS'], FILTER_SANITIZE_STRING);
        if (empty($secured)) {
            return $notsecured;
        }

        return null;
    }

    /**
     * Returns the plugin configured shared secret.
     *
     * @return string
     */
    private static function get_secret() {
        if ($secret = get_config('core', 'block_mhaairs_shared_secret')) {
            return $secret;
        }

        return '';
    }

}
