<?php

namespace App\Presenters;

use Nette,
    App\Model,
    Nette\Application\UI\Form,
    Nette\Forms\Container,
    Nette\Utils\Html,
    Grido\Grid,
    Tracy\Debugger,
    Nette\Utils\Validators,
    Nette\Utils\Strings;
    
/**
 * Uzivatel presenter.
 */
class PublicPresenter extends BasePresenter
{  
    private $spravceOblasti;
    private $uzivatel;
    private $ap;

    function __construct(Model\SpravceOblasti $sprobl,  Model\Uzivatel $uzivatel, Model\AP $ap) {
    	$this->spravceOblasti = $sprobl;
    	$this->uzivatel = $uzivatel;
    	$this->ap = $ap;
    }

    public function renderSpravci()
    {
        
    }

    protected function createComponentGridSpravci($name)
    {
    	$grid = new \Grido\Grid($this, $name);
    	$grid->translator->setLang('cs');
        
        $id = $this->getParameter('id');
        $seznamSpravcu = $this->uzivatel->getSeznamSpravcuUzivatele($id);
        $apid = $this->uzivatel->getUzivatel($id)->Ap_id;
        $areaid = $this->ap->getAP($apid)->Oblast_id;
        
        $grid->setModel($seznamSpravcu);
        $grid->setFilterRenderType(\Grido\Components\Filters\Filter::RENDER_INNER);
        
    	$grid->setDefaultPerPage(100);
        $grid->setPerPageList(array(25, 50, 100, 250, 500, 1000));
    	$grid->setDefaultSort(array('rank' => 'ASC'));
        
        $thisspravceOblasti = $this->spravceOblasti;
        
        $grid->setRowCallback(function ($item, $tr) use ($thisspravceOblasti, $areaid){                
                $role = $thisspravceOblasti->getUserRole($item->id, $areaid);
                if($role == "SO")
                {
                  $tr->class[] = 'cestne';
                }          
                return $tr;
            });
        
        $grid->addColumnText('nick', 'Nick');
        
        
        $grid->addColumnText('id', 'Funkce')->setCustomRender(function($item) use ($thisspravceOblasti, $areaid){  
                $role = $thisspravceOblasti->getUserRole($item->id, $areaid);
                return $role == "SO" ? "Správce oblasti" : "Zástupce správce oblasti";
            });

        $grid->addColumnText('jmeno', 'Jméno a příjmení')->setCustomRender(function($item){                
                return $item->jmeno . ' '. $item->prijmeni;
            });
           
        $grid->addColumnEmail('email', 'E-mail');
        $grid->addColumnText('telefon', 'Telefon')->setCustomRender(function($item){ 
            if($item->publicPhone)
                return $item->telefon;
            else
                return "N/A";
            });

    }
    
}
