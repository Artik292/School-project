<?php

namespace atk4\ui;

class Lister extends View
{
    // @var Template
    protected $t_row = null;

    // @var Template
    protected $t_totals = null;

    // @inheritdoc
    public $defaultTemplate = null;

    /**
     * {@inheritdoc}
     */
    public function renderView()
    {
        if (!$this->template) {
            throw new Exception(['Lister requires you to specify template explicitly']);
        }
        $this->t_row = $this->template->cloneRegion('row');
        //$this->t_totals = isset($this->template['totals']) ? $this->template->cloneRegion('totals') : null;

        $this->template->del('rows');

        foreach ($this->model as $this->current_id => $this->current_row) {
            $rowHTML = $this->t_row->set($this->current_row)->render();
            $this->template->appendHTML('rows', $rowHTML);
        }

        return parent::renderView(); //$this->template->render();
    }
}
