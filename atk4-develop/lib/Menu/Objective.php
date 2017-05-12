<?php
/**
 * Undocumented
 */
class Menu_Objective extends View
{
    public $current_menu_class = 'atk-state-current';

    // {{{ Inherited properties

    /** @var View */
    public $owner;

    /** @var App_Web */
    public $app;

    // }}}

    public function init()
    {
        parent::init();
        $this->setElement('ul');
    }

    public function isCurrent($href)
    {
        // returns true if item being added is current
        if (!is_object($href)) {
            $href = str_replace('/', '_', $href);
        }

        return $href == $this->app->page
            || $href == ';'.$this->app->page
            || $href.$this->app->getConfig('url_postfix', '') == $this->app->page
            || (string) $href == (string) $this->app->url();
    }

    public function addMenuItem($page, $label = null)
    {
        if (!$label) {
            $label = ucwords(str_replace('_', ' ', $page));
        }

        /** @type View $li */
        $li = $this->add('View');
        $li->setElement('li');
        /** @type View $a */
        $a = $li->add('View');
        $a->setElement('a')->set($label);

        if ($page instanceof jQuery_Chain) {
            $li->js('click', $page);

            return $li;
        }

        $a->setAttr('href', $this->app->url($page));

        if ($this->isCurrent($page) && $this->current_menu_class) {
            $li->addClass($this->current_menu_class);
        }

        return $li;
    }

    /**
     * @param string $name
     *
     * @return self
     */
    public function addSubMenu($name)
    {
        /** @type View $li */
        $li = $this->add('View');
        $li->setElement('li');

        /** @type Text $t */
        $t = $li->add('Text');
        $t->set($name);

        return $li
            ->add(get_class($this));
    }
}
