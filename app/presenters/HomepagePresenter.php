<?php

namespace App\Presenters;

use Nette,
    App\Model;


/**
 * Homepage presenter.
 */
class HomepagePresenter extends BasePresenter
{
	/** @var Model\Uzivatel */
        private $uzivatel;

        function __construct(Model\Uzivatel $uzivatel) {
	    $this->uzivatel = $uzivatel;
        }

    
	public function renderDefault()
	{
	}
}
