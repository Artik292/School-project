<?PHP

class friends extends \atk4\data\Model {
	public $table = 'friends';
	
function init() {
	parent::init();
	$this->addField('name', ['caption'=>'Friend Name']);
	$this->addField('surname');
	$this->addField('phone_number');
	$this->addField('email');
	$this->hasMany('money');
}
}