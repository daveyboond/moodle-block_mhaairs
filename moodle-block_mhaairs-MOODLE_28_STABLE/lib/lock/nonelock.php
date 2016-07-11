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
 * Dummy locking class when no locking is selected.
 *
 * @package    block_mhaairs
 * @copyright  2014 Moodlerooms inc.
 * @author     Darko Miletic <dmiletic@moodlerooms.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') or die();

global $CFG;
require_once($CFG->dirroot.'/blocks/mhaairs/lib/lock/abstractlock.php');

class block_mhaairs_nonelock extends block_mhaairs_lock_abstract {

    /**
     * @return bool
     */
    public function locked() {
        return true;
    }

    /**
     * @param bool $force
     * @return bool
     */
    public function unlock($force = false) {
        return true;
    }

    /**
     * @return bool
     */
    public function lock() {
        return true;
    }
}
