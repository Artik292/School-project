
.. _grid:

====
Grid
====

.. php:namespace:: atk4\ui
.. php:class:: Grid

If you didn't read documentation on :ref:`table` you should start with that. While table implements the actual
data rendering, Grid component supplies various enhancements around it, such as paginator, quick-search, toolbar
and others by relying on other components.

Using Grid
==========

Here is a simple usage::

    $layout->add('Grid')->setModel(new Country($db));

To make your grid look nicer, you might want to add some buttons and enable quicksearch::

    $grid = $layout->add('Grid');
    $grid->setModel(new Country($db));

    $grid->addQuickSearch();
    $grid->menu->addItem('Reload Grid', new \atk4\ui\jsReload($grid));

Adding Menu Items
=================

.. php:attr: $menu

.. php:method: addButton($label)

Grid top-bar which contains QuickSearch is implemented using Semantic UI "ui menu". With that
you can add additional items and use all features of a regular :php:class:`Menu`::

    $sub = $grid->menu->addMenu('Drop-down');
    $sub->addItem('Test123');

For compatibility grid supports addition of the buttons to the menu, but there are several
Semantic UI limitations that wouldn't allow to format buttons nicely::

    $grid->addButton('Hello');

If you don't need menu, you can disable menu bar entirely::

    $grid = $layout->add(['Grid', 'menu' => false]);

Adding Quick Search
===================

.. php:attr: $quickSearch

.. php:method: addQuickSearch($fields = [])

After you have associated grid with a model using :php:class:`View::setModel()` you can
include quick-search component::

    $grid->addQuickSearch(['name', 'surname']);

If you don't specify argument, then search will be done by a models title field.
(http://agile-data.readthedocs.io/en/develop/model.html#title-field)

Paginator
=========

.. php:attr: $paginator

.. php:attr: $ipp

Grid comes with a paginator already. You can disable it by setting $paginator property to false. You can use $ipp
to specify different number of items per page::

    $grid->ipp = 10;

Actions
=======

.. php:attr: $actions

.. php:method: addAction($label, $action)

:php:class:`Table` supports use of :php:class:`TableColumn\Actions`, which allows to display button for each row.
Calling addAction() provides a useful short-cut for creating column-based actions.

Selection
=========

Grid can have a checkbox column for you to select elements. It relies on :php:class:`TableColumn\Checkbox`, but will
additionally place this column before any other column inside a grid. You can use :php:meth:`TableColumn\Checkbox::jsChecked()`
method to reference value of selected checkboxes inside any :ref:`js_action`::

    $sel = $grid->addSelection();
    $grid->menu->addItem('show selection')->on('click', new \atk4\ui\jsExpression(
        'alert("Selected: "+[])', [$sel->jsChecked()]
    ));

Advanced Usage
==============

.. php:attr: $table

You can use a different component instead of default :php:class:`Table` by injecting $table property.
