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
 * Class for circuit persistence.
 *
 * @package    local_moodleorm
 * @category   test
 * @copyright  2021 Andrew Caya <andrewscaya@yahoo.ca>
 * @author     Andrew Caya <andrewscaya@yahoo.ca>
 * @license    https://directory.fsf.org/wiki/License:Apache-2.0 Apache v2
 */

namespace local_moodleorm\tests;

use \core\persistent;
use \local_moodleorm\traits\unitofworkaware;

/**
 * Class for loading/storing a circuit from the DB.
 *
 * @package    local_moodleorm
 * @category   test
 * @copyright  2021 Andrew Caya <andrewscaya@yahoo.ca>
 * @author     Andrew Caya <andrewscaya@yahoo.ca>
 * @license    https://directory.fsf.org/wiki/License:Apache-2.0 Apache v2
 */
class circuit extends persistent {

    use unitofworkaware;

    /** Table name */
    const TABLE = 'simulation_circuit';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() {
        return array(
            'id' => array(
                'type' => PARAM_INT,
            ),
            'name' => array(
                'type' => PARAM_TEXT,
            ),
            'description' => array(
                'type' => PARAM_TEXT,
                'default' => null,
                'null' => NULL_ALLOWED,
            ),
            'waveid' => array(
                'type' => PARAM_INT,
            ),
            'timestart' => array(
                'type' => PARAM_INT,
            ),
            'timeend' => array(
                'type' => PARAM_INT,
            ),
            'timecreated' => array(
                'type' => PARAM_INT,
                'default' => time(),
            ),
            'timemodified' => array(
                'type' => PARAM_INT,
                'default' => time(),
            ),
        );
    }
}
