<?php

namespace App\Presenters;

use Nette,
    App\Model,
    Grido\Grid,
    Nette\Utils\Html,
    Tracy\Debugger;

/**
 * Sprava odchozich plateb presenter.
 */
class SpravaPlatebPresenter extends SpravaPresenter
{
    private $odchoziPlatba;

    function __construct(Model\OdchoziPlatba $odchplatba) {
        $this->odchoziPlatba = $odchplatba;
    }
    

    public function renderOdchoziplatby()
    {

    }

    protected function createComponentOdchplatby($name)
    {
    	$grid = new \Grido\Grid($this, $name);
    	$grid->translator->setLang('cs');

        $grid->setModel($this->odchoziPlatba->getOdchoziPlatby());

    	$grid->setDefaultPerPage(10);
        $grid->setDefaultSort(array('datum' => 'DESC'));
        $grid->setPerPageList(array(10));

        $grid->setRowCallback(function ($item, $tr){

            if($item->datum_platby == null)
                {
                  $tr->class[] = 'primarni';
                }

                return $tr;
            });

    	$grid->addColumnDate('datum', 'Datum dokladu')->setDateFormat(\Grido\Components\Columns\Date::FORMAT_DATE);
        $grid->getColumn('datum')->headerPrototype->style['width'] = '10%';
        $grid->addColumnText('firma', 'Firma');
        $grid->addColumnText('popis', 'Popis');
        $grid->addColumnText('typ', 'Typ');
        $grid->getColumn('typ')->headerPrototype->style['width'] = '10%';
        $grid->addColumnText('kategorie', 'Kategorie');
        $grid->addColumnNumber('castka', 'Částka', 2, ',', ' ');
        $grid->getColumn('castka')->headerPrototype->style['width'] = '10%';
        $grid->addColumnDate('datum_platby', 'Datum platby')->setDateFormat(\Grido\Components\Columns\Date::FORMAT_DATE);
        $grid->getColumn('datum_platby')->headerPrototype->style['width'] = '10%';
    }
}