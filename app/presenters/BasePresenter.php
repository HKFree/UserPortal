<?php

namespace App\Presenters;

use Nette,
	App\Model,
    Nette\Application\UI\Form,
    Tracy\Debugger;


/**
 * Base presenter for all application presenters.
 */
abstract class BasePresenter extends Nette\Application\UI\Presenter
{

    /** @persistent */
    public $id;

    public function injectOblast()
    {
    }
    
    public function startup() {
		parent::startup();

		//$uri = $this->getHttpRequest()->getUrl();

		if($this->context->parameters["debug"]["fakeUser"] == false && isset($_SERVER['PHP_AUTH_USER']) && $_SERVER['PHP_AUTH_USER']!='')
		{
			$this->getUser()->login($_SERVER['PHP_AUTH_USER'], NULL);
		}
		else
		{ 
			$this->getUser()->login("DBG", NULL);
		}
    }
    
    protected function beforeRender() {
        parent::__construct();
        parent::beforeRender();        
    }

}
