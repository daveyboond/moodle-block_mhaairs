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
 * Redis locking class
 * Expects phpredis extension to be present and installed
 * We strongly recommend to use phpredis 2.2.4 or more recent
 *
 * @package    block_mhaairs
 * @copyright  2014 Moodlerooms inc.
 * @author     Darko Miletic <dmiletic@moodlerooms.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @see https://github.com/nicolasff/phpredis phpredis home page
 * @see http://windows.php.net/downloads/pecl/releases/redis/ Windows build of phpredis extension
 * @see http://windows.php.net/downloads/pecl/snaps/redis/ Windows build snapshots of phpredis extension
 */

defined('MOODLE_INTERNAL') or die();

global $CFG;
require_once($CFG->dirroot.'/blocks/mhaairs/lib/lock/abstractlock.php');

class block_mhaairs_redis {
    /**
     * @var null|Redis
     */
    private $_server = null;

    /**
     * @var null|string
     */
    private $_connection = null;

    public function __construct($timeout = 2, $port = 6379) {
        if (!class_exists('Redis')) {
            throw new Exception('Redis class not found, Redis PHP Extension is probably not installed');
        }
        $server = get_config('core', 'local_mr_redis_server');
        if (empty($server)) {
            throw new Exception('Redis connection string is not configured in $CFG->local_mr_redis_server');
        }

        $this->_server     = new Redis();
        $this->_connection = $server;
        if (!$this->_server->connect($this->_connection, $port, $timeout)) {
            throw new Exception('Unable to connect to the server!');
        }
    }

    public function __destruct() {
        $this->_server->close();
    }

    /**
     * @return Redis
     */
    public function get() {
        return $this->_server;
    }
}

class block_mhaairs_redislock extends block_mhaairs_lock_abstract {
    const LOCKNAME = 'block_mhaairs';

    /**
     * @var null|string
     */
    private $keyname = null;

    /**
     * @var bool
     */
    private $locked = false;

    /**
     * @param bool $lock
     * @throws Exception
     */
    public function __construct($lock = true) {
        $this->keyname = sprintf('%s_%s', get_config('core', 'dbname'), self::LOCKNAME);

        if ($lock) {
            $this->lock();
        }
    }
    /**
     * @return bool
     */
    public function locked() {
        return $this->locked;
    }

    /**
     * @param bool $force
     * @return bool
     */
    public function unlock($force = false) {
        $result = 1;
        try {
            if ($force || $this->locked()) {
                $redis = new block_mhaairs_redis();
                $result = $redis->get()->delete($this->keyname);
                $this->locked = ($result == 0);
            }
        } catch (RedisException $e) {
            debugging("RedisException caught on host {$this->get_hostname()} with message: {$e->getMessage()}");
        } catch (Exception $e) {
            debugging("Redis lock denied on host {$this->get_hostname()}, Redis locking disabled because {$e->getMessage()}.");
        }
        return ($result == 1);
    }

    public function lock() {
        if ($this->locked()) {
            return true;
        }
        try {
            $redis = new block_mhaairs_redis();
            $this->locked = $redis->get()->setnx($this->keyname, $this->get_lock_value());
        } catch (RedisException $e) {
            echo 'Exception A!', PHP_EOL;
            debugging("RedisException caught on host {$this->get_hostname()} with message: {$e->getMessage()}");
        } catch (Exception $e) {
            echo 'Exception B!', PHP_EOL;
            debugging("Redis lock denied on host {$this->get_hostname()}, Redis locking disabled because {$e->getMessage()}.");
        }

        return $this->locked();
    }

    public function get_lock_value() {
        return http_build_query(array(
            'timetolive' => 0,
            'hostname' => gethostname(),
            'processid' => getmypid(),
        ), null, '&');
    }

    protected function get_hostname() {
        if (($hostname = gethostname()) === false) {
            $hostname = 'UNKOWN';
        }
        return $hostname;
    }

}
