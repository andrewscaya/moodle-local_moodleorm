.. _ConfigurationAnchor:

.. index:: Configuration

.. _configuration:

Configuration
=============

The plugin's configuration is set by passing the following parameters to the ``unitofwork``'s constructor
upon object instantiation. The constructor's function signature is as follows:

.. code-block:: php

    public function __construct(\moodle_database $DB, array $classmap, string $maintablename, int $maintablekey, string $childparentidcolumnname = null, bool $enableconstraints = false) {}

The first parameter is an instance of the Moodle database abstraction layer (global $DB).

The second parameter is an array containing a classmap of the tables that the Unit of Work should manage
for you. The array should have the following layout: the array must contain an array for each database table
that should be managed. Each one of these arrays should have an index with the name of the table, that is
associated to an array containing these two elements: the fully-qualified class name (FQCN) of the Moodle
entity that is to be mapped to the table, and an optional element containing the FQCN of its parent entity.

Here is an example of a classmap for four tables with three parent/child relationships, in concatenation,
where one child becomes the parent of the next child:

.. code-block:: php

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

By default, an entity that does not have a parent is considered to be the fully-managed (CRUD) child of the
main table, which, in turn, should always be read-only. But, if need be, one could easily create an entity
for the main table and designate it as its own child, thus allowing for single table management (complete CRUD)
of the main table. This being said, this would be a limit use case, since accessing the main table through the
standard Moodle DBAL might be a better choice in this case. Again, the full potential of the ORM becomes
clear when managing complex relationships between elements of a Moodle form, for example.

.. note:: The ORM manages 1 -> 1 subordination in its version 1.0.0, and 1 -> N parent to child relationships will be added eventually.

The third parameter is the main table name. This is the read-only reference table that is usually the anchor for
a data structure. For example, usually, a course module, or a particular activity, is the anchor point of any
form element or data structure in Moodle.

The fourth parameter is the primary key (``id`` field) that should be fetched from the main table.

The fifth parameter is optional, and makes it possible to determine the nomenclature of the index that is to be
considered as a foreign key to the parent's id for the first level child (an entity with no direct parent FQCN).
This is particularly useful when Moodle core tables do not use any particular pattern in their index nomenclature.
For second level children and beyond, the nomenclature must be the parent table's name element that follows the
last underscore, followed by the expression 'id', without any spaces, underscores, or hyphens. For example,
if the parent table's name is 'simulation_circuit', then the name of child's foreign key to the parent id would be
'circuitid'. This is also the default behaviour for first level children if nothing is specified for this parameter.

The sixth and final paramater is also optional and allows the user to enable constraints on all of the children's foreign keys. It is recommended
to avoid using constraints with Moodle tables, since it is not considered to be a standard way of handling tables
in the Moodle community for now. By default, this parameter is set to ``false``.
