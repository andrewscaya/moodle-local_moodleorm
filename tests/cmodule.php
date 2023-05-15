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
 * Class for entity persistence.
 *
 * @package    local_moodleorm
 * @copyright  2021 Andrew Caya <andrewscaya@yahoo.ca>
 * @author     Andrew Caya <andrewscaya@yahoo.ca>
 * @license    https://directory.fsf.org/wiki/License:Apache-2.0 Apache v2
 */

namespace local_moodleorm\tests;

use \core\persistent;
use \local_moodleorm\traits\unitofworkaware;

/**
 * Class for loading/storing an entity from the database.
 *
 * @package    local_moodleorm
 * @copyright  2021 Andrew Caya <andrewscaya@yahoo.ca>
 * @author     Andrew Caya <andrewscaya@yahoo.ca>
 * @license    https://directory.fsf.org/wiki/License:Apache-2.0 Apache v2
 */
class cmodule extends persistent {

    use unitofworkaware;

    /** Table name */
    const TABLE = 'course_modules';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            'id' => [
                'type' => PARAM_INT,
            ],
            'course' => [
                'type' => PARAM_INT,
            ],
            'module' => [
                'type' => PARAM_INT,
            ],
            'instance' => [
                'type' => PARAM_INT,
            ],
            'section' => [
                'type' => PARAM_INT,
            ],
            'idnumber' => [
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
            ],
            'added' => [
                'type' => PARAM_INT,
                'default' => time(),
            ],
            'indent' => [
                'type' => PARAM_INT,
            ],
            'visible' => [
                'type' => PARAM_INT,
            ],
            'visiblecoursepage' => [
                'type' => PARAM_INT,
            ],
            'visibleold' => [
                'type' => PARAM_INT,
            ],
            'groupmode' => [
                'type' => PARAM_INT,
            ],
            'groupingid' => [
                'type' => PARAM_INT,
            ],
            'completion' => [
                'type' => PARAM_INT,
            ],
            'completiongradeitemnumber' => [
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
            ],
            'completionview' => [
                'type' => PARAM_INT,
            ],
            'completionexpected' => [
                'type' => PARAM_INT,
            ],
            'completionpassgrade' => [
                'type' => PARAM_INT,
            ],
            'showdescription' => [
                'type' => PARAM_INT,
            ],
            'availability' => [
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
            ],
            'deletioninprogress' => [
                'type' => PARAM_INT,
            ],
            'downloadcontent' => [
                'type' => PARAM_INT,
            ],
            'lang' => [
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
            ],
        ];
    }
}
