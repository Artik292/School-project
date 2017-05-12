<?php

namespace atk4\ui\TableColumn;

/**
 * Implements Checkbox column for selecting rows.
 */
class Checkbox extends Generic
{
    public $class = null;

    /**
     * Return action which will calculate and return array of all checkbox IDs, e.g.
     *
     * [3, 5, 20]
     */
    public function jsChecked()
    {
        return new \atk4\ui\jsExpression(' $('.$this->table->jsRender().").find('.checked.".$this->class."').closest('tr').map(function(){ ".
            "return $(this).data('id');}).get().join(',')");
    }

    public function init()
    {
        parent::init();
        if (!$this->class) {
            $this->class = 'cb_'.$this->short_name;
        }
    }

    public function getHeaderCellHTML(\atk4\data\Field $f = null)
    {
        if (isset($f)) {
            throw new Exception(['Checkbox must be placed in an empty column. Don\'t specify any field.', 'field'=>$f]);
        }
        $this->table->js(true)->find('.'.$this->class)->checkbox();

        return parent::getHeaderCellHTML($f);
    }

    public function getDataCellHTML(\atk4\data\Field $f = null)
    {
        return $this->getTag('td', 'body', ['div', 'class'=>'ui checkbox '.$this->class, ['input', 'type'=>'checkbox']]);
    }
}
