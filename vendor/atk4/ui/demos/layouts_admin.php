<?php
/**
 * Demonstrates how to use layouts.
 */
require '../vendor/autoload.php';

try {
    $app = new \atk4\ui\App('Agile Toolkit Demo App');

    $app->initLayout('Admin');

    $m_comp = $app->layout->menu->addMenu(['Layouts', 'icon'=>'puzzle']);
    $m_comp->addItem('Centered');
    $m_comp->addItem('Admin');

    $m_comp = $app->layout->menu->addMenu(['Component Demo', 'icon'=>'puzzle']);
    $m_form = $m_comp->addMenu('Forms');
    $m_form->addItem('Form Elements');
    $m_form->addItem('Form Layouts');
    $m_comp->addItem('CRUD');

    $app->layout->leftMenu->addItem(['Home', 'icon'=>'home']);
    $app->layout->leftMenu->addItem(['Topics', 'icon'=>'block layout']);
    $app->layout->leftMenu->addItem(['Friends', 'icon'=>'smile']);
    $app->layout->leftMenu->addItem(['History', 'icon'=>'calendar']);
    $app->layout->leftMenu->addItem(['Settings', 'icon'=>'cogs']);

    $app->layout->add(['Header', 'Basic Form Example']);

    $f = $app->layout->add(new \atk4\ui\Form(['segment']));
    $f->setModel(new \atk4\data\Model());

    $f_group = $f->addGroup('Name');
    $f_group->addField('first_name', ['width'=>'eight']);
    $f_group->addField('middle_name', ['width'=>'three']);
    $f_group->addField('last_name', ['width'=>'five']);

    $f_group = $f->addGroup('Address');
    $f_group->addField('address', ['width'=>'twelve']);
    $f_group->addField('zip', ['Post Code', 'width'=>'four']);

    $f->onSubmit(function ($f) {
        $errors = [];

        foreach (['first_name', 'last_name', 'address'] as $field) {
            if (!$f->model[$field]) {
                $errors[] = $f->error($field, 'Field '.$field.' is mandatory');
            }
        }

        return $errors ?: $f->success('No more errors', 'so we have saved everything into the database');
    });

    $app->run();
} catch (\atk4\core\Exception $e) {
    var_dump($e->getMessage());

    var_dump($e->getParams());
    throw $e;
}
