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
 * Version details.
 *
 * @package     local_moodleorm
 * @copyright   2021 Andrew Caya <andrewscaya@yahoo.ca>
 * @author      Andrew Caya
 * @license     https://directory.fsf.org/wiki/License:Apache-2.0 Apache v2
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version = 2022121501;        // The current module version.
$plugin->requires = 2015111601;
$plugin->component = 'local_moodleorm'; // Full name of the plugin (used for diagnostics).
$plugin->cron = 0;
$plugin->maturity = MATURITY_STABLE;
$plugin->release = 'MoodleORM Plugin version 1.0.0';
