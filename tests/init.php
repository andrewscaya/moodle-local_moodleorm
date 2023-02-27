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
 * Unitofwork tests.
 *
 * @package    local_moodleorm
 * @category   test
 * @copyright  2021 Andrew Caya <andrewscaya@yahoo.ca>
 * @author     Andrew Caya <andrewscaya@yahoo.ca>
 * @license    https://directory.fsf.org/wiki/License:Apache-2.0 Apache v2
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot
    . DIRECTORY_SEPARATOR
    . 'local'
    . DIRECTORY_SEPARATOR
    . 'moodleorm'
    . DIRECTORY_SEPARATOR
    . 'tests'
    . DIRECTORY_SEPARATOR
    . 'wave.php');

require_once($CFG->dirroot
    . DIRECTORY_SEPARATOR
    . 'local'
    . DIRECTORY_SEPARATOR
    . 'moodleorm'
    . DIRECTORY_SEPARATOR
    . 'tests'
    . DIRECTORY_SEPARATOR
    . 'circuit.php');

require_once($CFG->dirroot
    . DIRECTORY_SEPARATOR
    . 'local'
    . DIRECTORY_SEPARATOR
    . 'moodleorm'
    . DIRECTORY_SEPARATOR
    . 'tests'
    . DIRECTORY_SEPARATOR
    . 'station.php');

require_once($CFG->dirroot
    . DIRECTORY_SEPARATOR
    . 'local'
    . DIRECTORY_SEPARATOR
    . 'moodleorm'
    . DIRECTORY_SEPARATOR
    . 'tests'
    . DIRECTORY_SEPARATOR
    . 'substation.php');

require_once($CFG->dirroot
    . DIRECTORY_SEPARATOR
    . 'local'
    . DIRECTORY_SEPARATOR
    . 'moodleorm'
    . DIRECTORY_SEPARATOR
    . 'tests'
    . DIRECTORY_SEPARATOR
    . 'cmodule.php');

require_once($CFG->dirroot
    . DIRECTORY_SEPARATOR
    . 'local'
    . DIRECTORY_SEPARATOR
    . 'moodleorm'
    . DIRECTORY_SEPARATOR
    . 'tests'
    . DIRECTORY_SEPARATOR
    . 'module.php');
