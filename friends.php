<?PHP

require 'init.php';

$c = new \atk4\ui\table();
$c->setModel(new friends($db));