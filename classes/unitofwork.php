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
 * Class of a unit of work for persistence management.
 *
 * @package    local_moodleorm
 * @copyright  2021 Andrew Caya <andrewscaya@yahoo.ca>
 * @author     Andrew Caya <andrewscaya@yahoo.ca>
 * @license    https://directory.fsf.org/wiki/License:Apache-2.0 Apache v2
 */

namespace local_moodleorm;

/**
 * Class for unit of work persistence of Moodle persistent entities.
 */
class unitofwork {
    /**
     * @var string Contains a mapping of the main database table id with the table's name as its id index key.
     */
    protected $maintablename = '';

    /**
     * @var int Contains a mapping of the main database table id with the table's name as its id index key.
     */
    protected $maintablekey = 0;

    /**
     * @var string Contains the name of the child's column name which contains the parent's foreign key.
     *
     * Optional - can be a self-referenced column for single tables without child tables.
     */
    protected $childparentidcolumnname = '';

    /**
     * @var \moodle_database Contains a Moodle database connection $DB.
     */
    protected $db;

    /**
     * @var array Contains a list of objects containing the parameters of this activity (id) in the main table.
     */
    protected $maintablesettings = [];

    /**
     * @var array Contains a list of mapped persistent classes (dependencies are sub-arrays).
     */
    protected $classmap = [];

    /**
     * @var array Contains registered persistent objects.
     */
    protected $registry = [];

    /**
     * @var array Contains keys of dirty persistent objects.
     */
    protected $dirty = [];

    /**
     * @var array Contains references to staged persistent objects.
     */
    protected $stage = [];

    /**
     * @var bool Flag representing current Unit of Work state.
     */
    protected $committed = false;

    /**
     * Class constructor method.
     *
     * @param \moodle_database $DB
     * @param array $classmap
     * @param string $maintablename
     * @param int $maintablekey
     * @param string $childparentidcolumnname defaults to null
     * @param bool $enableconstraints defaults to false
     *
     * @throws \dml_exception
     */
    public function __construct(
        \moodle_database $DB,
        array $classmap,
        string $maintablename,
        int $maintablekey,
        string $childparentidcolumnname = null,
        bool $enableconstraints = false
    ) {
        $this->db = $DB;
        $this->maintablename = $maintablename;
        $this->maintablekey = $maintablekey;
        $this->childparentidcolumnname = $childparentidcolumnname ?? '';
        $this->classmap_bubble_sort($classmap);

        if ($enableconstraints) {
            foreach ($this->classmap as $parentrepositoryname => $parentclassinfo) {
                list($parentrepositoryname, $parentclassname, $parentradical) = $this->get_class_information($parentrepositoryname);

                $classchildren = $this->get_class_children($parentclassname);

                foreach ($classchildren as $childrepositoryname => $childclassinformation) {
                    $this->try_add_cascade_delete_check(
                        $parentrepositoryname,
                        $childrepositoryname,
                        $parentradical . 'id',
                        'id'
                    );
                }
            }
        }

        $this->read_all();
    }

    /**
     * Class destructor method.
     */
    public function __destruct() {
        if ($this->committed === false) {
            return $this->commit();
        }
    }

    /**
     * Getter method of the $maintablesettings property.
     *
     * @return array
     */
    public function get_maintable_settings(): array {
        return $this->maintablesettings;
    }

    /**
     * Getter method of the $classmap property.
     *
     * @return array
     */
    public function get_classmap(): array {
        return $this->classmap;
    }

    /**
     * Getter method of the $registry property.
     *
     * @return array
     */
    public function get_registry(): array {
        return $this->registry;
    }

    /**
     * Getter method of the $dirty property.
     *
     * @return array
     */
    public function get_dirty(): array {
        return $this->dirty;
    }

    /**
     * Getter method of the $stage property.
     *
     * @return array
     */
    public function get_stage(): array {
        return $this->stage;
    }

    /**
     * Getter method of the $committed property.
     *
     * @return bool
     */
    public function is_committed(): bool {
        return $this->committed;
    }

    /**
     * Method to check if the Unit of Work needs to commit any changes.
     *
     * @return bool
     */
    public function is_dirty(): bool {
        foreach ($this->dirty as $action) {
            if (!empty($action)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Commit all changes to the database, using a single transaction.
     *
     * @return bool
     */
    public function commit() {
        if ($this->committed) {
            return false;
        } else {
            $this->committed = true;
        }

        try {
            try {
                $transaction = $this->db->start_delegated_transaction();

                if (is_array($this->registry) && !empty($this->registry)) {
                    if (isset($this->dirty['create'])
                        && is_array($this->dirty['create'])
                        && !empty($this->dirty['create'])
                    ) {
                        foreach ($this->dirty['create'] as $repositoryname => &$entitynames) {
                            array_walk($entitynames, function(&$entityname) use (&$repositoryname) {
                                if (
                                    array_key_exists($entityname, $this->registry[$repositoryname])
                                    && !isset($this->registry[$repositoryname][$entityname]['persistent_object']->get_data()->id)
                                    && !isset($this->registry[$repositoryname][$entityname]['hash'])
                                ) {
                                    if (isset($this->registry[$repositoryname][$entityname]['parentuuid'])) {
                                        $parentuuid = $this->registry[$repositoryname][$entityname]['parentuuid'];

                                        foreach ($this->registry as $subrepositoryname => $subitem) {
                                            if (is_array($subitem)) {
                                                array_walk($subitem,
                                                    function($subentity, $subentityname)
                                                    use ($repositoryname, $entityname, $parentuuid) {
                                                        if (is_array($subentity)
                                                            && isset($subentity['entityuuid'])
                                                            && $subentity['entityuuid'] === $parentuuid
                                                        ) {
                                                            $id = $subentity['persistent_object']->get_data()->id;
                                                            $propertyname = explode('_', $subentityname)[0] . 'id';

                                                            $this->registry[$repositoryname][$entityname]['persistent_object']
                                                                ->set(
                                                                    $propertyname,
                                                                    $id
                                                                );
                                                        }
                                                    }
                                                );
                                            }
                                        }
                                    }

                                    $this->registry[$repositoryname][$entityname]['persistent_object']->create();

                                    $this->stage[spl_object_id($this->registry[$repositoryname][$entityname]['persistent_object'])]
                                        = &$this->registry[$repositoryname][$entityname];

                                    unset($this->dirty['create'][$repositoryname][$entityname]);
                                }
                            });
                        }
                    }

                    if (isset($this->dirty['update'])
                        && is_array($this->dirty['update'])
                        && !empty($this->dirty['update'])
                    ) {
                        foreach ($this->dirty['update'] as $repositoryname => &$entitynames) {
                            array_walk($entitynames, function(&$entityname) use (&$repositoryname) {
                                if (
                                    array_key_exists($entityname, $this->registry[$repositoryname])
                                    && $this->registry[$repositoryname][$entityname]['persistent_object']->get_data()->id > 0
                                    && isset($this->registry[$repositoryname][$entityname]['hash'])
                                    && $this->hash_sign(
                                        json_encode($this->registry[$repositoryname][$entityname]['persistent_object']->get_data()
                                        )
                                        !== $this->registry[$repositoryname][$entityname]['hash'])
                                ) {
                                    $this->registry[$repositoryname][$entityname]['persistent_object']->update();

                                    $this->stage[spl_object_id($this->registry[$repositoryname][$entityname]['persistent_object'])]
                                        = &$this->registry[$repositoryname][$entityname];

                                    unset($this->dirty['update'][$repositoryname][$entityname]);
                                }
                            });
                        }
                    }

                    if (isset($this->dirty['delete'])
                        && is_array($this->dirty['delete'])
                        && !empty($this->dirty['delete'])
                    ) {
                        foreach ($this->dirty['delete'] as $repositoryname => &$entitynames) {
                            array_walk($entitynames, function($entityname) use (&$repositoryname) {
                                $id = $this->registry[$repositoryname][$entityname]['persistent_object']->get_data()->id;

                                if (
                                    array_key_exists($entityname, $this->registry[$repositoryname])
                                    && $id > 0
                                    && !isset($this->registry[$repositoryname][$entityname]['hash'])
                                ) {
                                    $this->registry[$repositoryname][$entityname]['persistent_object']->delete();

                                    $this->stage[spl_object_id($this->registry[$repositoryname][$entityname]['persistent_object'])]
                                        = &$this->registry[$repositoryname][$entityname];

                                    unset($this->dirty['delete'][$repositoryname][$entityname]);
                                }
                            });
                        }
                    }
                }

                $transaction->allow_commit();
            } catch (\Exception $e) {
                // Make sure transaction is valid.
                if (!empty($transaction) && !$transaction->is_disposed()) {
                    $transaction->rollback($e);
                }

                \core\notification::error($e->getMessage());
            }
        } catch (\Exception $e) {
            \core\notification::error($e->getMessage());
        }

        foreach ($this->stage as &$committed) {
            unset($committed);
        }

        return $this->is_committed();
    }

    /**
     * Export (hydrate and format) all registry data to an array that is compatible
     * with the save() method.
     *
     * @return array|false
     */
    public function export_data() {
        if ($this->is_dirty() || $this->is_committed()) {
            return false;
        } else {
            $data = [];

            foreach ($this->registry as $repositoryname => $repository) {
                if (is_array($repository) && !empty($repository)) {
                    array_walk($repository, function($item, $entityname) use (&$data, &$repositoryname) {
                        $payload = json_decode(json_encode($item['persistent_object']->get_data()), true);
                        $data[] = [
                            'entityid' => $payload['id'],
                            'repositoryname' => $repositoryname,
                            'data' => $payload,
                        ];
                    });
                }
            }

            return $data;
        }
    }

    /**
     * Save all data to the registry and mark the appropriate entities as dirty.
     *
     * @param array $data
     *
     * @return bool
     */
    public function save(array $data) {
        if (!$this->sort_dependencies()) {
            return false;
        }

        try {
            foreach ($data as $datum) {
                if (is_array($datum) && isset($datum['repositoryname'])) {
                    if (!isset($datum['entityid'])) {
                        if (list($repositoryname, $classname, $radical) = $this->get_class_information($datum['repositoryname'])) {
                            $newrecord = (object) $datum['data'];
                            $persistentobject = new $classname(null, $newrecord);
                            $tempid = spl_object_id($persistentobject);

                            $this->registry[$repositoryname][$radical . '_' . $tempid] = [
                                'persistent_object' => $persistentobject,
                                'entityuuid' => $datum['entityuuid'] ?? null,
                                'parentuuid' => $datum['parentuuid'] ?? null,
                            ];

                            $this->dirty['create'][$repositoryname][] = $radical . '_' . $tempid;
                        }
                    } else if (isset($datum['entityid'])) {
                        foreach ($this->registry as $repositoryname => &$item) {
                            if ($repositoryname === $datum['repositoryname']) {
                                $id = (string) $datum['entityid'];
                                $datum['data']['id'] = $id;

                                if (list($repositoryname, $classname, $radical) = $this->get_class_information($repositoryname)) {
                                    if (array_key_exists($radical . '_' . $id, $item)) {
                                        $propertynames = array_keys($datum['data']);

                                        foreach ($propertynames as $propertyname) {
                                            $this->registry[$repositoryname][$radical . '_' . $id]['persistent_object']
                                                ->set($propertyname, $datum['data'][$propertyname]);
                                        }

                                        $this->dirty['update'][$repositoryname][] = $radical . '_' . $id;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            return false;
        }

        foreach ($this->registry as $repositoryname => &$item) {
            if (is_array($item)) {
                array_walk($item, function(&$entity, $entityname) use (&$repositoryname) {
                    if (list($repositoryname, $classname, $radical) = $this->get_class_information($repositoryname)) {
                        if (
                            (isset($this->dirty['create'][$repositoryname])
                                && !in_array($entityname, $this->dirty['create'][$repositoryname]))
                            && (isset($this->dirty['update'][$repositoryname])
                                && !in_array($entityname, $this->dirty['update'][$repositoryname]))
                        ) {
                            $entity['hash'] = null;

                            $this->dirty['delete'][$repositoryname][] = $entityname;
                        }
                    }
                });
            }
        }

        return true;
    }

    /**
     * Bubble sort the classmap array, based on parenthood.
     *
     * @param array $classmap
     *
     * @return bool Array was sorted
     */
    protected function classmap_bubble_sort(array $classmap) {
        $parentclassmap = [];

        foreach ($classmap as $classrepositoryname => $classinfo) {
            if (empty($classinfo['parent_class_fqn'])) {
                $parentclassmap[$classrepositoryname] = $classinfo;

                unset($classmap[$classrepositoryname]);
            }
        }

        $newclassmap = array_merge($parentclassmap, $classmap);

        $classmap = $newclassmap;

        $tempclassmap = [];

        foreach ($classmap as $classrepositoryname => $classinfo) {
            $tempclassmap[] = array_merge(['classrepositoryname' => $classrepositoryname], $classinfo);
        }

        $size = count($tempclassmap) - 1;

        for ($i = 0; $i < $size; $i++) {
            for ($j = 0; $j < $size - $i; $j++) {
                $k = $j + 1;
                if ($tempclassmap[$j]['parent_class_fqn'] === $tempclassmap[$k]['class_fqn']) {
                    // Swap elements.
                    list($tempclassmap[$j], $tempclassmap[$k]) = [$tempclassmap[$k], $tempclassmap[$j]];
                }
            }
        }

        foreach ($tempclassmap as $classentity) {
            $this->classmap[$classentity['classrepositoryname']] = $classentity;

            unset($this->classmap[$classentity['classrepositoryname']]['classrepositoryname']);
        }

        return true;
    }

    /**
     * Get all child classes of a given parent class.
     *
     * @param string $classname
     *
     * @return array
     */
    protected function get_class_children(string $classname) {
        $children = [];

        foreach ($this->classmap as $classrepositoryname => $classinfo) {
            if (isset($classinfo['parent_class_fqn']) && $classinfo['parent_class_fqn'] === $classname) {
                $children[$classrepositoryname] = $this->get_class_information($classrepositoryname);
            }
        }

        return $children;
    }

    /**
     * Get all useful information concerning a particular class map.
     *
     * @param string $repositoryname
     *
     * @return array|false
     */
    protected function get_class_information(string $repositoryname) {
        if (!isset($this->classmap[$repositoryname])) {
            return false;
        }

        $classname = $this->classmap[$repositoryname]['class_fqn'];
        $radical = array_reverse(explode('\\', $classname))[0];

        return [$repositoryname, $classname, $radical];
    }

    /**
     * Sign all committed entities with a hash.
     *
     * @param string $jsonstring
     *
     * @return false|string
     */
    protected function hash_sign(string $jsonstring) {
        return hash('sha512', $jsonstring);
    }

    /**
     * Read all the data from the database and store the corresponding entities to the registry.
     *
     * @return bool
     *
     * @throws \dml_exception
     */
    protected function read_all() {
        if (!empty($this->maintablename) && $this->maintablekey != 0 && empty($this->childparentidcolumnname)) {
            $this->maintablesettings = $this->db->get_records($this->maintablename, ['id' => $this->maintablekey]);
        } else if (!empty($this->maintablename) && $this->maintablekey != 0 && !empty($this->childparentidcolumnname)) {
            $this->maintablesettings = $this->db->get_records($this->maintablename, ['id' => $this->maintablekey]);
        } else {
            $this->maintablesettings = [];
        }

        if (is_array($this->maintablesettings) && !empty($this->maintablesettings)) {
            foreach ($this->classmap as $repositoryname => $item) {
                if (is_array($item) && empty($item['parent_class_fqn'])) {
                    if (list($repositoryname, $classname, $radical) = $this->get_class_information($repositoryname)) {
                        $table = $classname::TABLE;

                        if ($table === $this->maintablename && empty($this->childparentidcolumnname)) {
                            $childparentidcolumnname = 'id';
                        } else if (!empty($this->childparentidcolumnname)) {
                            $childparentidcolumnname = $this->childparentidcolumnname;
                        } else {
                            $childparentidcolumnname = $this->maintablename . 'id';
                        }

                        if (isset(reset($this->maintablesettings)->$childparentidcolumnname)
                            && !is_null(reset($this->maintablesettings)->$childparentidcolumnname)
                        ) {
                            $tablekey = reset($this->maintablesettings)->$childparentidcolumnname;
                        } else {
                            $tablekey = $this->maintablekey;
                        }

                        if ($results = $this->db->get_records($table, [$childparentidcolumnname => $tablekey], 'id')) {
                            foreach ($results as $row) {
                                try {
                                    $persistentobject = new $classname($row->id);
                                } catch (\Exception $e) {
                                    throw new \Exception($e->getMessage());
                                }

                                $hash = $this->hash_sign(json_encode($persistentobject->get_data()));

                                $this->registry[$repositoryname][$radical . '_' . $row->id] = [
                                    'persistent_object' => $persistentobject,
                                    'hash' => $hash,
                                ];
                            }
                        }
                    }
                }
            }

            foreach ($this->classmap as $repositoryname => $item) {
                if (is_array($item) && !empty($item['parent_class_fqn'])) {
                    $parentclassfqn = $item['parent_class_fqn'];

                    if (list($repositoryname, $classname, $radical) = $this->get_class_information($repositoryname)) {
                        foreach ($this->classmap as $parentname => $parentitem) {
                            if ($parentname !== $repositoryname && in_array($parentclassfqn, $parentitem)) {
                                $parentrepositoryname = $parentname;
                            }
                        }

                        $table = $classname::TABLE;
                        $columnname = array_reverse(explode('_', $parentrepositoryname))[0] . 'id';

                        if (isset($this->registry[$parentrepositoryname]) && is_array($this->registry[$parentrepositoryname])) {
                            array_walk($this->registry[$parentrepositoryname],
                                function($parentitem, $parentname)
                                use ($repositoryname, $classname, $table, $radical, $columnname) {
                                    $id = array_reverse(explode('_', $parentname))[0];

                                    if ($results = $this->db->get_records($table, [$columnname => $id], 'id')) {
                                        foreach ($results as $row) {
                                            $persistentobject = new $classname($row->id);
                                            $hash = $this->hash_sign(json_encode($persistentobject->get_data()));

                                            $this->registry[$repositoryname][$radical . '_' . $row->id] = [
                                                'persistent_object' => $persistentobject,
                                                'hash' => $hash,
                                            ];
                                        }
                                    }
                                }
                            );
                        }
                    }
                }
            }

            return true;
        } else {
            return false;
        }
    }

    /**
     * Sort the class map by class dependencies.
     *
     * @return bool
     */
    protected function sort_dependencies(): bool {
        $dependencysortedarray = [];

        array_walk($this->classmap, function($classinfo, $repositoryname) use (&$dependencysortedarray) {
            if (empty($classinfo['parent_class_fqn'])) {
                $dependencysortedarray[$repositoryname] = [];
            } else {
                $dependencysortedarray[$repositoryname] = [];
            }
        });

        foreach ($this->classmap as $repositoryname => $classinfo) {
            $dependencysortedarray[$repositoryname] = [];
        }

        $this->dirty['create'] = $dependencysortedarray;
        $this->dirty['update'] = $dependencysortedarray;
        $this->dirty['delete'] = array_reverse($dependencysortedarray);

        return true;
    }

    /**
     * Try to add a cascade delete to the database tables.
     *
     * @param string $parent The parent (one) table in the relationship.
     * @param string $tablename The child (many) table in the relationship.
     * @param string $fieldname The child field that may contain the parent id.
     * @param string $indexname The indexname upon which to base the constraint name.
     *
     * @return bool Whether a relationship was added.
     *
     * @copyright  2015 Catalyst IT
     * @author     Nigel Cunningham <nigelc@catalyst-au.net>
     * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
     * @author     Andrew Caya <andrewscaya@yahoo.ca>
     * @license    https://directory.fsf.org/wiki/License:Apache-2.0 Apache v2
     */
    protected function try_add_cascade_delete($parent, $tablename, $fieldname, $indexname): bool {
        try {
            $this->db->execute(
                "DELETE FROM {{$tablename}} WHERE NOT EXISTS (
                        SELECT 1 FROM {{$parent}} WHERE {{$tablename}} . {$fieldname} = {{$parent}} . id)");

            $this->db->execute("ALTER TABLE {{$tablename}}
                       ADD CONSTRAINT c_{{$tablename}}_{{$parent}}_{$indexname}
                          FOREIGN KEY ({$fieldname})
                           REFERENCES {{$parent}}(id)
                    ON DELETE CASCADE");

            return true;
        } catch (\dml_write_exception $e) {
            if (substr($e->error, -14) == "already exists") {
                return true;
            } else {
                \core\notification::error(
                    "Failed ({$e->getMessage()}).\n"
                );
            }
        } catch (\dml_read_exception $e) {
            // Trying to match fields of different types?
            if (substr($e->error, 0, 32) == "ERROR:  operator does not exist:") {
                \core\notification::error(
                    "ID field from {$parent} table and {$fieldname} from {$tablename} have different data types.\n"
                );
            } else if (substr($e->error, 0, 16) == "ERROR:  relation") {
                \core\notification::error(
                    "{$tablename} table missing?! Perhaps there's an upgrade to be done.\n"
                );
            } else {
                \core\notification::error(
                    "Failed ({$e->getMessage()}).\n"
                );
            }
        }

        return false;
    }

    /**
     * Try to remove a cascade delete to the database tables.
     *
     * @param string $parent The parent (one) table in the relationship.
     * @param string $tablename The child (many) table in the relationship.
     * @param string $fieldname The child field that may contain the parent id.
     * @param string $indexname The indexname upon which to base the constraint name.
     *
     * @return bool Whether a relationship was removed.
     *
     * @copyright  2021 Andrew Caya <andrewscaya@yahoo.ca>
     * @author     Andrew Caya <andrewscaya@yahoo.ca>
     * @license    https://directory.fsf.org/wiki/License:Apache-2.0 Apache v2
     */
    protected function try_remove_cascade_delete($parent, $tablename, $fieldname, $indexname): bool {
        try {
            $this->db->execute("ALTER TABLE {{$tablename}}
                       DROP INDEX c_{{$tablename}}_{{$parent}}_{$indexname}
                    ");

            return true;
        } catch (\dml_write_exception $e) {
            \core\notification::error(
                "Failed ({$e->getMessage()}).\n"
            );
        } catch (\dml_read_exception $e) {
            // Trying to match fields of different types?
            if (substr($e->error, 0, 32) == "ERROR:  operator does not exist:") {
                \core\notification::error(
                    "ID field from {$parent} table and {$fieldname} from {$tablename} have different data types.\n"
                );
            } else if (substr($e->error, 0, 16) == "ERROR:  relation") {
                \core\notification::error(
                    "{$tablename} table missing?! Perhaps there's an upgrade to be done.\n"
                );
            } else {
                \core\notification::error(
                    "Failed ({$e->getMessage()}).\n"
                );
            }
        }

        return false;
    }

    /**
     * Check if it is necessary to add a cascade delete constraint to the database tables.
     *
     * @param string $parent The parent (one) table in the relationship.
     * @param string $tablename The child (many) table in the relationship.
     * @param string $fieldname The child field that may contain the parent id.
     * @param string $indexname The indexname upon which to base the constraint name.
     *
     * @return bool Whether a relationship was found or created.
     *
     * @copyright  2021 Andrew Caya <andrewscaya@yahoo.ca>
     * @author     Andrew Caya <andrewscaya@yahoo.ca>
     * @license    https://directory.fsf.org/wiki/License:Apache-2.0 Apache v2
     */
    public function try_add_cascade_delete_check($parent, $tablename, $fieldname, $indexname): bool {
        $indexes = $this->db->get_indexes($tablename);

        if (!empty($indexes)) {
            foreach ($indexes as $index => $details) {
                if (isset($details['columns']) && in_array($fieldname, $details['columns'])) {
                    return true;
                }
            }

            return $this->try_add_cascade_delete($parent, $tablename, $fieldname, $indexname);
        } else {
            return $this->try_add_cascade_delete($parent, $tablename, $fieldname, $indexname);
        }
    }

    /**
     * Check if it is necessary to remove a cascade delete constraint from the database tables.
     *
     * @return bool Whether a relationship was found and removed.
     *
     * @copyright  2021 Andrew Caya <andrewscaya@yahoo.ca>
     * @author     Andrew Caya <andrewscaya@yahoo.ca>
     * @license    https://directory.fsf.org/wiki/License:Apache-2.0 Apache v2
     */
    public function try_remove_cascade_delete_check(): bool {
        foreach ($this->classmap as $parentrepositoryname => $parentclassinfo) {
            list($parent, $parentclassname, $parentradical) = $this->get_class_information($parentrepositoryname);
            $fieldname = $parentradical . 'id';
            $indexname = 'id';
            $classchildren = $this->get_class_children($parentclassname);

            foreach ($classchildren as $childrepositoryname => $childclassinformation) {
                $tablename = $childrepositoryname;

                $indexes = $this->db->get_indexes($tablename);

                if (!empty($indexes)) {
                    foreach ($indexes as $index => $details) {
                        if (isset($details['columns']) && in_array($fieldname, $details['columns'])) {
                            return true;
                        }
                    }

                    return $this->try_remove_cascade_delete($parent, $tablename, $fieldname, $indexname);
                } else {
                    return $this->try_remove_cascade_delete($parent, $tablename, $fieldname, $indexname);
                }
            }
        }

        return false;
    }
}
