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
class module extends persistent {

    use unitofworkaware;

    /** Table name */
    const TABLE = 'modules';

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
            'name' => [
                'type' => PARAM_TEXT,
            ],
            'cron' => [
                'type' => PARAM_INT,
            ],
            'lastcron' => [
                'type' => PARAM_INT,
            ],
            'search' => [
                'type' => PARAM_TEXT,
            ],
            'visible' => [
                'type' => PARAM_INT,
            ],
        ];
    }
}
