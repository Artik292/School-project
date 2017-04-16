<?PHP
require 'vendor/autoload.php';


$app = new \atk4\ui\App('Task №1');
$app->initLayout('Centered');

use \atk4\ui\Button;
use \atk4\ui\Buttons;
use \atk4\ui\Header;
use \atk4\ui\Icon;
use \atk4\ui\Label;
use \atk4\ui\Template;
use \atk4\ui\View;

$app->layout->add('HelloWorld');

$app->add(new Header(['Combining Buttons', 'size'=>1]));
$button1 = new View(['ui'=>'buttons', null, 'vertical']); 
$button1->add(new Button(['Facebook','icon'=>'facebook']));
$button1->set(['size big'=>true]);
//$button1->set(['link'=>'https://www.facebook.com/groups/348235235572043/']);
$button1->add(new Button(['VK', 'icon'=>'vk']));
$button1->set(['size big'=>true]);
$button1->add(new Button(['Shuffle', 'icon'=>'shuffle']));
$button1->set(['size big'=>true]);
$app->add($button1);

$button = new Button();
$button->set('Click me');
$button->set(['primary' => true]);
$button->set(['icon'=>'cubes']);
$button->set(['size big'=>true]);

$app->add(new Header(['Task №1', 'size'=>2]));

//$button->on('click', function ($button) {
    //return 'success';
//}); 

    $a = [];
    $m_register = new \atk4\data\Model(new \atk4\data\Persistence_Array($a));
    $m_register->addField('name');
		
    $f = $app->add(new \atk4\ui\Form(['segment'=>TRUE]));
    $f->setModel($m_register);
	
	 $f->onSubmit(function ($f) {
        if ($f->model['name'] == '') {
            return $f->error('name', "This place can't be empty.");
        } else {
			$s = $f->model['name'];
			return "Hello $s ";
			//return new \atk4\ui\jsExpression('document.location = "task.php" ');

		}
    }); 
	
$button = $app->layout->add(['Button', 'GO TO TASK №2','iconRight'=>'external']);
$button->link(['task','key'=>'ok']);
