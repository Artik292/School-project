<?PHP
require 'vendor/autoload.php';

$app = new \atk4\ui\App('Registration');
$layout = $app->initLayout('Centered');

$db = new
\atk4\data\Persistence_SQL('mysql:dbname=lending;host=localhost','root','');