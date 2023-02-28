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

namespace local_moodleorm;

defined('MOODLE_INTERNAL') || die();

use \local_moodleorm\unitofwork;

global $CFG;

require_once($CFG->dirroot
    . DIRECTORY_SEPARATOR
    . 'local'
    . DIRECTORY_SEPARATOR
    . 'moodleorm'
    . DIRECTORY_SEPARATOR
    . 'tests'
    . DIRECTORY_SEPARATOR
    . 'init.php');

/**
 * Class of tests for the Unit of Work of persistence management.
 *
 * @package    local_moodleorm
 * @category   test
 * @copyright  2021 Andrew Caya <andrewscaya@yahoo.ca>
 * @author     Andrew Caya <andrewscaya@yahoo.ca>
 * @license    https://directory.fsf.org/wiki/License:Apache-2.0 Apache v2
 */
class unitofwork_test extends \advanced_testcase {

    /** Constant string Name of the main test table. */
    const MAIN_TABLE_NAME = 'simulation';

    /** @var classmap Array of arrays containing a classmap of persistent objects. */
    protected $classmap;

    /** @var db Instance of a fake moodle_database object. */
    protected $db;

    /** @var unitofwork Instance of a unitofwork object. */
    protected $unitofwork;

    /**
     * Setup test data.
     */
    public function setUp(): void {
        parent::setUp();

        global $CFG, $DB;

        $this->classmap = [
            'simulation_wave' => [
                'class_fqn' => '\local_moodleorm\tests\wave',
                'parent_class_fqn' => '',
            ],
            'simulation_circuit' => [
                'class_fqn' => '\local_moodleorm\tests\circuit',
                'parent_class_fqn' => '\local_moodleorm\tests\wave',
            ],
            'simulation_station' => [
                'class_fqn' => '\local_moodleorm\tests\station',
                'parent_class_fqn' => '\local_moodleorm\tests\circuit',
            ],
            'simulation_substation' => [
                'class_fqn' => '\local_moodleorm\tests\substation',
                'parent_class_fqn' => '\local_moodleorm\tests\station',
            ],
        ];

        $this->db = $DB;

        $dbman = $this->db->get_manager();

        // Load the XML file.
        $xmldbfile = new \xmldb_file($CFG->dirroot
            . DIRECTORY_SEPARATOR
            . 'local'
            . DIRECTORY_SEPARATOR
            . 'moodleorm'
            . DIRECTORY_SEPARATOR
            . 'tests'
            . DIRECTORY_SEPARATOR
            . 'db'
            . DIRECTORY_SEPARATOR
            . 'install.xml');

        // Only if the file exists.
        if ($xmldbfile->fileExists()) {
            // Load the XML contents to structure.
            $loaded = $xmldbfile->loadXMLStructure();

            if ($loaded || $xmldbfile->isLoaded()) {
                // Arriving here, everything is ok, get the XMLDB structure.
                $structure = $xmldbfile->getStructure();

                // Getting tables.
                if ($xmldbtables = $structure->getTables()) {
                    // Foreach table, process its fields.
                    foreach ($xmldbtables as $xmldbtable) {
                        // Table processing starts here.
                        if ($dbman->table_exists($xmldbtable)) {
                            continue;
                        } else {
                            $dbman->create_table($xmldbtable);
                        }

                        // Give the script some more time (resetting to current if exists).
                        if ($currenttl = @ini_get('max_execution_time')) {
                            @ini_set('max_execution_time', $currenttl);
                        }
                    }
                }
            }
        }

        $this->resetAfterTest(false);
    }

    /**
     * Clean up after tests.
     */
    public function tearDown(): void {
        global $CFG;

        $this->unitofwork->try_remove_cascade_delete_check();

        $dbman = $this->db->get_manager();

        // Load the XML file.
        $xmldbfile = new \xmldb_file($CFG->dirroot
            . DIRECTORY_SEPARATOR
            . 'local'
            . DIRECTORY_SEPARATOR
            . 'moodleorm'
            . DIRECTORY_SEPARATOR
            . 'tests'
            . DIRECTORY_SEPARATOR
            . 'db'
            . DIRECTORY_SEPARATOR
            . 'install.xml');

        // Only if the file exists.
        if ($xmldbfile->fileExists()) {
            // Load the XML contents to structure.
            $loaded = $xmldbfile->loadXMLStructure();

            if ($loaded || $xmldbfile->isLoaded()) {
                // Arriving here, everything is ok, get the XMLDB structure.
                $structure = $xmldbfile->getStructure();

                // Getting tables.
                if ($xmldbtables = $structure->getTables()) {
                    // For each table, truncate it (delete all records).
                    foreach ($xmldbtables as $xmldbtable) {
                        // Table processing starts here.
                        if ($dbman->table_exists($xmldbtable) && $xmldbtable->getName() === 'simulation') {
                            $this->db->delete_records($xmldbtable->getName());
                        } else {
                            continue;
                        }

                        // Give the script some more time (resetting to current if exists).
                        if ($currenttl = @ini_get('max_execution_time')) {
                            @ini_set('max_execution_time', $currenttl);
                        }
                    }

                    // For each table, drop it.
                    foreach ($xmldbtables as $xmldbtable) {
                        // Table processing starts here.
                        $this->db->delete_records($xmldbtable->getName());
                        $dbman->drop_table($xmldbtable);

                        // Give the script some more time (resetting to current if exists).
                        if ($currenttl = @ini_get('max_execution_time')) {
                            @ini_set('max_execution_time', $currenttl);
                        }
                    }
                }
            }
        }

        $this->unitofwork = null;

        $this->db = null;

        $this->classmap = null;

        parent::tearDown();
    }

    /**
     * Test unitofwork_creation method.
     *
     * @covers \local_moodleorm\unitofwork
     */
    public function test_unitofwork_creation() {
        $this->unitofwork = new unitofwork($this->db, $this->classmap, self::MAIN_TABLE_NAME, 1);

        $this->assertTrue(
            array_reverse(explode('\\', get_class($this->unitofwork)))[0]
            === 'unitofwork'
        );

        $this->assertEquals($this->classmap, $this->unitofwork->get_classmap());
    }

    /**
     * Test unitofwork_creation_with_childparentid method.
     *
     * @covers \local_moodleorm\unitofwork
     */
    public function test_unitofwork_creation_with_childparentid() {
        $this->unitofwork = new unitofwork($this->db, $this->classmap, self::MAIN_TABLE_NAME, 1, 'simulationid');

        $this->assertTrue(
            array_reverse(explode('\\', get_class($this->unitofwork)))[0]
            === 'unitofwork'
        );

        $this->assertEquals($this->classmap, $this->unitofwork->get_classmap());
    }

    /**
     * Test unitofwork_destruction_autocommit method.
     *
     * @covers \local_moodleorm\unitofwork
     */
    public function test_unitofwork_destruction_autocommit() {
        $this->unitofwork = new unitofwork($this->db, $this->classmap, self::MAIN_TABLE_NAME, 1, 'simulationid');

        $this->assertTrue($this->unitofwork->__destruct());

        $this->assertTrue($this->unitofwork->is_committed());
    }

    /**
     * Test unitofwork_cannot_commit_twice method.
     *
     * @covers \local_moodleorm\unitofwork
     */
    public function test_unitofwork_cannot_commit_twice() {
        $this->unitofwork = new unitofwork($this->db, $this->classmap, self::MAIN_TABLE_NAME, 1, 'simulationid');

        $this->assertTrue($this->unitofwork->commit());

        $this->assertFalse($this->unitofwork->commit());
    }

    /**
     * Test unitofwork_creation_no_data_in_the_database method.
     *
     * @covers \local_moodleorm\unitofwork
     */
    public function test_unitofwork_creation_no_data_in_the_database() {
        $this->unitofwork = new unitofwork($this->db, $this->classmap, self::MAIN_TABLE_NAME, 1, 'simulationid');

        $this->assertEmpty($this->unitofwork->get_registry());

        $this->assertEmpty($this->unitofwork->get_dirty());

        $this->assertEmpty($this->unitofwork->get_stage());
    }

    /**
     * Test unitofwork_get_main_settings_from_the_database method.
     *
     * @covers \local_moodleorm\unitofwork
     */
    public function test_unitofwork_get_main_settings_from_the_database() {
        $course = $this->getDataGenerator()->create_course();

        $simulation = [
            'course' => $course->id,
            'name' => 'newsim',
            'introformat' => 0,
            'scheduledelimiters' => "Arrival | -30\nInstructions | -15",
        ];

        $simulationid = $this->db->insert_record('simulation', $simulation);

        $this->unitofwork = new unitofwork($this->db, $this->classmap, self::MAIN_TABLE_NAME, $simulationid, 'simulationid');

        $mainsettings = $this->unitofwork->get_maintable_settings();

        $this->assertTrue(isset($mainsettings) && !empty($mainsettings));

        $key = key($mainsettings);

        $this->assertSame($simulation['name'], $mainsettings[$key]->name);

        $this->assertSame($simulation['scheduledelimiters'], $mainsettings[$key]->scheduledelimiters);
    }

    /**
     * Test test_unitofwork_get_single_parent_table_from_the_database method.
     *
     * @covers \local_moodleorm\unitofwork
     */
    public function test_unitofwork_get_single_parent_table_from_the_database() {
        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);
        $module = $this->db->get_record('course_modules', ['id' => $cm->cmid], 'module');

        $classmap = [
            'module' => [
                'class_fqn' => '\local_moodleorm\tests\module',
                'parent_class_fqn' => '',
            ],
        ];

        $this->unitofwork = new unitofwork($this->db, $classmap, 'modules', $module->module, 'id');

        $fetchedarray = $this->unitofwork->get_registry();

        $this->assertTrue(isset($fetchedarray) && !empty($fetchedarray));

        $this->assertSame('module', key($fetchedarray));

        foreach ($fetchedarray['module'] as $entity) {
            if (!isset($entity['persistent_object']->get_data()->id)) {
                $this->assertSame($module->module, $entity['persistent_object']->get_data()->id);
                $this->assertSame('forum', $entity['persistent_object']->get_data()->name);
                $this->assertSame('local_moodleorm\tests\module', get_class($entity['persistent_object']));
                $this->assertTrue(isset($entity['hash']));
            }
        }
    }

    /**
     * Test test_unitofwork_get_single_child_table_from_the_database method.
     *
     * @covers \local_moodleorm\unitofwork
     */
    public function test_unitofwork_get_single_child_table_from_the_database() {
        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);

        $classmap = [
            'module' => [
                'class_fqn' => '\local_moodleorm\tests\cmodule',
                'parent_class_fqn' => '',
            ],
        ];

        $this->unitofwork = new unitofwork($this->db, $classmap, 'course_modules', $cm->cmid, 'id');

        $fetchedarray = $this->unitofwork->get_registry();

        $this->assertTrue(isset($fetchedarray) && !empty($fetchedarray));

        $this->assertSame('module', key($fetchedarray));

        foreach ($fetchedarray['module'] as $entity) {
            if (!isset($entity['persistent_object']->get_data()->id)) {
                $this->assertSame($cm->cmid, $entity['persistent_object']->get_data()->id);
                $this->assertSame($cm->timemodified, $entity['persistent_object']->get_data()->added);
                $this->assertSame('local_moodleorm\tests\cmodule', get_class($entity['persistent_object']));
                $this->assertSame($course->id, $entity['persistent_object']->get_data()->course);
                $this->assertTrue(isset($entity['hash']));
            }
        }
    }

    /**
     * Test test_unitofwork_get_parent_and_child_tables_from_the_database method.
     *
     * @covers \local_moodleorm\unitofwork
     */
    public function test_unitofwork_get_parent_and_child_tables_from_the_database() {
        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);
        $module = $this->db->get_record('course_modules', ['id' => $cm->cmid], 'module');

        $classmap = [
            'module' => [
                'class_fqn' => '\local_moodleorm\tests\cmodule',
                'parent_class_fqn' => '',
            ],
        ];

        $this->unitofwork = new unitofwork($this->db, $classmap, 'modules', $module->module, 'module');

        $fetchedarray = $this->unitofwork->get_registry();

        $this->assertTrue(isset($fetchedarray) && !empty($fetchedarray));

        $this->assertSame('module', key($fetchedarray));

        foreach ($fetchedarray['module'] as $entity) {
            if (!isset($entity['persistent_object']->get_data()->id)) {
                $this->assertSame($cm->cmid, $entity['persistent_object']->get_data()->id);
                $this->assertSame($cm->timemodified, $entity['persistent_object']->get_data()->added);
                $this->assertSame('local_moodleorm\tests\cmodule', get_class($entity['persistent_object']));
                $this->assertSame($course->id, $entity['persistent_object']->get_data()->course);
                $this->assertTrue(isset($entity['hash']));
            }
        }
    }

    /**
     * Test unitofwork_creation_with_data_in_the_database method.
     *
     * @covers \local_moodleorm\unitofwork
     */
    public function test_unitofwork_creation_with_data_in_the_database() {
        $course = $this->getDataGenerator()->create_course();

        $simulation = [
            'course' => $course->id,
            'name' => 'newsim',
            'introformat' => 0,
            'scheduledelimiters' => "Arrival | -30\nInstructions | -15",
        ];

        $simulationid = $this->db->insert_record('simulation', $simulation);

        $wave = [
            'name' => 'Vague 1',
            'description' => 'Première vague du premier test.',
            'simulationid' => $simulationid,
        ];

        $waveid = $this->db->insert_record('simulation_wave', $wave);

        $this->unitofwork = new unitofwork($this->db, $this->classmap, self::MAIN_TABLE_NAME, $simulationid);

        $fetchedwavearray = $this->unitofwork->get_registry()['simulation_wave']['wave_' . $waveid];

        $this->assertTrue(isset($fetchedwavearray) && !empty($fetchedwavearray));

        $this->assertEquals($waveid, $fetchedwavearray['persistent_object']->get_data()->id);

        $this->assertEmpty($this->unitofwork->get_dirty());

        $this->assertEmpty($this->unitofwork->get_stage());
    }

    /**
     * Test unitofwork_creation_with_data_in_the_database_with_childparentid method.
     *
     * @covers \local_moodleorm\unitofwork
     */
    public function test_unitofwork_creation_with_data_in_the_database_with_childparentid() {
        $course = $this->getDataGenerator()->create_course();

        $simulation = [
            'course' => $course->id,
            'name' => 'newsim',
            'introformat' => 0,
            'scheduledelimiters' => "Arrival | -30\nInstructions | -15",
        ];

        $simulationid = $this->db->insert_record('simulation', $simulation);

        $wave = [
            'name' => 'Vague 1',
            'description' => 'Première vague du premier test.',
            'simulationid' => $simulationid,
        ];

        $waveid = $this->db->insert_record('simulation_wave', $wave);

        $this->unitofwork = new unitofwork($this->db, $this->classmap, self::MAIN_TABLE_NAME, $simulationid, 'simulationid');

        $fetchedwavearray = $this->unitofwork->get_registry()['simulation_wave']['wave_' . $waveid];

        $this->assertTrue(isset($fetchedwavearray) && !empty($fetchedwavearray));

        $this->assertEquals($waveid, $fetchedwavearray['persistent_object']->get_data()->id);

        $this->assertEmpty($this->unitofwork->get_dirty());

        $this->assertEmpty($this->unitofwork->get_stage());
    }

    /**
     * Test unitofwork_creation_with_data_read_from_the_database method.
     *
     * @covers \local_moodleorm\unitofwork
     */
    public function test_unitofwork_creation_with_data_read_from_the_database() {
        $course = $this->getDataGenerator()->create_course();

        $simulation = [
            'course' => $course->id,
            'name' => 'newsim',
            'introformat' => 0,
            'scheduledelimiters' => "Arrival | -30\nInstructions | -15",
        ];

        $simulationid = $this->db->insert_record('simulation', $simulation);

        $wave = [
            'name' => 'Vague 1',
            'description' => 'Première vague du premier test.',
            'simulationid' => $simulationid,
        ];

        $waveid = $this->db->insert_record('simulation_wave', $wave);

        $this->unitofwork = new unitofwork($this->db, $this->classmap, self::MAIN_TABLE_NAME, $simulationid, 'simulationid');

        $fetchedwavearray = $this->unitofwork->get_registry()['simulation_wave']['wave_' . $waveid];

        $this->assertTrue(isset($fetchedwavearray) && !empty($fetchedwavearray));

        $this->assertEquals($waveid, $fetchedwavearray['persistent_object']->get_data()->id);

        $this->assertEmpty($this->unitofwork->get_dirty());

        $this->assertEmpty($this->unitofwork->get_stage());
    }

    /**
     * Test unitofwork_creation_with_data_read_other_simulation_from_the_database method.
     *
     * @covers \local_moodleorm\unitofwork
     */
    public function test_unitofwork_creation_with_data_read_other_simulation_from_the_database() {
        $course = $this->getDataGenerator()->create_course();

        $simulation = [
            'course' => $course->id,
            'name' => 'newsimother',
            'introformat' => 0,
            'scheduledelimiters' => "Arrival | -25\nInstructions | -10",
        ];

        $simulationidref = $this->db->insert_record('simulation', $simulation);

        $simulation = [
            'course' => $course->id,
            'name' => 'newsim',
            'introformat' => 0,
            'scheduledelimiters' => "Arrival | -30\nInstructions | -15",
        ];

        $simulationid = $this->db->insert_record('simulation', $simulation);

        $wave = [
            'name' => 'Vague 1',
            'description' => 'Première vague du premier test.',
            'simulationid' => $simulationid,
        ];

        $waveid = $this->db->insert_record('simulation_wave', $wave);

        $this->unitofwork = new unitofwork($this->db, $this->classmap, self::MAIN_TABLE_NAME, $simulationidref, 'simulationid');

        $fetchedwavearray = $this->unitofwork->get_registry();

        $this->assertTrue(empty($fetchedwavearray));

        $this->assertEmpty($this->unitofwork->get_dirty());

        $this->assertEmpty($this->unitofwork->get_stage());
    }

    /**
     * Test create_wave_with_data_in_the_database method.
     *
     * @covers \local_moodleorm\unitofwork
     */
    public function test_create_wave_with_data_in_the_database() {
        $course = $this->getDataGenerator()->create_course();

        $simulation = [
            'course' => $course->id,
            'name' => 'newsim',
            'introformat' => 0,
            'scheduledelimiters' => "Arrival | -30\nInstructions | -15",
        ];

        $simulationid = $this->db->insert_record('simulation', $simulation);

        $wave = [
            'name' => 'Vague 1',
            'description' => 'Première vague du test.',
            'simulationid' => $simulationid,
        ];

        $waveid = $this->db->insert_record('simulation_wave', $wave);

        $data[] = [
            'entityid' => $waveid,
            'repositoryname' => 'simulation_wave',
            'data' => [
                'name' => 'Vague 1',
                'description' => 'Première vague du test.',
                'simulationid' => $simulationid,
            ],
        ];

        $uuid = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_wave',
            'entityuuid' => $uuid,
            'parentuuid' => null,
            'data' => [
                'name' => 'Vague 2',
                'description' => 'Deuxième vague du test.',
                'simulationid' => $simulationid,
            ],
        ];

        $this->unitofwork = new unitofwork($this->db, $this->classmap, self::MAIN_TABLE_NAME, $simulationid);

        $this->unitofwork->save($data);

        $wavesarray = $this->unitofwork->get_registry()['simulation_wave'];

        $this->assertEquals(2, count($wavesarray));

        $dirtyregistry = $this->unitofwork->get_dirty();

        $this->assertFalse(empty($dirtyregistry));

        foreach ($wavesarray as $entity) {
            if (!isset($entity['persistent_object']->get_data()->id)) {
                $this->assertFalse(isset($entity['hash']));
            }
        }

        $totaldirtyentities = 0;

        $dirtyentities = $this->unitofwork->get_dirty();

        array_walk_recursive($dirtyentities, function($element) use (&$totaldirtyentities) {
            if (!empty($element)) {
                $totaldirtyentities++;
            }
        });

        $this->assertEquals(2, $totaldirtyentities);

        $this->unitofwork->commit();

        $this->assertTrue($this->unitofwork->is_committed());

        $records = $this->db->get_records('simulation_wave');

        $this->assertTrue(isset($records) && !empty($records));

        $this->assertEquals(2, count($records));

        foreach ($records as $record) {
            if ($record->name === 'Vague 2') {
                $this->assertEquals('Deuxième vague du test.', $record->description);
            }
        }
    }

    /**
     * Test create_wave_with_data_in_the_database_with_childparentid method.
     *
     * @covers \local_moodleorm\unitofwork
     */
    public function test_create_wave_with_data_in_the_database_with_childparentid() {
        $course = $this->getDataGenerator()->create_course();

        $simulation = [
            'course' => $course->id,
            'name' => 'newsim',
            'introformat' => 0,
            'scheduledelimiters' => "Arrival | -30\nInstructions | -15",
        ];

        $simulationid = $this->db->insert_record('simulation', $simulation);

        $wave = [
            'name' => 'Vague 1',
            'description' => 'Première vague du test.',
            'simulationid' => $simulationid,
        ];

        $waveid = $this->db->insert_record('simulation_wave', $wave);

        $data[] = [
            'entityid' => $waveid,
            'repositoryname' => 'simulation_wave',
            'data' => [
                'name' => 'Vague 1',
                'description' => 'Première vague du test.',
                'simulationid' => $simulationid,
            ],
        ];

        $uuid = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_wave',
            'entityuuid' => $uuid,
            'parentuuid' => null,
            'data' => [
                'name' => 'Vague 2',
                'description' => 'Deuxième vague du test.',
                'simulationid' => $simulationid,
            ],
        ];

        $this->unitofwork = new unitofwork($this->db, $this->classmap, self::MAIN_TABLE_NAME, $simulationid, 'simulationid');

        $this->unitofwork->save($data);

        $wavesarray = $this->unitofwork->get_registry()['simulation_wave'];

        $this->assertEquals(2, count($wavesarray));

        $dirtyregistry = $this->unitofwork->get_dirty();

        $this->assertFalse(empty($dirtyregistry));

        foreach ($wavesarray as $entity) {
            if (!isset($entity['persistent_object']->get_data()->id)) {
                $this->assertFalse(isset($entity['hash']));
            }
        }

        $totaldirtyentities = 0;

        $dirtyentities = $this->unitofwork->get_dirty();

        array_walk_recursive($dirtyentities, function($element) use (&$totaldirtyentities) {
            if (!empty($element)) {
                $totaldirtyentities++;
            }
        });

        $this->assertEquals(2, $totaldirtyentities);

        $this->unitofwork->commit();

        $this->assertTrue($this->unitofwork->is_committed());

        $records = $this->db->get_records('simulation_wave');

        $this->assertTrue(isset($records) && !empty($records));

        $this->assertEquals(2, count($records));

        foreach ($records as $record) {
            if ($record->name === 'Vague 2') {
                $this->assertEquals('Deuxième vague du test.', $record->description);
            }
        }
    }

    /**
     * Test create_wave_and_circuit_with_data_in_the_database method.
     *
     * @covers \local_moodleorm\unitofwork
     */
    public function test_create_wave_and_circuit_with_data_in_the_database() {
        $course = $this->getDataGenerator()->create_course();

        $simulation = [
            'course' => $course->id,
            'name' => 'newsim',
            'introformat' => 0,
            'scheduledelimiters' => "Arrival | -30\nInstructions | -15",
        ];

        $simulationid = $this->db->insert_record('simulation', $simulation);

        $wave = [
            'name' => 'Vague 1',
            'description' => 'Première vague du test.',
            'simulationid' => $simulationid,
        ];

        $waveid = $this->db->insert_record('simulation_wave', $wave);

        $data[] = [
            'entityid' => $waveid,
            'repositoryname' => 'simulation_wave',
            'data' => [
                'name' => 'Vague 1',
                'description' => 'Première vague du test.',
                'simulationid' => $simulationid,
            ],
        ];

        $uuid = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_wave',
            'entityuuid' => $uuid,
            'parentuuid' => null,
            'data' => [
                'name' => 'Vague 2',
                'description' => 'Deuxième vague du test.',
                'simulationid' => $simulationid,
            ],
        ];

        $uuid2 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_circuit',
            'entityuuid' => $uuid2,
            'parentuuid' => $uuid,
            'data' => [
                'name' => 'Circuit 1',
                'timestart' => '20220311110000',
                'timeend' => '20220311120000',
            ],
        ];

        $this->unitofwork = new unitofwork($this->db, $this->classmap, self::MAIN_TABLE_NAME, $simulationid, 'simulationid');

        $this->unitofwork->save($data);

        $wavesarray = $this->unitofwork->get_registry()['simulation_wave'];

        $this->assertEquals(2, count($wavesarray));

        $circuitsarray = $this->unitofwork->get_registry()['simulation_circuit'];

        $this->assertEquals(1, count($circuitsarray));

        foreach ($wavesarray as $entity) {
            if (!isset($entity['persistent_object']->get_data()->id)) {
                $this->assertFalse(isset($entity['hash']));
            }
        }

        foreach ($circuitsarray as $entity) {
            if (!isset($entity['persistent_object']->get_data()->id)) {
                $this->assertFalse(isset($entity['hash']));
            }
        }

        $totaldirtyentities = 0;

        $dirtyentities = $this->unitofwork->get_dirty();

        $this->assertFalse(empty($dirtyentities));

        array_walk_recursive($dirtyentities, function($element) use (&$totaldirtyentities) {
            if (!empty($element)) {
                $totaldirtyentities++;
            }
        });

        $this->assertEquals(3, $totaldirtyentities);

        $this->unitofwork->commit();

        $this->assertTrue($this->unitofwork->is_committed());

        $records = $this->db->get_records('simulation_wave');

        $this->assertTrue(isset($records) && !empty($records));

        $this->assertEquals(2, count($records));

        foreach ($records as $record) {
            if ($record->name === 'Vague 2') {
                $this->assertEquals('Deuxième vague du test.', $record->description);
            }
        }

        $records = $this->db->get_records('simulation_circuit');

        $this->assertTrue(isset($records) && !empty($records));

        $this->assertEquals(1, count($records));

        foreach ($records as $record) {
            if ($record->name === 'Circuit 1') {
                $this->assertEquals('20220311110000', $record->timestart);
            }
        }
    }

    /**
     * Test create_wave_and_circuit_with_data_in_the_database method.
     *
     * @covers \local_moodleorm\unitofwork
     */
    public function test_update_wave_and_circuit_with_data_in_the_database() {
        $course = $this->getDataGenerator()->create_course();

        $simulation = [
            'course' => $course->id,
            'name' => 'newsim',
            'introformat' => 0,
            'scheduledelimiters' => "Arrival | -30\nInstructions | -15",
        ];

        $simulationid = $this->db->insert_record('simulation', $simulation);

        $wave = [
            'name' => 'Vague 1',
            'description' => 'Première vague du test.',
            'simulationid' => $simulationid,
        ];

        $waveid = $this->db->insert_record('simulation_wave', $wave);

        $circuit = [
            'name' => 'Circuit 1',
            'description' => 'Premier circuit de la première vague du test.',
            'waveid' => $waveid,
        ];

        $circuitid = $this->db->insert_record('simulation_circuit', $circuit);

        $data[] = [
            'entityid' => $waveid,
            'repositoryname' => 'simulation_wave',
            'data' => [
                'name' => 'Vague 1',
                'description' => 'Première vague du test.',
                'simulationid' => $simulationid,
            ],
        ];

        $data[] = [
            'entityid' => $circuitid,
            'repositoryname' => 'simulation_circuit',
            'data' => [
                'name' => 'Circuit 1a',
                'description' => 'Premier circuit modifié de la première vague du test.',
                'waveid' => $waveid,
            ],
        ];

        $uuid = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_wave',
            'entityuuid' => $uuid,
            'parentuuid' => null,
            'data' => [
                'name' => 'Vague 2',
                'description' => 'Deuxième vague du test.',
                'simulationid' => $simulationid,
            ],
        ];

        $uuid2 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_circuit',
            'entityuuid' => $uuid2,
            'parentuuid' => $uuid,
            'data' => [
                'name' => 'Circuit 1b',
                'timestart' => '20220311110000',
                'timeend' => '20220311120000',
            ],
        ];

        $this->unitofwork = new unitofwork($this->db, $this->classmap, self::MAIN_TABLE_NAME, $simulationid, 'simulationid');

        $this->unitofwork->save($data);

        $wavesarray = $this->unitofwork->get_registry()['simulation_wave'];

        $this->assertEquals(2, count($wavesarray));

        $circuitsarray = $this->unitofwork->get_registry()['simulation_circuit'];

        $this->assertEquals(2, count($circuitsarray));

        foreach ($wavesarray as $entity) {
            if (!isset($entity['persistent_object']->get_data()->id)) {
                $this->assertFalse(isset($entity['hash']));
            }
        }

        foreach ($circuitsarray as $entity) {
            if (!isset($entity['persistent_object']->get_data()->id)) {
                $this->assertFalse(isset($entity['hash']));
            }
        }

        $totaldirtyentities = 0;

        $dirtyentities = $this->unitofwork->get_dirty();

        $this->assertFalse(empty($dirtyentities));

        array_walk_recursive($dirtyentities, function($element) use (&$totaldirtyentities) {
            if (!empty($element)) {
                $totaldirtyentities++;
            }
        });

        $this->assertEquals(4, $totaldirtyentities);

        $this->unitofwork->commit();

        $this->assertTrue($this->unitofwork->is_committed());

        $records = $this->db->get_records('simulation_wave');

        $this->assertTrue(isset($records) && !empty($records));

        $this->assertEquals(2, count($records));

        foreach ($records as $record) {
            if ($record->name === 'Vague 2') {
                $this->assertEquals('Deuxième vague du test.', $record->description);
            }
        }

        $records = $this->db->get_records('simulation_circuit');

        $this->assertTrue(isset($records) && !empty($records));

        $this->assertEquals(2, count($records));

        foreach ($records as $record) {
            if ($record->name === 'Circuit 1a') {
                $this->assertEquals(
                    'Premier circuit modifié de la première vague du test.',
                    $record->description
                );
            }

            if ($record->name === 'Circuit 1b') {
                $this->assertEquals('20220311110000', $record->timestart);
            }
        }
    }

    /**
     * Test unitofwork_export_data_cannot_export_when_dirty method.
     *
     * @covers \local_moodleorm\unitofwork
     */
    public function test_unitofwork_export_data_cannot_export_when_dirty() {
        $course = $this->getDataGenerator()->create_course();

        $simulation = [
            'course' => $course->id,
            'name' => 'newsim',
            'introformat' => 0,
            'scheduledelimiters' => "Arrival | -30\nInstructions | -15",
        ];

        $simulationid = $this->db->insert_record('simulation', $simulation);

        $wave = [
            'name' => 'Vague 1',
            'description' => 'Première vague du premier test.',
            'simulationid' => $simulationid,
        ];

        $waveid = $this->db->insert_record('simulation_wave', $wave);

        $data[] = [
            'entityid' => $waveid,
            'repositoryname' => 'simulation_wave',
            'data' => [
                'name' => 'Vague 1',
                'description' => 'Première vague du test.',
                'simulationid' => $simulationid,
            ],
        ];

        $uuid = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_wave',
            'entityuuid' => $uuid,
            'parentuuid' => null,
            'data' => [
                'name' => 'Vague 2',
                'description' => 'Deuxième vague du test.',
                'simulationid' => $simulationid,
            ],
        ];

        $this->unitofwork = new unitofwork($this->db, $this->classmap, self::MAIN_TABLE_NAME, $simulationid, 'simulationid');

        $this->unitofwork->save($data);

        $this->assertTrue($this->unitofwork->is_dirty());

        $this->assertFalse($this->unitofwork->export_data());
    }

    /**
     * Test unitofwork_export_data_cannot_export_when_committed method.
     *
     * @covers \local_moodleorm\unitofwork
     */
    public function test_unitofwork_export_data_cannot_export_when_committed() {
        $course = $this->getDataGenerator()->create_course();

        $simulation = [
            'course' => $course->id,
            'name' => 'newsim',
            'introformat' => 0,
            'scheduledelimiters' => "Arrival | -30\nInstructions | -15",
        ];

        $simulationid = $this->db->insert_record('simulation', $simulation);

        $wave = [
            'name' => 'Vague 1',
            'description' => 'Première vague du premier test.',
            'simulationid' => $simulationid,
        ];

        $waveid = $this->db->insert_record('simulation_wave', $wave);

        $data[] = [
            'entityid' => $waveid,
            'repositoryname' => 'simulation_wave',
            'data' => [
                'name' => 'Vague 1',
                'description' => 'Première vague du test.',
                'simulationid' => $simulationid,
            ],
        ];

        $uuid = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_wave',
            'entityuuid' => $uuid,
            'parentuuid' => null,
            'data' => [
                'name' => 'Vague 2',
                'description' => 'Deuxième vague du test.',
                'simulationid' => $simulationid,
            ],
        ];

        $this->unitofwork = new unitofwork($this->db, $this->classmap, self::MAIN_TABLE_NAME, $simulationid, 'simulationid');

        // Deliberately omitting to save() the data.
        $this->unitofwork->commit();

        $this->assertTrue($this->unitofwork->is_committed());

        $this->assertFalse($this->unitofwork->export_data());
    }

    /**
     * Test unitofwork_export_data_from_the_database method.
     *
     * @covers \local_moodleorm\unitofwork
     */
    public function test_unitofwork_export_data_from_the_database() {
        $course = $this->getDataGenerator()->create_course();

        $simulation = [
            'course' => $course->id,
            'name' => 'newsim',
            'introformat' => 0,
            'scheduledelimiters' => "Arrival | -30\nInstructions | -15",
        ];

        $simulationid = $this->db->insert_record('simulation', $simulation);

        $wave = [
            'name' => 'Vague 1',
            'description' => 'Première vague du premier test.',
            'simulationid' => $simulationid,
        ];

        $waveid = $this->db->insert_record('simulation_wave', $wave);

        $data[] = [
            'entityid' => $waveid,
            'repositoryname' => 'simulation_wave',
            'data' => [
                'name' => 'Vague 1',
                'description' => 'Première vague du test.',
                'simulationid' => $simulationid,
            ],
        ];

        $uuid = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_wave',
            'entityuuid' => $uuid,
            'parentuuid' => null,
            'data' => [
                'name' => 'Vague 2',
                'description' => 'Deuxième vague du test.',
                'simulationid' => $simulationid,
            ],
        ];

        $uuid2 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_circuit',
            'entityuuid' => $uuid2,
            'parentuuid' => $uuid,
            'data' => [
                'name' => 'Circuit 1',
                'timestart' => '20220311110000',
                'timeend' => '20220311120000',
            ],
        ];

        $this->unitofwork = new unitofwork($this->db, $this->classmap, self::MAIN_TABLE_NAME, $simulationid, 'simulationid');

        $this->unitofwork->save($data);

        $this->unitofwork->commit();

        $this->unitofwork = new unitofwork($this->db, $this->classmap, self::MAIN_TABLE_NAME, $simulationid, 'simulationid');

        $exporteddata = $this->unitofwork->export_data();

        foreach ($exporteddata as $element) {
            if (isset($element['repositoryname'])
                && $element['repositoryname'] === 'simulation_wave'
            ) {
                $this->assertTrue(
                    isset($element['entityid'])
                    && isset($element['data'])
                    && isset($element['data']['id'])
                    && isset($element['data']['name'])
                    && isset($element['data']['description'])
                    && isset($element['data']['simulationid'])
                    && isset($element['data']['timemodified'])
                    && $element['entityid'] === $element['data']['id']
                );
            } else if (isset($element['repositoryname'])
                && $element['repositoryname'] === 'simulation_circuit'
            ) {
                $this->assertTrue(
                    isset($element['entityid'])
                    && isset($element['data'])
                    && isset($element['data']['id'])
                    && isset($element['data']['name'])
                    && isset($element['data']['waveid'])
                    && isset($element['data']['timestart'])
                    && isset($element['data']['timeend'])
                    && isset($element['data']['timemodified'])
                    && $element['entityid'] === $element['data']['id']
                );
            }
        }
    }

    /**
     * Test crud_one_without_children_all_entities_with_data_in_the_database method.
     *
     * @covers \local_moodleorm\unitofwork
     */
    public function test_crud_one_without_children_all_entities_with_data_in_the_database() {
        $course = $this->getDataGenerator()->create_course();

        $simulation = [
            'course' => $course->id,
            'name' => 'newsim',
            'introformat' => 0,
            'scheduledelimiters' => "Arrival | -30\nInstructions | -15",
        ];

        $simulationid = $this->db->insert_record('simulation', $simulation);

        $wave = [
            'name' => 'Vague 1',
            'description' => 'Première vague du test.',
            'simulationid' => $simulationid,
        ];

        $waveid = $this->db->insert_record('simulation_wave', $wave);

        $data[] = [
            'entityid' => $waveid,
            'repositoryname' => 'simulation_wave',
            'data' => [
                'name' => 'Vague 1a',
                'description' => 'Première vague du test.',
                'simulationid' => $simulationid,
            ],
        ];

        $uuid1 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_wave',
            'entityuuid' => $uuid1,
            'parentuuid' => null,
            'data' => [
                'name' => 'Vague 1b',
                'description' => 'Deuxième vague du test.',
                'simulationid' => $simulationid,
            ],
        ];

        $uuid2 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_circuit',
            'entityuuid' => $uuid2,
            'parentuuid' => $uuid1,
            'data' => [
                'name' => 'Circuit 1',
                'timestart' => '20220311110000',
                'timeend' => '20220311120000',
            ],
        ];

        $uuid3 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_circuit',
            'entityuuid' => $uuid3,
            'parentuuid' => $uuid1,
            'data' => [
                'name' => 'Circuit 2',
                'timestart' => '20220312110000',
                'timeend' => '20220312120000',
            ],
        ];

        $uuid4 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_station',
            'entityuuid' => $uuid4,
            'parentuuid' => $uuid2,
            'data' => [
                'name' => 'Station 1',
                'description' => 'La station 1 du circuit 1.',
                'groupid' => 1,
            ],
        ];

        $uuid5 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_station',
            'entityuuid' => $uuid5,
            'parentuuid' => $uuid3,
            'data' => [
                'name' => 'Station 1',
                'description' => 'La station 1 du circuit 2.',
                'groupid' => 1,
            ],
        ];

        $uuid6 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_station',
            'entityuuid' => $uuid6,
            'parentuuid' => $uuid3,
            'data' => [
                'name' => 'Station 2',
                'description' => 'La station 2 du circuit 2.',
                'groupid' => 1,
            ],
        ];

        $uuid7 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_substation',
            'entityuuid' => $uuid7,
            'parentuuid' => $uuid5,
            'data' => [
                'name' => 'Station 1A',
                'userid' => 1,
                'role' => 3,
            ],
        ];

        $uuid8 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_substation',
            'entityuuid' => $uuid8,
            'parentuuid' => $uuid5,
            'data' => [
                'name' => 'Station 1B',
                'userid' => 1,
                'role' => 2,
            ],
        ];

        $uuid9 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_substation',
            'entityuuid' => $uuid9,
            'parentuuid' => $uuid5,
            'data' => [
                'name' => 'Station 1C',
                'userid' => 1,
                'role' => 3,
            ],
        ];

        $this->unitofwork = new unitofwork($this->db, $this->classmap, self::MAIN_TABLE_NAME, $simulationid, 'simulationid');

        $this->unitofwork->save($data);

        $wavesarray = $this->unitofwork->get_registry()['simulation_wave'];

        $this->assertEquals(2, count($wavesarray));

        $circuitsarray = $this->unitofwork->get_registry()['simulation_circuit'];

        $this->assertEquals(2, count($circuitsarray));

        $circuitsarray = $this->unitofwork->get_registry()['simulation_station'];

        $this->assertEquals(3, count($circuitsarray));

        foreach ($wavesarray as $entity) {
            if (!isset($entity['persistent_object']->get_data()->id)) {
                $this->assertFalse(isset($entity['hash']));
            }
        }

        foreach ($circuitsarray as $entity) {
            if (!isset($entity['persistent_object']->get_data()->id)) {
                $this->assertFalse(isset($entity['hash']));
            }
        }

        $totaldirtyentities = 0;

        $dirtyentities = $this->unitofwork->get_dirty();

        $this->assertFalse(empty($dirtyentities));

        array_walk_recursive($dirtyentities, function($element) use (&$totaldirtyentities) {
            if (!empty($element)) {
                $totaldirtyentities++;
            }
        });

        $this->assertEquals(10, $totaldirtyentities);

        $this->unitofwork->commit();

        $this->assertTrue($this->unitofwork->is_committed());

        $waverecords = $this->db->get_records('simulation_wave');

        $this->assertTrue(isset($waverecords) && !empty($waverecords));

        $this->assertEquals(2, count($waverecords));

        foreach ($waverecords as $record) {
            if ($record->name === 'Vague 1a') {
                $this->assertEquals('Première vague du test.', $record->description);
            } else if ($record->name === 'Vague 1b') {
                $this->assertEquals('Deuxième vague du test.', $record->description);
            }
        }

        $circuitrecords = $this->db->get_records('simulation_circuit');

        $this->assertTrue(isset($circuitrecords) && !empty($circuitrecords));

        $this->assertEquals(2, count($circuitrecords));

        foreach ($circuitrecords as $record) {
            if ($record->name === 'Circuit 1') {
                $this->assertEquals('20220311110000', $record->timestart);
            }

            if ($record->name === 'Circuit 2') {
                $this->assertEquals('20220312110000', $record->timestart);
            }
        }

        $circuitrecords = array_values($circuitrecords);

        $stationrecords = $this->db->get_records('simulation_station');

        $this->assertTrue(isset($stationrecords) && !empty($stationrecords));

        $this->assertEquals(3, count($stationrecords));

        foreach ($stationrecords as $record) {
            if ($record->circuitid === $circuitrecords[0]->id && $record->name === 'Station 1') {
                $this->assertEquals('La station 1 du circuit 1.', $record->description);
            }

            if ($record->circuitid === $circuitrecords[1]->id && $record->name === 'Station 1') {
                $this->assertEquals('La station 1 du circuit 2.', $record->description);
            }

            if ($record->circuitid === $circuitrecords[1]->id && $record->name === 'Station 2') {
                $this->assertEquals('La station 2 du circuit 2.', $record->description);
            }
        }

        $this->unitofwork = new unitofwork($this->db, $this->classmap, self::MAIN_TABLE_NAME, $simulationid, 'simulationid');

        $data = $this->unitofwork->export_data();

        array_shift($data);

        $this->unitofwork->save($data);

        $wavesarray = $this->unitofwork->get_registry()['simulation_wave'];

        $this->assertEquals(2, count($wavesarray));

        $totaldirtyentities = 0;

        $dirtyentities = $this->unitofwork->get_dirty();

        $this->assertFalse(empty($dirtyentities));

        array_walk_recursive($dirtyentities, function($element) use (&$totaldirtyentities) {
            if (!empty($element)) {
                $totaldirtyentities++;
            }
        });

        $this->assertEquals(10, $totaldirtyentities);

        $this->unitofwork->commit();

        $this->assertTrue($this->unitofwork->is_committed());

        $waverecords = $this->db->get_records('simulation_wave');
        $circuitrecords = $this->db->get_records('simulation_circuit');
        $stationrecords = $this->db->get_records('simulation_station');
        $substationrecords = $this->db->get_records('simulation_substation');

        $this->assertTrue(isset($waverecords) && !empty($waverecords));
        $this->assertEquals(1, count($waverecords));

        $this->assertTrue(isset($circuitrecords) && !empty($circuitrecords));
        $this->assertEquals(2, count($circuitrecords));

        $this->assertTrue(isset($stationrecords) && !empty($stationrecords));
        $this->assertEquals(3, count($stationrecords));

        $this->assertTrue(isset($substationrecords) && !empty($substationrecords));
        $this->assertEquals(3, count($substationrecords));
    }

    /**
     * Test crud_one_with_children_all_entities_with_data_in_the_database method.
     *
     * @covers \local_moodleorm\unitofwork
     */
    public function test_crud_one_with_children_all_entities_with_data_in_the_database() {
        $course = $this->getDataGenerator()->create_course();

        $simulation = [
            'course' => $course->id,
            'name' => 'newsim',
            'introformat' => 0,
            'scheduledelimiters' => "Arrival | -30\nInstructions | -15",
        ];

        $simulationid = $this->db->insert_record('simulation', $simulation);

        $wave = [
            'name' => 'Vague 1',
            'description' => 'Première vague du test.',
            'simulationid' => $simulationid,
        ];

        $waveid = $this->db->insert_record('simulation_wave', $wave);

        $data[] = [
            'entityid' => $waveid,
            'repositoryname' => 'simulation_wave',
            'data' => [
                'name' => 'Vague 1a',
                'description' => 'Première vague du test.',
                'simulationid' => $simulationid,
            ],
        ];

        $uuid1 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_wave',
            'entityuuid' => $uuid1,
            'parentuuid' => null,
            'data' => [
                'name' => 'Vague 1b',
                'description' => 'Deuxième vague du test.',
                'simulationid' => $simulationid,
            ],
        ];

        $uuid2 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_circuit',
            'entityuuid' => $uuid2,
            'parentuuid' => $uuid1,
            'data' => [
                'name' => 'Circuit 1',
                'timestart' => '20220311110000',
                'timeend' => '20220311120000',
            ],
        ];

        $uuid3 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_circuit',
            'entityuuid' => $uuid3,
            'parentuuid' => $uuid1,
            'data' => [
                'name' => 'Circuit 2',
                'timestart' => '20220312110000',
                'timeend' => '20220312120000',
            ],
        ];

        $uuid4 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_station',
            'entityuuid' => $uuid4,
            'parentuuid' => $uuid2,
            'data' => [
                'name' => 'Station 1',
                'description' => 'La station 1 du circuit 1.',
                'groupid' => 1,
            ],
        ];

        $uuid5 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_station',
            'entityuuid' => $uuid5,
            'parentuuid' => $uuid3,
            'data' => [
                'name' => 'Station 1',
                'description' => 'La station 1 du circuit 2.',
                'groupid' => 1,
            ],
        ];

        $uuid6 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_station',
            'entityuuid' => $uuid6,
            'parentuuid' => $uuid3,
            'data' => [
                'name' => 'Station 2',
                'description' => 'La station 2 du circuit 2.',
                'groupid' => 1,
            ],
        ];

        $uuid7 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_substation',
            'entityuuid' => $uuid7,
            'parentuuid' => $uuid5,
            'data' => [
                'name' => 'Station 1A',
                'userid' => 1,
                'role' => 3,
            ],
        ];

        $uuid8 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_substation',
            'entityuuid' => $uuid8,
            'parentuuid' => $uuid5,
            'data' => [
                'name' => 'Station 1B',
                'userid' => 1,
                'role' => 2,
            ],
        ];

        $uuid9 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_substation',
            'entityuuid' => $uuid9,
            'parentuuid' => $uuid5,
            'data' => [
                'name' => 'Station 1C',
                'userid' => 1,
                'role' => 3,
            ],
        ];

        $this->unitofwork = new unitofwork($this->db, $this->classmap, self::MAIN_TABLE_NAME, $simulationid, 'simulationid');

        $this->unitofwork->save($data);

        $wavesarray = $this->unitofwork->get_registry()['simulation_wave'];

        $this->assertEquals(2, count($wavesarray));

        $circuitsarray = $this->unitofwork->get_registry()['simulation_circuit'];

        $this->assertEquals(2, count($circuitsarray));

        $circuitsarray = $this->unitofwork->get_registry()['simulation_station'];

        $this->assertEquals(3, count($circuitsarray));

        foreach ($wavesarray as $entity) {
            if (!isset($entity['persistent_object']->get_data()->id)) {
                $this->assertFalse(isset($entity['hash']));
            }
        }

        foreach ($circuitsarray as $entity) {
            if (!isset($entity['persistent_object']->get_data()->id)) {
                $this->assertFalse(isset($entity['hash']));
            }
        }

        $totaldirtyentities = 0;

        $dirtyentities = $this->unitofwork->get_dirty();

        $this->assertFalse(empty($dirtyentities));

        array_walk_recursive($dirtyentities, function($element) use (&$totaldirtyentities) {
            if (!empty($element)) {
                $totaldirtyentities++;
            }
        });

        $this->assertEquals(10, $totaldirtyentities);

        $this->unitofwork->commit();

        $this->assertTrue($this->unitofwork->is_committed());

        $waverecords = $this->db->get_records('simulation_wave');

        $this->assertTrue(isset($waverecords) && !empty($waverecords));

        $this->assertEquals(2, count($waverecords));

        foreach ($waverecords as $record) {
            if ($record->name === 'Vague 1a') {
                $this->assertEquals('Première vague du test.', $record->description);
            } else if ($record->name === 'Vague 1b') {
                $this->assertEquals('Deuxième vague du test.', $record->description);
            }
        }

        $circuitrecords = $this->db->get_records('simulation_circuit');

        $this->assertTrue(isset($circuitrecords) && !empty($circuitrecords));

        $this->assertEquals(2, count($circuitrecords));

        foreach ($circuitrecords as $record) {
            if ($record->name === 'Circuit 1') {
                $this->assertEquals('20220311110000', $record->timestart);
            }

            if ($record->name === 'Circuit 2') {
                $this->assertEquals('20220312110000', $record->timestart);
            }
        }

        $circuitrecords = array_values($circuitrecords);

        $stationrecords = $this->db->get_records('simulation_station');

        $this->assertTrue(isset($stationrecords) && !empty($stationrecords));

        $this->assertEquals(3, count($stationrecords));

        foreach ($stationrecords as $record) {
            if ($record->circuitid === $circuitrecords[0]->id && $record->name === 'Station 1') {
                $this->assertEquals('La station 1 du circuit 1.', $record->description);
            }

            if ($record->circuitid === $circuitrecords[1]->id && $record->name === 'Station 1') {
                $this->assertEquals('La station 1 du circuit 2.', $record->description);
            }

            if ($record->circuitid === $circuitrecords[1]->id && $record->name === 'Station 2') {
                $this->assertEquals('La station 2 du circuit 2.', $record->description);
            }
        }

        $this->unitofwork = new unitofwork($this->db, $this->classmap, self::MAIN_TABLE_NAME, $simulationid, 'simulationid');

        $data = [];

        $data[] = [
            'entityid' => $waveid,
            'repositoryname' => 'simulation_wave',
            'data' => [
                'name' => 'Vague 1a',
                'description' => 'Première vague du test.',
                'simulationid' => $simulationid,
            ],
        ];

        $this->unitofwork->save($data);

        $wavesarray = $this->unitofwork->get_registry()['simulation_wave'];

        $this->assertEquals(2, count($wavesarray));

        $totaldirtyentities = 0;

        $dirtyentities = $this->unitofwork->get_dirty();

        $this->assertFalse(empty($dirtyentities));

        array_walk_recursive($dirtyentities, function($element) use (&$totaldirtyentities) {
            if (!empty($element)) {
                $totaldirtyentities++;
            }
        });

        $this->assertEquals(10, $totaldirtyentities);

        $this->unitofwork->commit();

        $this->assertTrue($this->unitofwork->is_committed());

        $waverecords = $this->db->get_records('simulation_wave');
        $circuitrecords = $this->db->get_records('simulation_circuit');
        $stationrecords = $this->db->get_records('simulation_station');
        $substationrecords = $this->db->get_records('simulation_substation');

        $this->assertTrue(isset($waverecords) && !empty($waverecords));
        $this->assertEquals(1, count($waverecords));

        $this->assertFalse(isset($circuitrecords) && !empty($circuitrecords));
        $this->assertEquals(0, count($circuitrecords));

        $this->assertFalse(isset($stationrecords) && !empty($stationrecords));
        $this->assertEquals(0, count($stationrecords));

        $this->assertFalse(isset($substationrecords) && !empty($substationrecords));
        $this->assertEquals(0, count($substationrecords));
    }

    /**
     * Test crud_one_with_children_all_entities_with_data_in_the_database_with_classmap_order_has_no_effect method.
     *
     * @covers \local_moodleorm\unitofwork
     */
    public function test_crud_one_with_children_all_entities_with_data_in_the_database_with_classmap_order_has_no_effect() {
        $course = $this->getDataGenerator()->create_course();

        $simulation = [
            'course' => $course->id,
            'name' => 'newsim',
            'introformat' => 0,
            'scheduledelimiters' => "Arrival | -30\nInstructions | -15",
        ];

        $simulationid = $this->db->insert_record('simulation', $simulation);

        $wave = [
            'name' => 'Vague 1',
            'description' => 'Première vague du test.',
            'simulationid' => $simulationid,
        ];

        $waveid = $this->db->insert_record('simulation_wave', $wave);

        $data[] = [
            'entityid' => $waveid,
            'repositoryname' => 'simulation_wave',
            'data' => [
                'name' => 'Vague 1a',
                'description' => 'Première vague du test.',
                'simulationid' => $simulationid,
            ],
        ];

        $uuid1 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_wave',
            'entityuuid' => $uuid1,
            'parentuuid' => null,
            'data' => [
                'name' => 'Vague 1b',
                'description' => 'Deuxième vague du test.',
                'simulationid' => $simulationid,
            ],
        ];

        $uuid2 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_circuit',
            'entityuuid' => $uuid2,
            'parentuuid' => $uuid1,
            'data' => [
                'name' => 'Circuit 1',
                'timestart' => '20220311110000',
                'timeend' => '20220311120000',
            ],
        ];

        $uuid3 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_circuit',
            'entityuuid' => $uuid3,
            'parentuuid' => $uuid1,
            'data' => [
                'name' => 'Circuit 2',
                'timestart' => '20220312110000',
                'timeend' => '20220312120000',
            ],
        ];

        $uuid4 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_station',
            'entityuuid' => $uuid4,
            'parentuuid' => $uuid2,
            'data' => [
                'name' => 'Station 1',
                'description' => 'La station 1 du circuit 1.',
                'groupid' => 1,
            ],
        ];

        $uuid5 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_station',
            'entityuuid' => $uuid5,
            'parentuuid' => $uuid3,
            'data' => [
                'name' => 'Station 1',
                'description' => 'La station 1 du circuit 2.',
                'groupid' => 1,
            ],
        ];

        $uuid6 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_station',
            'entityuuid' => $uuid6,
            'parentuuid' => $uuid3,
            'data' => [
                'name' => 'Station 2',
                'description' => 'La station 2 du circuit 2.',
                'groupid' => 1,
            ],
        ];

        $uuid7 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_substation',
            'entityuuid' => $uuid7,
            'parentuuid' => $uuid5,
            'data' => [
                'name' => 'Station 1A',
                'userid' => 1,
                'role' => 3,
            ],
        ];

        $uuid8 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_substation',
            'entityuuid' => $uuid8,
            'parentuuid' => $uuid5,
            'data' => [
                'name' => 'Station 1B',
                'userid' => 1,
                'role' => 2,
            ],
        ];

        $uuid9 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_substation',
            'entityuuid' => $uuid9,
            'parentuuid' => $uuid5,
            'data' => [
                'name' => 'Station 1C',
                'userid' => 1,
                'role' => 3,
            ],
        ];

        $tempclassmap = $this->classmap;

        $tempclassmap['simulation_substation'] = $this->classmap['simulation_substation'];
        $tempclassmap['simulation_circuit'] = $this->classmap['simulation_circuit'];
        $tempclassmap['simulation_station'] = $this->classmap['simulation_station'];
        $tempclassmap['simulation_wave'] = $this->classmap['simulation_wave'];

        $this->unitofwork = new unitofwork($this->db, $tempclassmap, self::MAIN_TABLE_NAME, $simulationid, 'simulationid');

        $this->unitofwork->save($data);

        $wavesarray = $this->unitofwork->get_registry()['simulation_wave'];

        $this->assertEquals(2, count($wavesarray));

        $circuitsarray = $this->unitofwork->get_registry()['simulation_circuit'];

        $this->assertEquals(2, count($circuitsarray));

        $circuitsarray = $this->unitofwork->get_registry()['simulation_station'];

        $this->assertEquals(3, count($circuitsarray));

        foreach ($wavesarray as $entity) {
            if (!isset($entity['persistent_object']->get_data()->id)) {
                $this->assertFalse(isset($entity['hash']));
            }
        }

        foreach ($circuitsarray as $entity) {
            if (!isset($entity['persistent_object']->get_data()->id)) {
                $this->assertFalse(isset($entity['hash']));
            }
        }

        $totaldirtyentities = 0;

        $dirtyentities = $this->unitofwork->get_dirty();

        $this->assertFalse(empty($dirtyentities));

        array_walk_recursive($dirtyentities, function($element) use (&$totaldirtyentities) {
            if (!empty($element)) {
                $totaldirtyentities++;
            }
        });

        $this->assertEquals(10, $totaldirtyentities);

        $this->unitofwork->commit();

        $this->assertTrue($this->unitofwork->is_committed());

        $waverecords = $this->db->get_records('simulation_wave');

        $this->assertTrue(isset($waverecords) && !empty($waverecords));

        $this->assertEquals(2, count($waverecords));

        foreach ($waverecords as $record) {
            if ($record->name === 'Vague 1a') {
                $this->assertEquals('Première vague du test.', $record->description);
            } else if ($record->name === 'Vague 1b') {
                $this->assertEquals('Deuxième vague du test.', $record->description);
            }
        }

        $circuitrecords = $this->db->get_records('simulation_circuit');

        $this->assertTrue(isset($circuitrecords) && !empty($circuitrecords));

        $this->assertEquals(2, count($circuitrecords));

        foreach ($circuitrecords as $record) {
            if ($record->name === 'Circuit 1') {
                $this->assertEquals('20220311110000', $record->timestart);
            }

            if ($record->name === 'Circuit 2') {
                $this->assertEquals('20220312110000', $record->timestart);
            }
        }

        $circuitrecords = array_values($circuitrecords);

        $stationrecords = $this->db->get_records('simulation_station');

        $this->assertTrue(isset($stationrecords) && !empty($stationrecords));

        $this->assertEquals(3, count($stationrecords));

        foreach ($stationrecords as $record) {
            if ($record->circuitid === $circuitrecords[0]->id && $record->name === 'Station 1') {
                $this->assertEquals('La station 1 du circuit 1.', $record->description);
            }

            if ($record->circuitid === $circuitrecords[1]->id && $record->name === 'Station 1') {
                $this->assertEquals('La station 1 du circuit 2.', $record->description);
            }

            if ($record->circuitid === $circuitrecords[1]->id && $record->name === 'Station 2') {
                $this->assertEquals('La station 2 du circuit 2.', $record->description);
            }
        }

        $tempclassmap = $this->classmap;

        $tempclassmap['simulation_circuit'] = $this->classmap['simulation_circuit'];
        $tempclassmap['simulation_substation'] = $this->classmap['simulation_substation'];
        $tempclassmap['simulation_wave'] = $this->classmap['simulation_wave'];
        $tempclassmap['simulation_station'] = $this->classmap['simulation_station'];

        $this->unitofwork = new unitofwork($this->db, $tempclassmap, self::MAIN_TABLE_NAME, $simulationid, 'simulationid');

        $data = [];

        $data[] = [
            'entityid' => $waveid,
            'repositoryname' => 'simulation_wave',
            'data' => [
                'name' => 'Vague 1a',
                'description' => 'Première vague du test.',
                'simulationid' => $simulationid,
            ],
        ];

        $this->unitofwork->save($data);

        $wavesarray = $this->unitofwork->get_registry()['simulation_wave'];

        $this->assertEquals(2, count($wavesarray));

        $totaldirtyentities = 0;

        $dirtyentities = $this->unitofwork->get_dirty();

        $this->assertFalse(empty($dirtyentities));

        array_walk_recursive($dirtyentities, function($element) use (&$totaldirtyentities) {
            if (!empty($element)) {
                $totaldirtyentities++;
            }
        });

        $this->assertEquals(10, $totaldirtyentities);

        $this->unitofwork->commit();

        $this->assertTrue($this->unitofwork->is_committed());

        $waverecords = $this->db->get_records('simulation_wave');
        $circuitrecords = $this->db->get_records('simulation_circuit');
        $stationrecords = $this->db->get_records('simulation_station');
        $substationrecords = $this->db->get_records('simulation_substation');

        $this->assertTrue(isset($waverecords) && !empty($waverecords));
        $this->assertEquals(1, count($waverecords));

        $this->assertFalse(isset($circuitrecords) && !empty($circuitrecords));
        $this->assertEquals(0, count($circuitrecords));

        $this->assertFalse(isset($stationrecords) && !empty($stationrecords));
        $this->assertEquals(0, count($stationrecords));

        $this->assertFalse(isset($substationrecords) && !empty($substationrecords));
        $this->assertEquals(0, count($substationrecords));
    }

    /**
     * Test crud_one_with_children_and_delete_deleted_child_all_entities_with_data_in_the_database method.
     *
     * @covers \local_moodleorm\unitofwork
     */
    public function test_crud_one_with_children_and_delete_deleted_child_all_entities_with_data_in_the_database() {
        $course = $this->getDataGenerator()->create_course();

        $simulation = [
            'course' => $course->id,
            'name' => 'newsim',
            'introformat' => 0,
            'scheduledelimiters' => "Arrival | -30\nInstructions | -15",
        ];

        $simulationid = $this->db->insert_record('simulation', $simulation);

        $wave = [
            'name' => 'Vague 1',
            'description' => 'Première vague du test.',
            'simulationid' => $simulationid,
        ];

        $waveid = $this->db->insert_record('simulation_wave', $wave);

        $data[] = [
            'entityid' => $waveid,
            'repositoryname' => 'simulation_wave',
            'data' => [
                'name' => 'Vague 1a',
                'description' => 'Première vague du test.',
                'simulationid' => $simulationid,
            ],
        ];

        $uuid1 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_wave',
            'entityuuid' => $uuid1,
            'parentuuid' => null,
            'data' => [
                'name' => 'Vague 1b',
                'description' => 'Deuxième vague du test.',
                'simulationid' => $simulationid,
            ],
        ];

        $uuid2 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_circuit',
            'entityuuid' => $uuid2,
            'parentuuid' => $uuid1,
            'data' => [
                'name' => 'Circuit 1',
                'timestart' => '20220311110000',
                'timeend' => '20220311120000',
            ],
        ];

        $uuid3 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_circuit',
            'entityuuid' => $uuid3,
            'parentuuid' => $uuid1,
            'data' => [
                'name' => 'Circuit 2',
                'timestart' => '20220312110000',
                'timeend' => '20220312120000',
            ],
        ];

        $uuid4 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_station',
            'entityuuid' => $uuid4,
            'parentuuid' => $uuid2,
            'data' => [
                'name' => 'Station 1',
                'description' => 'La station 1 du circuit 1.',
                'groupid' => 1,
            ],
        ];

        $uuid5 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_station',
            'entityuuid' => $uuid5,
            'parentuuid' => $uuid3,
            'data' => [
                'name' => 'Station 1',
                'description' => 'La station 1 du circuit 2.',
                'groupid' => 1,
            ],
        ];

        $uuid6 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_station',
            'entityuuid' => $uuid6,
            'parentuuid' => $uuid3,
            'data' => [
                'name' => 'Station 2',
                'description' => 'La station 2 du circuit 2.',
                'groupid' => 1,
            ],
        ];

        $uuid7 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_substation',
            'entityuuid' => $uuid7,
            'parentuuid' => $uuid5,
            'data' => [
                'name' => 'Station 1A',
                'userid' => 1,
                'role' => 3,
            ],
        ];

        $uuid8 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_substation',
            'entityuuid' => $uuid8,
            'parentuuid' => $uuid5,
            'data' => [
                'name' => 'Station 1B',
                'userid' => 1,
                'role' => 2,
            ],
        ];

        $uuid9 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_substation',
            'entityuuid' => $uuid9,
            'parentuuid' => $uuid5,
            'data' => [
                'name' => 'Station 1C',
                'userid' => 1,
                'role' => 3,
            ],
        ];

        $this->unitofwork = new unitofwork($this->db, $this->classmap, self::MAIN_TABLE_NAME, $simulationid, 'simulationid');

        $this->unitofwork->save($data);

        $wavesarray = $this->unitofwork->get_registry()['simulation_wave'];

        $this->assertEquals(2, count($wavesarray));

        $circuitsarray = $this->unitofwork->get_registry()['simulation_circuit'];

        $this->assertEquals(2, count($circuitsarray));

        $circuitsarray = $this->unitofwork->get_registry()['simulation_station'];

        $this->assertEquals(3, count($circuitsarray));

        foreach ($wavesarray as $entity) {
            if (!isset($entity['persistent_object']->get_data()->id)) {
                $this->assertFalse(isset($entity['hash']));
            }
        }

        foreach ($circuitsarray as $entity) {
            if (!isset($entity['persistent_object']->get_data()->id)) {
                $this->assertFalse(isset($entity['hash']));
            }
        }

        $totaldirtyentities = 0;

        $dirtyentities = $this->unitofwork->get_dirty();

        $this->assertFalse(empty($dirtyentities));

        array_walk_recursive($dirtyentities, function($element) use (&$totaldirtyentities) {
            if (!empty($element)) {
                $totaldirtyentities++;
            }
        });

        $this->assertEquals(10, $totaldirtyentities);

        $this->unitofwork->commit();

        $this->assertTrue($this->unitofwork->is_committed());

        $waverecords = $this->db->get_records('simulation_wave');

        $this->assertTrue(isset($waverecords) && !empty($waverecords));

        $this->assertEquals(2, count($waverecords));

        foreach ($waverecords as $record) {
            if ($record->name === 'Vague 1a') {
                $this->assertEquals('Première vague du test.', $record->description);
            } else if ($record->name === 'Vague 1b') {
                $this->assertEquals('Deuxième vague du test.', $record->description);
            }
        }

        $circuitrecords = $this->db->get_records('simulation_circuit');

        $this->assertTrue(isset($circuitrecords) && !empty($circuitrecords));

        $this->assertEquals(2, count($circuitrecords));

        foreach ($circuitrecords as $record) {
            if ($record->name === 'Circuit 1') {
                $this->assertEquals('20220311110000', $record->timestart);
            }

            if ($record->name === 'Circuit 2') {
                $this->assertEquals('20220312110000', $record->timestart);
            }
        }

        $circuitrecords = array_values($circuitrecords);

        $stationrecords = $this->db->get_records('simulation_station');

        $this->assertTrue(isset($stationrecords) && !empty($stationrecords));

        $this->assertEquals(3, count($stationrecords));

        foreach ($stationrecords as $record) {
            if ($record->circuitid === $circuitrecords[0]->id && $record->name === 'Station 1') {
                $this->assertEquals('La station 1 du circuit 1.', $record->description);
            }

            if ($record->circuitid === $circuitrecords[1]->id && $record->name === 'Station 1') {
                $this->assertEquals('La station 1 du circuit 2.', $record->description);
            }

            if ($record->circuitid === $circuitrecords[1]->id && $record->name === 'Station 2') {
                $this->assertEquals('La station 2 du circuit 2.', $record->description);
            }
        }

        $this->unitofwork = new unitofwork($this->db, $this->classmap, self::MAIN_TABLE_NAME, $simulationid, 'simulationid');

        $data = [];

        $data[] = [
            'entityid' => $waveid,
            'repositoryname' => 'simulation_wave',
            'data' => [
                'name' => 'Vague 1a',
                'description' => 'Première vague du test.',
                'simulationid' => $simulationid,
            ],
        ];

        $this->unitofwork->save($data);

        $wavesarray = $this->unitofwork->get_registry()['simulation_wave'];

        $this->assertEquals(2, count($wavesarray));

        $totaldirtyentities = 0;

        $dirtyentities = $this->unitofwork->get_dirty();

        $this->assertFalse(empty($dirtyentities));

        array_walk_recursive($dirtyentities, function($element) use (&$totaldirtyentities) {
            if (!empty($element)) {
                $totaldirtyentities++;
            }
        });

        $this->assertEquals(10, $totaldirtyentities);

        $this->unitofwork->commit();

        $this->assertTrue($this->unitofwork->is_committed());

        $waverecords = $this->db->get_records('simulation_wave');
        $circuitrecords = $this->db->get_records('simulation_circuit');
        $stationrecords = $this->db->get_records('simulation_station');
        $substationrecords = $this->db->get_records('simulation_substation');

        $this->assertTrue(isset($waverecords) && !empty($waverecords));
        $this->assertEquals(1, count($waverecords));

        $this->assertFalse(isset($circuitrecords) && !empty($circuitrecords));
        $this->assertEquals(0, count($circuitrecords));

        $this->assertFalse(isset($stationrecords) && !empty($stationrecords));
        $this->assertEquals(0, count($stationrecords));

        $this->assertFalse(isset($substationrecords) && !empty($substationrecords));
        $this->assertEquals(0, count($substationrecords));
    }

    /**
     * Test crud_one_with_children_and_one_bad_update_all_entities_with_data_in_the_database method.
     *
     * @covers \local_moodleorm\unitofwork
     */
    public function test_crud_one_with_children_and_one_bad_update_all_entities_with_data_in_the_database() {
        $course = $this->getDataGenerator()->create_course();

        $simulation = [
            'course' => $course->id,
            'name' => 'newsim',
            'introformat' => 0,
            'scheduledelimiters' => "Arrival | -30\nInstructions | -15",
        ];

        $simulationid = $this->db->insert_record('simulation', $simulation);

        $wave = [
            'name' => 'Vague 1',
            'description' => 'Première vague du test.',
            'simulationid' => $simulationid,
        ];

        $waveid = $this->db->insert_record('simulation_wave', $wave);

        $data[] = [
            'entityid' => $waveid,
            'repositoryname' => 'simulation_wave',
            'data' => [
                'name' => 'Vague 1a',
                'description' => 'Première vague du test.',
                'simulationid' => $simulationid,
            ],
        ];

        $uuid1 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_wave',
            'entityuuid' => $uuid1,
            'parentuuid' => null,
            'data' => [
                'name' => 'Vague 1b',
                'description' => 'Deuxième vague du test.',
                'simulationid' => $simulationid,
            ],
        ];

        $uuid2 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_circuit',
            'entityuuid' => $uuid2,
            'parentuuid' => $uuid1,
            'data' => [
                'name' => 'Circuit 1',
                'timestart' => '20220311110000',
                'timeend' => '20220311120000',
            ],
        ];

        $uuid3 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_circuit',
            'entityuuid' => $uuid3,
            'parentuuid' => $uuid1,
            'data' => [
                'name' => 'Circuit 2',
                'timestart' => '20220312110000',
                'timeend' => '20220312120000',
            ],
        ];

        $uuid4 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_station',
            'entityuuid' => $uuid4,
            'parentuuid' => $uuid2,
            'data' => [
                'name' => 'Station 1',
                'description' => 'La station 1 du circuit 1.',
                'groupid' => 1,
            ],
        ];

        $uuid5 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_station',
            'entityuuid' => $uuid5,
            'parentuuid' => $uuid3,
            'data' => [
                'name' => 'Station 1',
                'description' => 'La station 1 du circuit 2.',
                'groupid' => 1,
            ],
        ];

        $uuid6 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_station',
            'entityuuid' => $uuid6,
            'parentuuid' => $uuid3,
            'data' => [
                'name' => 'Station 2',
                'description' => 'La station 2 du circuit 2.',
                'groupid' => 1,
            ],
        ];

        $uuid7 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_substation',
            'entityuuid' => $uuid7,
            'parentuuid' => $uuid5,
            'data' => [
                'name' => 'Station 1A',
                'userid' => 1,
                'role' => 3,
            ],
        ];

        $uuid8 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_substation',
            'entityuuid' => $uuid8,
            'parentuuid' => $uuid5,
            'data' => [
                'name' => 'Station 1B',
                'userid' => 1,
                'role' => 2,
            ],
        ];

        $uuid9 = bin2hex(random_bytes(20));

        $data[] = [
            'repositoryname' => 'simulation_substation',
            'entityuuid' => $uuid9,
            'parentuuid' => $uuid5,
            'data' => [
                'name' => 'Station 1C',
                'userid' => 1,
                'role' => 3,
            ],
        ];

        $this->unitofwork = new unitofwork($this->db, $this->classmap, self::MAIN_TABLE_NAME, $simulationid, 'simulationid');

        $this->unitofwork->save($data);

        $wavesarray = $this->unitofwork->get_registry()['simulation_wave'];

        $this->assertEquals(2, count($wavesarray));

        $circuitsarray = $this->unitofwork->get_registry()['simulation_circuit'];

        $this->assertEquals(2, count($circuitsarray));

        $circuitsarray = $this->unitofwork->get_registry()['simulation_station'];

        $this->assertEquals(3, count($circuitsarray));

        foreach ($wavesarray as $entity) {
            if (!isset($entity['persistent_object']->get_data()->id)) {
                $this->assertFalse(isset($entity['hash']));
            }
        }

        foreach ($circuitsarray as $entity) {
            if (!isset($entity['persistent_object']->get_data()->id)) {
                $this->assertFalse(isset($entity['hash']));
            }
        }

        $totaldirtyentities = 0;

        $dirtyentities = $this->unitofwork->get_dirty();

        $this->assertFalse(empty($dirtyentities));

        array_walk_recursive($dirtyentities, function($element) use (&$totaldirtyentities) {
            if (!empty($element)) {
                $totaldirtyentities++;
            }
        });

        $this->assertEquals(10, $totaldirtyentities);

        $this->unitofwork->commit();

        $this->assertTrue($this->unitofwork->is_committed());

        $waverecords = $this->db->get_records('simulation_wave');

        $this->assertTrue(isset($waverecords) && !empty($waverecords));

        $this->assertEquals(2, count($waverecords));

        foreach ($waverecords as $record) {
            if ($record->name === 'Vague 1a') {
                $this->assertEquals('Première vague du test.', $record->description);
            } else if ($record->name === 'Vague 1b') {
                $this->assertEquals('Deuxième vague du test.', $record->description);
            }
        }

        $circuitrecords = $this->db->get_records('simulation_circuit');

        $this->assertTrue(isset($circuitrecords) && !empty($circuitrecords));

        $this->assertEquals(2, count($circuitrecords));

        foreach ($circuitrecords as $record) {
            if ($record->name === 'Circuit 1') {
                $this->assertEquals('20220311110000', $record->timestart);
            }

            if ($record->name === 'Circuit 2') {
                $this->assertEquals('20220312110000', $record->timestart);
            }
        }

        $circuitrecords = array_values($circuitrecords);

        $stationrecords = $this->db->get_records('simulation_station');

        $this->assertTrue(isset($stationrecords) && !empty($stationrecords));

        $this->assertEquals(3, count($stationrecords));

        foreach ($stationrecords as $record) {
            if ($record->circuitid === $circuitrecords[0]->id && $record->name === 'Station 1') {
                $this->assertEquals('La station 1 du circuit 1.', $record->description);
            }

            if ($record->circuitid === $circuitrecords[1]->id && $record->name === 'Station 1') {
                $this->assertEquals('La station 1 du circuit 2.', $record->description);
            }

            if ($record->circuitid === $circuitrecords[1]->id && $record->name === 'Station 2') {
                $this->assertEquals('La station 2 du circuit 2.', $record->description);
            }
        }

        $this->unitofwork = new unitofwork($this->db, $this->classmap, self::MAIN_TABLE_NAME, $simulationid, 'simulationid');

        $data = [];

        $data[] = [
            'entityid' => $waveid,
            'repositoryname' => 'simulation_wave',
            'data' => [
                'name' => 'Vague 1a',
                'description' => 'Première vague du test.',
                'simulationid' => $simulationid,
            ],
        ];

        $this->unitofwork->save($data);

        $wavesarray = $this->unitofwork->get_registry()['simulation_wave'];

        $this->assertEquals(2, count($wavesarray));

        $totaldirtyentities = 0;

        $dirtyentities = $this->unitofwork->get_dirty();

        $this->assertFalse(empty($dirtyentities));

        array_walk_recursive($dirtyentities, function($element) use (&$totaldirtyentities) {
            if (!empty($element)) {
                $totaldirtyentities++;
            }
        });

        $this->assertEquals(10, $totaldirtyentities);

        $this->unitofwork->commit();

        $this->assertTrue($this->unitofwork->is_committed());

        $waverecords = $this->db->get_records('simulation_wave');
        $circuitrecords = $this->db->get_records('simulation_circuit');
        $stationrecords = $this->db->get_records('simulation_station');
        $substationrecords = $this->db->get_records('simulation_substation');

        $this->assertTrue(isset($waverecords) && !empty($waverecords));
        $this->assertEquals(1, count($waverecords));

        $this->assertFalse(isset($circuitrecords) && !empty($circuitrecords));
        $this->assertEquals(0, count($circuitrecords));

        $this->assertFalse(isset($stationrecords) && !empty($stationrecords));
        $this->assertEquals(0, count($stationrecords));

        $this->assertFalse(isset($substationrecords) && !empty($substationrecords));
        $this->assertEquals(0, count($substationrecords));
    }
}
