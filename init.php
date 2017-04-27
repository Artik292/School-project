<?PHP
require 'vendor/autoload.php';

$app = new \atk4\ui\App('Registration');
$app->initLayout('Admin');
$layout = $app->layout;

$db = new
\atk4\data\Persistence_SQL('mysql:dbname=lending;host=localhost','root','');

$layout->leftMenu->addItem(['VK','icon'=>'vk']);