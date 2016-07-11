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
 * Block MHAAIRS version
 *
 * @package block_mhaairs
 * @copyright 2015 Itamar Tzadok {@link http://substantialmethods.com}
 * @copyright 2013-2014 Moodlerooms inc.
 * @author Teresa Hardy <thardy@moodlerooms.com>
 * @author Darko Miletic <dmiletic@moodlerooms.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version = 2015051104;
$plugin->release = '2.9.4';
$plugin->requires = 2015051100;
$plugin->cron = 0;
$plugin->component = 'block_mhaairs';
$plugin->maturity  = MATURITY_STABLE;