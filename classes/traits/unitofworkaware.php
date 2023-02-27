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
 * Trait for getting data from Moodle persistent objects.
 *
 * @package    local_moodleorm
 * @copyright  2021 Andrew Caya <andrewscaya@yahoo.ca>
 * @author     Andrew Caya <andrewscaya@yahoo.ca>
 * @license    https://directory.fsf.org/wiki/License:Apache-2.0 Apache v2
 */

namespace local_moodleorm\traits;

/**
 * Trait to allow access to the data payload of Moodle persistent objects.
 */
trait unitofworkaware {

    /**
     * Getter method for Moodle persistent objects.
     *
     * @return object|bool
     */
    public function get_data() {
        $reflection = new \ReflectionClass($this);
        $propertiesraw = $reflection->getParentClass()->getProperties();

        foreach ($propertiesraw as $property) {
            if ($property->getName() === 'data') {
                $property->setAccessible(true);

                return (object) $property->getValue($this);
            }
        }

        return false;
    }
}
