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
 * Block MHAAIRS Improved
 *
 * @package    block_mhaairs
 * @copyright  2013 Moodlerooms inc.
 * @author     Teresa Hardy <thardy@moodlerooms.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_block_mhaairs_upgrade($oldversion = 0) {
    global $DB;

    $result = true;

    if ($result && $oldversion < 2011091314) {
        // Check for multiple instances of mhaairs blocks in all courses.
        $blockname = 'mhaairs';
        $tbl = 'block_instances';
        $sql = 'SELECT distinct parentcontextid FROM {block_instances} WHERE blockname = :blockname';
        $instances = $DB->get_records_sql($sql, array('blockname' => $blockname));
        if (!empty($instances)) {
            $deletearr = array();
            foreach ($instances as $instance) {
                $params = array('parentcontextid' => $instance->parentcontextid, 'blockname' => $blockname);
                $recs = $DB->get_records($tbl, $params, '', 'id');

                $inst = 1;  // Helps mark first instance, which we will always keep.

                foreach ($recs as $record) {
                    $id = $record->id;
                    $newvalue = "";  // Set configdata to empty string.

                    if ($inst == 1) {
                        $DB->set_field($tbl, 'configdata', $newvalue, array('blockname' => $blockname));
                        $inst++;
                    } else {
                        // Delete list.
                        $deletearr[] = $id;
                    }
                }
                try {
                    try {
                        $transaction = $DB->start_delegated_transaction();
                        $DB->delete_records_list($tbl, 'id', $deletearr);
                        $transaction->allow_commit();
                    } catch (Exception $e) {
                        if (!empty($transaction) && !$transaction->is_disposed()) {
                            $transaction->rollback($e);
                        }
                    }
                } catch (Exception $e) {
                    $result = false;
                }
            }
        }
    }

    return $result;
}
