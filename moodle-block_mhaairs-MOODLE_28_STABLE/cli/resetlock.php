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


define('CLI_SCRIPT', true);

require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
global $CFG;
require_once($CFG->libdir.'/clilib.php');
require_once($CFG->dirroot.'/blocks/mhaairs/lib/lock/abstractlock.php');

cli_heading('Running lock reset');

try {
    $instance = new block_mhaairs_locinst(false);
    $instance->lock()->unlock(true);
    echo 'Unlocked MHaairs lock!', PHP_EOL;
} catch (Exception $e) {
    cli_problem($e->getMessage());
}
