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
 * Base locking classes and use code
 *
 * @package     block_mhaairs
 * @copyright   2014 Moodlerooms inc.
 * @author      Darko Miletic <dmiletic@moodlerooms.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') or die();

/**
 * Class block_mhaairs_lock_abstract
 * Abstract class for all implemented locks
 *
 */
abstract class block_mhaairs_lock_abstract {

    /**
     * @return bool
     */
    abstract public function locked();

    /**
     * @param bool $force
     * @return bool
     */
    abstract public function unlock($force = false);

    /**
     * @return bool
     */
    abstract public function lock();
}

class block_mhaairs_locinst {
    private $lock = null;

    /**
     * @param string $locktype
     * @param bool $autolock
     * @return null|block_mhaairs_lock_abstract
     */
    public static function getlock($locktype, $autolock=true) {
        global $CFG;
        require_once($CFG->dirroot."/blocks/mhaairs/lib/lock/{$locktype}.php");
        $class = "block_mhaairs_{$locktype}";
        if (class_exists($class)) {
            $lock = new $class($autolock);
            return $lock;
        }

        return null;
    }

    /**
     * @param bool $autolock
     */
    public function __construct($autolock = true) {
        $lock = self::getlock(get_config('core', 'block_mhaairs_locktype'), $autolock);
        if ($lock !== null) {
            $this->lock = $lock;
            // Try to obtain lock for 60s.
            $count = 0;
            while (($count < 30) && !$this->lock->locked()) {
                sleep(2);
                $this->lock->lock();
                $count++;
            }
        }
    }

    public function __destruct() {
        if ($this->lock !== null) {
            $this->lock->unlock();
        }
    }

    private function __clone() {

    }

    /**
     * @return block_mhaairs_lock_abstract|null
     */
    public function lock() {
        return $this->lock;
    }
}
