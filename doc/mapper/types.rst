Type Handlers
=============

Amiss doesn't care what type a value is by default. It will assign whatever the
database gives it on retrieval, and bind whatever you give it on query.

This may be fine for 98% of your interaction with the database (trust me - it
really will be), but then along come dates and throw a whopping big spanner in
the works.

How are you persisting dates? Probably as a YYYY-MM-DD formatted string, yeah?
Maybe as a unix timestamp? But when you're working on them in PHP, you really
want them to be a `DateTime <http://php.net/manual/en/book.datetime.php>`_ or
`DateTimeImmutable <http://php.net/manual/en/book.datetimeimmutable.php>`_
instance. No, really, you do.

``Amiss\Mapper\Base`` provides a facility for handling specific database types
arbirtrarily. Any class which inherits this class gains this support, and all
Amiss mappers do.

It's worth mentioning that type handlers should be used sparingly and only when
absolutely necessary. When overused, they can significantly slow down the
performance of the mapper as each time a column value with a type handler is
retrieved from the database. If you specify a type handler against all 20 fields
of your class and you retrieve 200 of them, you've just added 4000 function
calls to your retrieval operation. Congratulations, you have just introduced the
slowest part of your interaction with the database! By keeping the number of
type-handled fields to the absolute bare minimum necessary, you will arrive at
exactly the intersection of performance and pragmatism that Amiss is targeting.

A note on integers: PDO will return strings by default even when the underlying
database column is an integer when using ``FETCH_ASSOC`` (which Amiss does), but
does your application really care? PHP will treat a string as an integer if used
as one, and unless you're doing things that explicitly rely on ``===`` to work,
you probably don't need to convert these values, though you should be aware of
this behaviour.


Quickstart (annotation mapper)
------------------------------

Define an object with a field that has a ``type`` annotation:

.. code-block:: php

    <?php
    class Foo
    {
        /**:amiss = {"field": {"type": "mydate"}}; */
        public $bar;
    }


Assign a handler for the type:

.. code-block:: php

    <?php
    $mapper = new \Amiss\Mapper\Note();
    $mapper->addTypeHandler(new \Amiss\Type\Date(), 'mydate');


Now all fields with ``{"type": "mydate"}`` will pass through the
``\Amiss\Type\Date`` class on both retrieval and storage.


Using Type Handlers
-------------------

In order to register a handler with Amiss and allow it to be used, you need to
call ``Amiss\Mapper\Base::addTypeHandler($typeHandler, $id)``, or if registering
the same handler to many types, using
``Amiss\Mapper\Base::addTypeHandler($typeHandler, array($id1, $id2))``:

.. code-block:: php

    <?php
    // anything which derives from Amiss\Mapper\Base will work.
    $mapper = new Amiss\Mapper\Note;
    $dateHandler = new Amiss\Type\Date;
    $mapper->addTypeHandler($dateHandler, array('datetime', 'timestamp'));


Type handler IDs can be anything you like, with one important caveat: the ID
``autoinc`` is reserved for an autoincrement type. If you implement your own
autoincrement handler, feel free to use ``autoinc`` as your ID, but **Amiss**
does treat this one ID as special.  Everything else is yours to do with as you
see fit.

Type handler IDs are always lower case, even if the field type contains
uppercase letters.  The base mapper will also ignore everything in your field
type definitions following the first space or opening bracket.

.. code-block:: php

    <?php
    class Foo
    {
        /**:amiss = {"field": {"type": "BAZ(QUX)"}}; */
        public $bar;
    }
   
    // this will apply for field $bar
    $mapper->addTypeHandler($bazHandler, 'baz');


Included Handlers
-----------------

Amiss provides a set of type handlers for common use cases. These are set up by
default when creating a manager or mapper using the ``Amiss`` helper class.


Date
~~~~

``Amiss\Type\Date( array $options )``

    Converts database ``DATE`` or ``DATETIME`` into a PHP ``DateTime`` on object
    creation and PHP DateTime objects into a ``DATE`` or ``DATETIME`` on row
    export.

    ``$options`` should be an associative array with the following options:

    ``formats``
        Required. List of formats which can be used to parse the value coming
        from the DB, in order of priority. The first format is used when the
        value is saved to the DB.
        
        The `|` modifier (see
        http://php.net/manual/en/datetime.createfromformat.php) is appended when
        the date is parsed.

        You can also pass the strings ``date`` or ``datetime`` to get a
        pre-configured setup for those two column types. Both of these types are
        configured with *Amiss* if you use ``Amiss\Sql\Factory``.

    ``dbTimeZone``
        Required. String accepted by ``DateTimeZone`` constructor, or an
        instance of ``DateTimeZone``. Dates retrieved from the database are
        assumed to be in this time zone by default.

    ``appTimeZone``
        Optional. String accepted by ``DateTimeZone`` constructor, or an
        instance of ``DateTimeZone``. If this is set and different to
        ``dbTimeZone``, the ``DateTime`` object loaded from the database is
        converted to this timezone.

        Assuming the ``dbTimeZone`` is set to ``America/New_York`` and
        ``appTimeZone`` is set to ``Australia/Melbourne``, the following will
        happen to the value ``2015-01-01 12:00:00``:

        .. code-block:: php
               
            <?php
            $dt = \DateTime::createFromFormat(
                "Y-m-d H:i:s", 
                "2015-01-01 12:00:00",
                new \DateTimeZone("America/New_York")
            );
            $dt->setTimeZone(new \DateTimeZone("Australia/Melbourne"));
            echo $dt->format('c');
    
            // output: 2015-01-02T04:00:00+11:00

    ``classes``
        A list of classes which will be accepted by the type handler as a
        ``DateTime`` object. The first entry in this list is the class that will
        be returned when retrieving from the database. If you use your own
        derived version of PHP's ``DateTime`` or ``DateTimeImmutable``, you can
        tell Amiss to use it here.

        It comes set to ``[\DateTime::class, \DateTimeImmutable::class]`` by
        default, which means you can use either class when persisting, and a
        ``DateTime`` will be returned when retrieving.

        The first class in this array MUST have a static ``createFromFormat``
        method and all classes must inherit from ``DateTime`` or
        ``DateTimeImmutable``.

    ``forceTime``
        SQL's ``DATETIME`` type maps nicely to PHP's ``DateTime`` object. SQL's
        ``DATE`` type doesn't map quite so nicely - PHP's ``DateTime`` always
        carries around a time, even when one doesn't exist in the column. 

        This offers a possible solution. If you create a column of type
        ``date``, you can set ``forceTime`` to ``00:00:00`` and you'll get the
        times you expect. The default ``date`` type supplied by
        ``Amiss\Sql\Factory`` does this.

        You can also use this to create inclusive date ranges by setting
        ``forceTime`` to ``23:59:59``. Not so good for a leap second, but it's
        something.


Encoder
~~~~~~~

``Amiss\Type\Encoder( callable $serialiser, callable $deserialiser, $innerHandler=null )``

    Allows a value to be encoded/decoded using a pair of callables. This is
    useful if you want a specific type to be passed through PHP's
    ``serialize``/``unserialize`` function pair, or through
    ``json_encode``/``json_decode``, or your own custom translation.

    .. code-block:: php

        <?php
        class Foo
        {
            /**:amiss = {"field": {"type": "myEncodedType"}}; */
            public $nestage;
    
            /**:amiss = {"field": {"type": "mySuperMunge"}}; */
            public $munged;
        }
    
        $encoder = new \Amiss\Type\Encoder('serialize', 'unserialize');
        $mapper->addTypeHandler($encoder, 'myEncodedType');
    
        // or this terrible example demonstrating closures
        $encoder = new \Amiss\Type\Encoder(
            function($value) { return "--$value--"; },
            function($value) { return trim($value, "-"); }
        );
        $mapper->addTypeHandler($encoder, 'mySuperMunge');


    ``Amiss\Type\Encoder`` can also be passed a secondary handler that will be
    applied after the encoding/decoding process occurs. ``Amiss\Type\Encoder``
    instances can thus be chained, or used in conjunction with other handlers.


.. _embed:

Embed
~~~~~

``Amiss\Type\Embed( $mapper )``
    
    Allows one or many objects that are managed by Amiss to be stored as a
    nested value.

    This is useful when using Amiss with the Mongo extension, or when you are ok
    with storing a complex document as a serialised blob in a relational column
    (I am, sometimes).

    The ``Embed`` type requires the class name of the embedded object and,
    optionally, a flag indicating whether whether an array of objects is to be
    embedded instead of a single instance.
    
    In the following example, we add a type handler for a type called "nest" and
    specify one field that embeds a single instance of an ``ArtistType`` object,
    and another that embeds a collection of ``ArtistType`` objects:

    .. code-block:: php

        <?php
        class Artist
        {
            /** :amiss = {"field": {"type": {"id": "embed", "class": "ArtistType"}}}; */
            public $artistType;
    
            /**
             * :amiss = {"field": {
             *     "type": {"id": "embed", "class": "Member", "many": true}
             * }};
             */
            public $members;
        }
    
        $embed = new \Amiss\Type\Embed($mapper);
        $mapper->addTypeHandler($embed, 'embed');


    When using Amiss with MySQL or SQLite, serialisation must be used in
    conjunction with the ``Amiss\Type\Encoder`` type as these data stores can
    not handle storing or retrieving objects directly.

    .. code-block:: php

        <?php
        $embed = new \Amiss\Type\Embed($mapper);
        $encoder = new \Amiss\Type\Encoder('serialize', 'unserialize', $embed);
        $mapper->addTypeHandler($encoder, 'nest');


Custom Type Handlers
--------------------

To create your own type handler, you need to implement the
``Amiss\Type\Handler`` interface. This interface requires three methods:

``prepareValueForDb( $value , $object , array $fieldInfo )``
    
    Take an object value and prepare it for insertion into the database
    

``handleValueFromDb( $value , array $fieldInfo, array $row )``
    
    Takes a value coming out of the database and prepare it for assigning to an
    object.


``createColumnType( $engine , array $fieldInfo )``

    This generates the database type string for use in table creation. See
    :doc:`/schema` for more info. You can simply leave this method empty if you
    prefer and the type declared against the field will used instead if it is
    set.

    This method makes the database engine name available so you can return a
    different type depending on whether you're using MySQL or SQLite.


The following (naive) handler demonstrates serialising/deserialising an object
into a single column (though in practice you would use the provided
``Amiss\Type\Encoder`` handler for this task):

.. code-block:: php

    <?php
    class SerialiseHandler implements \Amiss\Type\Handler
    {
        function prepareValueForDb($value) { return serialize($value); }
        function handleValueFromDb($value) { return unserialize($value); }
        function createColumnType($engine, $fieldInfo) { return "LONGTEXT"; }
    }


To make use of your new handler, declare an object with fields that map to your
handler's ID and register the handler with your mapper:

.. code-block:: php

    <?php
    class Foo
    {
        /** :amiss = {"field": {"primary": true}}; */
        public $fooId;
   
        /** :amiss = {"field": {"type": "serialise"}}; */
        public $bar;
   
        /** :amiss = {"field": {"type": "serialise"}}; */
        public $baz;
    }
   
    // anything which derives from Amiss\Mapper\Base will work.
    $mapper = new Amiss\Mapper\Note;
    $mapper->addTypeHandler(new SerialiseHandler(), 'serialise');


Now, when you assign values to those properties, this class will handle the
translation between the code and the database:

.. code-block:: php

    <?php
    $f = new Foo();
    $f->bar = (object)array('yep'=>'wahey!');
    $manager->save($f);


The value of ``bar`` in the database will be::

    O:8:"stdClass":1:{s:3:"yep";s:5:"wahey";}

And when we retrieve the object again (assuming a primary key of ``1``), ``bar``
will contain a nicely unserialised ``stdClass`` instance, just like we started
with:

.. code-block:: php

    <?php
    $f = $manager->getById('Foo', 1);
    var_dump($f->bar);
    

In the situation where you want to handle a specific database type (like
``DATETIME`` or ``VARCHAR``), you can provide a handler for it and simply leave
the ``createColumnType`` method body empty.

To determine the id for the handler to use, it takes everything up to the first
space or opening parenthesis. In the following example, the type handler
``varchar`` will be used for column ``bar``:

.. code-block:: php

    <?php
    class Foo
    {
        /** :amiss = {"field": {"type": "VARCHAR(48)"}}; */
        public $bar;
    }
    $mapper->addTypeHandler(new BlahBlahHandler, 'varchar');

.. note:: Handler ids are case insensitive.

