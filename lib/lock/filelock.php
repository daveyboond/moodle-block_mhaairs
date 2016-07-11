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
 * File locking classes
 * Note: This will not work on network shared file systems like NFS
 *
 * @package    block_mhaairs
 * @copyright  2014 Moodlerooms inc.
 * @author     Darko Miletic <dmiletic@moodlerooms.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') or die();

global $CFG;
require_once($CFG->dirroot.'/blocks/mhaairs/lib/lock/abstractlock.php');

class block_mhaairs_filelock extends block_mhaairs_lock_abstract {
    const LOCKFILE = 'mhaairslock';

    /**
     * @var null|resource
     */
    private $handle = null;
    /**
     * @var null|string
     */
    private $filepath = null;

    /**
     * @param bool $lock
     */
    public function __construct($lock = true) {
        if ($lock) {
            $this->lock();
        }
    }

    public function __destruct() {
        $this->unlock();
    }

    /**
     * @param bool $force
     * @return bool
     */
    public function unlock($force = false) {
        if ($force || $this->locked()) {
            if (flock($this->handle, LOCK_UN | LOCK_NB)) {
                fclose($this->handle);
                $this->handle = null;
                $this->filepath = null;
                return true;
            }
        }
        return false;
    }

    /**
     * @return bool
     */
    public function lock() {
        if ($this->locked()) {
            return false;
        }

        $tempdir = get_config('core', 'tempdir');
        $filepath = $tempdir.DIRECTORY_SEPARATOR.self::LOCKFILE;
        $handle = fopen($filepath, 'w+');
        $result = ($handle !== false);
        if ($result) {
            $result = flock($handle, LOCK_EX | LOCK_NB);
            if ($result) {
                $this->handle = $handle;
                $this->filepath = $filepath;
            } else {
                fclose($handle);
            }
        }
        return $result;
    }

    /**
     * @return bool
     */
    public function locked() {
        return ($this->handle !== null);
    }
}
