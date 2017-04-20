<?PHP
class money extends \atk4\data\Model {
	public $table = 'money';
	
function init() {
	parent::init();
	$this->addField('type',['enum'=>['+'=>'your friend lends','-'=>'you lend']]);
	$this->addField('amount',['type'=>'money']);
	$this->addField('date',['type'=>'date']);
	
	$this->hasOne('friend_id', new friends());
}
}