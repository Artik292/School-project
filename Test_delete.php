<?PHP
require 'vendor/autoload.php';
include 'init.php';

$db = new
\atk4\data\Persistence_SQL('mysql:dbname=agile_education;host=localhost','root','');

class friends extends \atk4\data\Model {
	public $table = 'friends';
	
function init() {
	parent::init();
	$this->addField('Name');
	$this->addField('Surname');
	$this->addField('Phone_number');
}
}
$m = new friends($db);
//$m->loadBy('Surname','Test');
//$m->delete();

$m->addCondition('Surname','=','Test');
$m->action('delete')->execute(); 


$m = new friends($db);
$m->load(7);//$_GET['friend_id']
$model_money = $m->ref('money');



$grid = $layout->add('CRUD');
$grid->setModel(new friends($db));