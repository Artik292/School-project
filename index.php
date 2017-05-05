<?PHP
require 'init.php';

$grid = $layout->add('CRUD');
$grid->setModel(new friends($db));

//echo 'Hello World';
