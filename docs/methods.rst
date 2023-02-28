.. _MethodsAnchor:

.. index:: ORM Methods

.. _methods:

ORM Methods
===========

MoodleORM has twelve (12) public methods that will help you to work with complex data.

The main public methods are::

    * export_data()
    * save() and,
    * commit()

.. index:: export_data Method

.. _export_data Method:

export_data() Method
--------------------

The ``export_data()`` method allows you to extract an array of the data that was read from the database,
into a format that is perfectly compatible with the ORM's ``save()`` method.

.. note:: The ``export_data()`` will only return data if the ORM's ``save()`` method was not previously invoked, and the ORM's registry is clean.

The format of the data is fairly straightforward. Each data stucture has a ``repositoryname`` index, which must
match the name of the table in the classmap array. The ``data`` index contains the fields as defined in the
table schema and in the corresponding properties of the Moodle entity. Finally, the array must either contain an
``entityid`` index, that holds the id of the existing entity, or an ``entityuuid`` index if the entity is to be
inserted into the database. In order to avoid collisions, it is recommended to use the
``bin2hex(random_bytes(20))`` PHP functions to generate the UUID. If a new entity has a newly created parent
entity, it will also be necessary to create a ``parentuuid`` index and insert the corresponding UUID at this
index of the array. Here is an example of a new entity that must be created:

.. code-block:: php

    $simulationid = $course->cmid;

    $uuid = bin2hex(random_bytes(20));

    $data[] = [
        'repositoryname' => 'simulation_wave',
        'entityuuid' => $uuid,
        'parentuuid' => null,
        'data' => [
            'name' => 'Wave 2',
            'description' => 'Second wave.',
            'simulationid' => $simulationid,
        ],
    ];

An example of the array's structure when extracting the data from the database would be something like
the following example:

.. code-block:: php

    $data[] = [
        'entityid' => 4,
        'repositoryname' => 'simulation_wave',
        'data' => [
            'name' => 'Wave 2',
            'description' => 'Second wave.',
            'simulationid' => 1,
        ],
    ];

Updating is a question of modifying elements of an entity in the array. Deleting is simply a question of removing
the appropriate element from the array.

.. index:: save Method

.. _save Method:

save() Method
-------------

Once the data array is set, it will be necessary to invoke the ``save()`` method, in order for the ORM to start
tracking changes in the entities. Its Unit of Work will start building a registry of "dirty" entities, that will
then be used to define the elements of the transaction that will be sent to the database when invoking its
``commit()`` method.

.. index:: commit Method

.. _commit Method:

commit() Method
---------------

The ``commit()`` method will clean the dirty registry of entities by running an SQL transaction, with all of the
required queries, in order to save the new data to the database.

.. note:: The ``commit()`` method will automatically be invoked by the ORM's destructor method if it falls out of scope of the current PHP script.

.. index:: Other ORM Methods

.. _Other ORM Methods:

Other ORM Methods
-----------------

The ORM comes with the following helper methods:

    * ``get_maintable_settings()``, which gives access to the main read-only table's data, based on the given ``id``,
    * ``get_classmap()``, which makes it possible to get the ORM's currently used classmap,
    * ``get_registry()``, which will return an array containing all of the data, including Moodle entities, that were initially read from the database,
    * ``get_dirty()``, which will return an array of all of the keys of the new data that require a CRUD action in order to save them to the database,
    * ``get_stage()``, which will return an array of all of the data, including Moodle entities, that were included in the commit (database transaction),
    * ``is_committed()``, which will return true if a database transaction was attempted, and false if no persistence action was taken, and
    * ``is_dirty()``, which will return true if the ``save()`` method was invoked with new data.

There are also two helper methods for adding or removing foreign key constraints on the foreign keys. These methods
are:

    * ``try_add_cascade_delete_check()``, and
    * ``try_remove_cascade_delete_check()``.
