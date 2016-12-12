<?php

namespace App\Presenters;

use Nette,
    App\Model,
    Nette\Application\UI\Form,
    Nette\Forms\Container,
    Nette\Utils\Html,
    Grido\Grid,
    Tracy\Debugger,
    Nette\Mail\Message,
    Nette\Utils\Validators,
    Nette\Mail\SendmailMailer,
    Nette\Utils\Strings,
    PdfResponse\PdfResponse;
    
use Nette\Forms\Controls\SubmitButton;
/**
 * Uzivatel presenter.
 */
class UzivatelPresenter extends BasePresenter
{  
    private $spravceOblasti; 
    private $cestneClenstviUzivatele;  
    private $typClenstvi;
    private $typCestnehoClenstvi;
    private $typPravniFormyUzivatele;
    private $typSpravceOblasti;
    private $zpusobPripojeni;
    private $technologiePripojeni;
    private $uzivatel;
    private $ipAdresa;
    private $ap;
    private $typZarizeni;
    private $log;
    private $subnet;
    private $uzivatelskeKonto;
    private $prichoziPlatba;
    private $parameters;
    private $accountActivation;

    function __construct(Model\Parameters $parameters, Model\AccountActivation $accActivation, Model\PrichoziPlatba $platba, Model\UzivatelskeKonto $konto, Model\Subnet $subnet, Model\SpravceOblasti $prava, Model\CestneClenstviUzivatele $cc, Model\TypSpravceOblasti $typSpravce, Model\TypPravniFormyUzivatele $typPravniFormyUzivatele, Model\TypClenstvi $typClenstvi, Model\TypCestnehoClenstvi $typCestnehoClenstvi, Model\ZpusobPripojeni $zpusobPripojeni, Model\TechnologiePripojeni $technologiePripojeni, Model\Uzivatel $uzivatel, Model\IPAdresa $ipAdresa, Model\AP $ap, Model\TypZarizeni $typZarizeni, Model\Log $log) {
    	$this->spravceOblasti = $prava;
        $this->cestneClenstviUzivatele = $cc;
        $this->typSpravceOblasti = $typSpravce;
        $this->typClenstvi = $typClenstvi;
        $this->typCestnehoClenstvi = $typCestnehoClenstvi;
        $this->typPravniFormyUzivatele = $typPravniFormyUzivatele;
    	$this->zpusobPripojeni = $zpusobPripojeni;
        $this->technologiePripojeni = $technologiePripojeni;
    	$this->uzivatel = $uzivatel;
    	$this->ipAdresa = $ipAdresa;  
    	$this->ap = $ap;
    	$this->typZarizeni = $typZarizeni;
        $this->log = $log;
        $this->subnet = $subnet;
        $this->uzivatelskeKonto = $konto; 
        $this->prichoziPlatba = $platba; 
        $this->parameters = $parameters;
        $this->accountActivation = $accActivation;
    }
    
    public function actionMoneyActivate() {
        $id = $this->getUser()->getIdentity()->getId();
        if($id)
        {
            if($this->accountActivation->activateAccount($this->getUser(), $id))
            {
                $this->flashMessage('Účet byl aktivován.');
            }
            
            $this->redirect('Uzivatel:show', array('id'=>$id));            
        }
    }
    
    public function actionMoneyReactivate() {
        $id = $this->getUser()->getIdentity()->getId();
        if($id)
        {
            $result = $this->accountActivation->reactivateAccount($this->getUser(), $id);
            if($result != '')
            {
                $this->flashMessage($result);
            }
            
            $this->redirect('Uzivatel:show', array('id'=>$id));  
        }
    }
    
    public function actionMoneyDeactivate() {
        $id = $this->getUser()->getIdentity()->getId();
        if($id)
        {
            if($this->accountActivation->deactivateAccount($this->getUser(), $id))
            {
                $this->flashMessage('Účet byl deaktivován.'); 
            }
            
            $this->redirect('Uzivatel:show', array('id'=>$id));  
        }
    }
    
    public function generatePdf($uzivatel)
    {
        $template = $this->createTemplate()->setFile(__DIR__."/../templates/Uzivatel/pdf-form.latte");
        $template->oblast = $uzivatel->Ap->Oblast->jmeno;
        $oblastid = $uzivatel->Ap->Oblast->id; 
        $template->oblastemail = "oblast$oblastid@hkfree.org";
        $template->jmeno = $uzivatel->jmeno;
        $template->prijmeni = $uzivatel->prijmeni;
        $template->forma = $uzivatel->ref('TypPravniFormyUzivatele', 'TypPravniFormyUzivatele_id')->text;
        $template->firma = $uzivatel->firma_nazev;
        $template->ico = $uzivatel->firma_ico;
        $template->nick = $uzivatel->nick;
        $template->uid = $uzivatel->id;
        $template->heslo = $uzivatel->regform_downloaded_password_sent==0 ? $uzivatel->heslo : "-- nelze zpětně zjistit --";
        $template->email = $uzivatel->email;
        $template->telefon = $uzivatel->telefon;
        $template->ulice = $uzivatel->ulice_cp;
        $template->mesto = $uzivatel->mesto;
        $template->psc = $uzivatel->psc;
        $template->clenstvi = $uzivatel->TypClenstvi->text;
        $template->nthmesic = $uzivatel->ZpusobPripojeni_id==2 ? "třetího" : "prvního";
        $template->nthmesicname = $uzivatel->ZpusobPripojeni_id==2 ? $this->uzivatel->mesicName($uzivatel->zalozen,3) : $this->uzivatel->mesicName($uzivatel->zalozen,1);
        $template->nthmesicdate = $uzivatel->ZpusobPripojeni_id==2 ? $this->uzivatel->mesicDate($uzivatel->zalozen,2) : $this->uzivatel->mesicDate($uzivatel->zalozen,0);
        $ipadrs = $uzivatel->related('IPAdresa.Uzivatel_id')->fetchPairs('id', 'ip_adresa');
        foreach($ipadrs as $ip) {
            $subnet = $this->subnet->getSubnetOfIP($ip);
            
            if(isset($subnet["error"])) {
                $errorText = 'subnet není v databázi';
                $out[] = array('ip' => $ip, 'subnet' => $errorText, 'gateway' => $errorText, 'mask' => $errorText); 
            } else {
                $out[] = array('ip' => $ip, 'subnet' => $subnet["subnet"], 'gateway' => $subnet["gateway"], 'mask' => $subnet["mask"]);
            }
        }
        
        if(count($ipadrs) == 0) {
            $out[] = array('ip' => 'není přidána žádná ip', 'subnet' => 'subnet není v databázi', 'gateway' => 'subnet není v databázi', 'mask' => 'subnet není v databázi');                
        }
        $template->ips = $out;
        
        $pdf = new PDFResponse($template);
        $pdf->pageOrientation = PDFResponse::ORIENTATION_PORTRAIT;
        $pdf->pageFormat = "A4";
        $pdf->pageMargins = "5,5,5,5,20,60";
        $pdf->documentTitle = "hkfree-registrace-".$this->getParam('id');
        $pdf->documentAuthor = "hkfree.org z.s.";

        return $pdf;
    }
    
    public function mailPdf($pdf, $uzivatel)
    {
        $seznamSpravcu = $this->uzivatel->getSeznamSpravcuUzivatele($uzivatel->id);

        $mail = new Message;
        $mail->setFrom('aktivace@hkfree.org')
            ->addTo($uzivatel->email)
            ->setSubject('Registrační formulář člena hkfree.org z.s.')
            ->setBody('Dobrý den, zasíláme Vám registrační formulář. S pozdravem hkfree.org z.s.');
        
        //\Tracy\Dumper::dump($seznamSpravcu);
        //exit();
        foreach ($seznamSpravcu as $so) {
            $mail->addTo($so->email);
        }

        $temp_file = tempnam(sys_get_temp_dir(), 'registrace');                
        $pdf->outputName = $temp_file;
        $pdf->outputDestination = PdfResponse::OUTPUT_FILE;
        $pdf->send($this->getHttpRequest(), $this->getHttpResponse());
        $mail->addAttachment('hkfree-registrace-'.$uzivatel->id.'.pdf', file_get_contents($temp_file));

        $mailer = new SendmailMailer;
        $mailer->send($mail);

        if($uzivatel->regform_downloaded_password_sent==0)
        {
            $this->uzivatel->update($uzivatel->id, array('regform_downloaded_password_sent'=>1));
        }
    }

     protected function createComponentGridSpravci($name)
    {
        //\Tracy\Dumper::dump($search);
        
    	$grid = new \Grido\Grid($this, $name);
    	$grid->translator->setLang('cs');
        $grid->setExport('so_export');
        
        $seznamSpravcu = $this->uzivatel->getSeznamSpravcuUzivatele($this->getUser()->getIdentity()->getId());
        $apid = $this->uzivatel->getUzivatel($this->getUser()->getIdentity()->getId())->Ap_id;
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
    
    public function actionExportAndSendRegForm() {
        if($this->getParam('id'))
        {
            if($uzivatel = $this->uzivatel->getUzivatel($this->getParam('id')))
    	    {
                $pdf = $this->generatePdf($uzivatel);

                $this->mailPdf($pdf, $uzivatel);
                
                $this->flashMessage('E-mail byl odeslán.');

                $this->redirect('Uzivatel:show', array('id'=>$uzivatel->id));  	  
            }
        }
    }
        
    public function actionExportPdf() {
      if($uzivatel = $this->uzivatel->getUzivatel($this->getUser()->getIdentity()->getId()))
    	    {
                $pdf = $this->generatePdf($uzivatel);
                $this->sendResponse($pdf);    	  
            }
    }
    
    public function renderSpravci()
    {
        
    }

    public function renderEdit()
    {
        if($this->getParam('id') == $this->getUser()->getIdentity()->getId() && $uzivatel = $this->uzivatel->getUzivatel($this->getUser()->getIdentity()->getId()))
    	    {
    		    $this->template->canViewOrEdit = $this->getUser()->getIdentity()->getId() == $uzivatel->id;
    	    }
	        else
          {
            $this->template->canViewOrEdit = false;
          }
    }
    
    public function renderChangepassword()
    {
        
    }
    
    public function renderChangeknownpassword()
    {
        if($this->getParam('id') == $this->getUser()->getIdentity()->getId() && $uzivatel = $this->uzivatel->getUzivatel($this->getUser()->getIdentity()->getId()))
    	    {
    		    $this->template->canViewOrEdit = $this->getUser()->getIdentity()->getId() == $uzivatel->id;
    	    }
	        else
          {
            $this->template->canViewOrEdit = false;
          }
    }
    
    protected function createComponentChngknownpwd() {
    
    	$form = new Form($this, 'chngknownpwd');
        $form->addHidden('id');
        $form->addPassword('hesloold', 'Staré heslo', 5)->setRequired('Zadejte staré heslo');
        $form->addPassword('heslonew', 'Nové heslo', 100)->setRequired('Zadejte nové heslo');
        $form->addPassword('heslonew2', 'Nové heslo znovu', 100)->setRequired('Znovu zadejte nové heslo');

    	$form->addSubmit('save', 'Uložit nové heslo')
    		->setAttribute('class', 'btn btn-success btn-xs btn-white');
    	$form->onSuccess[] = array($this, 'chngknownpwdFormSucceded');
        $form->onValidate[] = array($this, 'validateChngknownpwdForm');
    
    	// pokud editujeme, nacteme existujici ipadresy
    	$submitujeSe = ($form->isAnchored() && $form->isSubmitted());
        if($this->getParam('id') && !$submitujeSe) {
    	    $values = $this->uzivatel->getUzivatel($this->getParam('id'));
    	    if($values) {
                $form->setValues($values);
    	    }
    	}                
    
    	return $form;
    }
    
    public function validateChngknownpwdForm($form)
    {
        $values = $form->getValues();

      
        if($uzivatel = $this->uzivatel->getUzivatel($values->id))
        {
            if($uzivatel->heslo != $values->hesloold)
            {
               $form->addError('Staré heslo není správné!'); 
            }
            
            if($values->heslonew != $values->heslonew2)
            {
               $form->addError('Nové heslo se neshoduje!'); 
            }
        }
        else
        {
            $form->addError('Člen s tímto ID nenalezen!');
        }
    }
    
    public function chngknownpwdFormSucceded($form, $values) {

        if(empty($values->id)) {
            $form->addError('ID člena nebylo vyplněno');            
        } else {

            if($uzivatel = $this->uzivatel->getUzivatel($values->id))
            {
                $this->uzivatel->update($uzivatel->id, array('heslo'=>$values->heslonew));
                
                $mail = new Message;
                $mail->setFrom('moje@hkfree.org')
                    ->addTo($uzivatel->email)
                    ->setSubject('Změna hesla člena prostřednictvím uživatelského portálu')
                    ->setHTMLBody('Na uživatelském portálu moje.hkfree.org bylo změněno heslo člena: '.$uzivatel->id.'. Heslo bude funkční do 5ti minut.');
                $mailer = new SendmailMailer;
                $mailer->send($mail);
                
                //zalogovat udalost
                $log = array();
                $log[] = array(
                        'sloupec'=>'Uzivatel.heslo',
                        'puvodni_hodnota'=>NULL,
                        'nova_hodnota'=>'uživatel si změnil heslo na vlastní',
                        'akce'=>'U'                      
                    );
                $this->log->loguj('Uzivatel', $uzivatel->id, $log);
                
                $this->flashMessage('Heslo bylo změněno.');
                
                //TODO: tohle bude spatne
                /*$so = $this->uzivatel->getUzivatel($this->getUser()->getIdentity()->getId());        
                $mail = new Message;
                $mail->setFrom($uzivatel->jmeno.' '.$uzivatel->prijmeni.' <'.$uzivatel->email.'>')
                    ->addTo($so->email)
                    ->setSubject('Změna hesla člena prostřednictvím uživatelského portálu')
                    ->setHTMLBody('Na uživatelském portálu moje.hkfree.org došlo ke změně hesla člena: '.$uzivatel->id);
                $mailer = new SendmailMailer;
                $mailer->send($mail);*/
            }
        }

    	return true;
    }
    
   public function renderConfirm()
    {
        if($this->getParam('id'))
        {
            list($uid, $hash) = explode('-', base64_decode($this->getParam('id')));
            
            //\Tracy\Dumper::dump($uid);
            if($uzivatel = $this->uzivatel->getUzivatel($uid))
    	    {
                if($uzivatel->regform_downloaded_password_sent==0 && $hash == md5($this->context->parameters["salt"].$uzivatel->zalozen))
                {
                    $pdf = $this->generatePdf($uzivatel);

                    $this->mailPdf($pdf, $uzivatel);
                }
    		    $this->template->stav = true;
    	    }
	        else
            {
              $this->template->stav = false;
            }
        }
        else {
            $this->template->stav = false;
        }
    }
    
    protected function createComponentChngpwd() {
    
    	$form = new Form($this, 'chngpwd');
        $form->addText('id', 'ID člena', 5)->setRequired('Zadejte ID člena');
        $form->addText('email', 'Email', 30)->setRequired('Zadejte email')->addRule(Form::EMAIL, 'Musíte zadat platný email');

    	$form->addSubmit('save', 'Zaslat nové heslo')
    		->setAttribute('class', 'btn btn-success btn-xs btn-white');
    	$form->onSuccess[] = array($this, 'chngpwdFormSucceded');
        $form->onValidate[] = array($this, 'validateChngpwdForm');
    
    	// pokud editujeme, nacteme existujici ipadresy
    	/*$submitujeSe = ($form->isAnchored() && $form->isSubmitted());
        if($this->getParam('id') && !$submitujeSe) {
    	    $values = $this->uzivatel->getUzivatel($this->getParam('id'));
    	    if($values) {
                $form->setValues($values);
    	    }
    	}    */            
    
    	return $form;
    }
    
    public function validateChngpwdForm($form)
    {
        $values = $form->getValues();

        $duplMail = $this->uzivatel->getExistingEmailArea($values->email, $values->id);
        
        if (!$duplMail) {
            $form->addError('Uživatel s tímto ID má jiný email!');
        }
    }
    
    public function chngpwdFormSucceded($form, $values) {

        if(empty($values->id)) {
            $form->addError('ID člena nebylo vyplněno');            
        } else {

            if($uzivatel = $this->uzivatel->getUzivatel($values->id))
            {
                $heslo = $this->uzivatel->generateStrongPassword();
                $this->uzivatel->update($uzivatel->id, array('heslo'=>$heslo));
                
                $mail = new Message;
                $mail->setFrom('moje@hkfree.org')
                    ->addTo($uzivatel->email)
                    ->setSubject('Změna hesla člena prostřednictvím uživatelského portálu')
                    ->setHTMLBody('Na uživatelském portálu moje.hkfree.org bylo vygenerováno nové heslo člena: '.$uzivatel->id.'. Heslo bude funkční do 5ti minut. Heslo: '.$heslo);
                $mailer = new SendmailMailer;
                $mailer->send($mail);
                
                //zalogovat udalost
                $log = array();
                $log[] = array(
                        'sloupec'=>'Uzivatel.heslo',
                        'puvodni_hodnota'=>NULL,
                        'nova_hodnota'=>'uživatel si změnil heslo',
                        'akce'=>'U'                      
                    );
                $this->log->logujAnonymous('Uzivatel', $uzivatel->id, $log);
                
                $this->flashMessage('E-mail s heslem byl odeslán.');
                
                //TODO: tohle bude spatne
                /*$so = $this->uzivatel->getUzivatel($this->getUser()->getIdentity()->getId());        
                $mail = new Message;
                $mail->setFrom($uzivatel->jmeno.' '.$uzivatel->prijmeni.' <'.$uzivatel->email.'>')
                    ->addTo($so->email)
                    ->setSubject('Změna hesla člena prostřednictvím uživatelského portálu')
                    ->setHTMLBody('Na uživatelském portálu moje.hkfree.org došlo ke změně hesla člena: '.$uzivatel->id);
                $mailer = new SendmailMailer;
                $mailer->send($mail);*/
            }
        }

    	return true;
    }

    protected function createComponentUzivatelForm() {
    	$typClenstvi = $this->typClenstvi->getTypyClenstvi()->fetchPairs('id','text');
        $typPravniFormy = $this->typPravniFormyUzivatele->getTypyPravniFormyUzivatele()->fetchPairs('id','text');
    	$zpusobPripojeni = $this->zpusobPripojeni->getZpusobyPripojeni()->fetchPairs('id','text');
        $technologiePripojeni = $this->technologiePripojeni->getTechnologiePripojeni()->fetchPairs('id','text');

    	//\Tracy\Dumper::dump($aps);
    
    	$form = new Form($this, 'uzivatelForm');
    	$form->addHidden('id');
    	$form->addSelect('TypPravniFormyUzivatele_id', 'Právní forma', $typPravniFormy)->addRule(Form::FILLED, 'Vyberte typ právní formy');
        $form->addText('firma_nazev', 'Název firmy', 30)->addConditionOn($form['TypPravniFormyUzivatele_id'], Form::EQUAL, 2)->setRequired('Zadejte název firmy');
        $form->addText('firma_ico', 'IČO', 8)->addConditionOn($form['TypPravniFormyUzivatele_id'], Form::EQUAL, 2)->setRequired('Zadejte IČ');
        //http://phpfashion.com/jak-overit-platne-ic-a-rodne-cislo
        $form->addText('jmeno', 'Jméno', 30)->setRequired('Zadejte jméno');
    	$form->addText('prijmeni', 'Přijmení', 30)->setRequired('Zadejte příjmení');
    	$form->addText('nick', 'Nick (přezdívka)', 30)->setRequired('Zadejte nickname');
    	$form->addText('email', 'Email', 30)->setRequired('Zadejte email')->addRule(Form::EMAIL, 'Musíte zadat platný email');
        $form->addText('email2', 'Sekundární email', 30)->addCondition(Form::FILLED)->addRule(Form::EMAIL, 'Musíte zadat platný email');
    	$form->addText('telefon', 'Telefon', 30)->setRequired('Zadejte telefon');
        $form->addText('cislo_clenske_karty', 'Číslo členské karty', 30);
    	$form->addText('ulice_cp', 'Adresa (ulice a čp)', 30)->setRequired('Zadejte ulici a čp');
        $form->addText('mesto', 'Adresa (město)', 30)->setRequired('Zadejte město');
        $form->addText('psc', 'Adresa (psč)', 5)->setRequired('Zadejte psč')->addRule(Form::INTEGER, 'PSČ musí být číslo');
    	$form->addText('rok_narozeni', 'Rok narození',30);	

    	$form->addSubmit('save', 'Uložit')
    		->setAttribute('class', 'btn btn-success btn-xs btn-white');
    	$form->onSuccess[] = array($this, 'uzivatelFormSucceded');
        $form->onValidate[] = array($this, 'validateUzivatelForm');
    
        $form->setDefaults(array(
            'TypClenstvi_id' => 3,
            'TypPravniFormyUzivatele_id' => 1,
        ));
    
    	// pokud editujeme, nacteme existujici ipadresy
    	$submitujeSe = ($form->isAnchored() && $form->isSubmitted());
        if($this->getParam('id') && !$submitujeSe) {
    	    $values = $this->uzivatel->getUzivatel($this->getParam('id'));
    	    if($values) {
                $form->setValues($values);
    	    }
    	}                
    
    	return $form;
    }
    
    public function validateUzivatelForm($form)
    {
        $values = $form->getValues();

        $duplMail = $this->uzivatel->getDuplicateEmailArea($values->email, $values->id);
        
        if ($duplMail) {
            $form->addError('Tento email již v DB existuje v oblasti: ' . $duplMail);
        }
        
        if(!empty($values->email2)) {
            $duplMail2 = $this->uzivatel->getDuplicateEmailArea($values->email2, $values->id);

            if ($duplMail2) {
                $form->addError('Tento email již v DB existuje v oblasti: ' . $duplMail2);
            }
        }
        
        /*$duplPhone = $this->uzivatel->getDuplicatePhoneArea($values->telefon, $values->id);
        
        if ($duplPhone) {
            $form->addError('Tento telefon již v DB existuje v oblasti: ' . $duplPhone);
        }*/
    }
    
    public function uzivatelFormSucceded($form, $values) {
        $log = array();
    	$idUzivatele = $values->id;
    
        if (empty($values->cislo_clenske_karty)) {
                $values->cislo_clenske_karty = null;
            }
        if (empty($values->firma_nazev)) {
                $values->firma_nazev = null;
            }
        if (empty($values->firma_ico)) {
                $values->firma_ico = null;
            }
        if (empty($values->email2)) {
                $values->email2 = null;
            }
        
    	// Zpracujeme nejdriv uzivatele
    	if(empty($values->id)) {
            $form->addError('Tato funkce zde není dostupná');            
        } else {
            $olduzivatel = $this->uzivatel->getUzivatel($idUzivatele);
    	    $this->uzivatel->update($idUzivatele, $values);
            $this->log->logujUpdate($olduzivatel, $values, 'Uzivatel', $log);
            
            //TODO: tohle bude spatne
            $so = $this->uzivatel->getUzivatel($this->getUser()->getIdentity()->getId());        
            $mail = new Message;
            $mail->setFrom($values->jmeno.' '.$values->prijmeni.' <'.$values->email.'>')
                ->addTo($so->email)
                ->setSubject('Změna údajů člena prostřednictvím uživatelského portálu')
                ->setHTMLBody('Na uživatelském portálu moje.hkfree.org došlo ke změně údajů člena: '.$idUzivatele);
            $mailer = new SendmailMailer;
            $mailer->send($mail);
        }
            	
        $this->log->loguj('Uzivatel', $idUzivatele, $log);
        
    	$this->redirect('Uzivatel:show', array('id'=>$idUzivatele)); 
    	return true;
    }
    
    public function renderShow()
    {
   	    if($uzivatel = $this->uzivatel->getUzivatel($this->getUser()->getIdentity()->getId()))
        {
            $this->template->u = $uzivatel;
            
            $this->template->money_act = ($uzivatel->money_aktivni == 1) ? "ANO" : "NE";
            $this->template->money_dis = ($uzivatel->money_deaktivace == 1) ? "ANO" : "NE";
            $posledniPlatba = $uzivatel->related('UzivatelskeKonto.Uzivatel_id')->where('TypPohybuNaUctu_id',1)->order('id DESC')->limit(1);
            if($posledniPlatba->count() > 0)
            {
                $posledniPlatbaData = $posledniPlatba->fetch();
                $this->template->money_lastpay = ($posledniPlatbaData->datum == null) ? "NIKDY" : ($posledniPlatbaData->datum->format('d.m.Y') . " (" . $posledniPlatbaData->castka . ")");
            }
            else
            {
                $this->template->money_lastpay = "?";
            }
            $posledniAktivace = $uzivatel->related('UzivatelskeKonto.Uzivatel_id')->where('TypPohybuNaUctu_id',array(4, 5))->order('id DESC')->limit(1);
            if($posledniAktivace->count() > 0)
            {
                $posledniAktivaceData = $posledniAktivace->fetch();
                $this->template->money_lastact = ($posledniAktivaceData->datum == null) ? "NIKDY" : ($posledniAktivaceData->datum->format('d.m.Y') . " (" . $posledniAktivaceData->castka . ")");
            }
            else
            {
                $this->template->money_lastact = "?";
            }
            $stavUctu = $uzivatel->related('UzivatelskeKonto.Uzivatel_id')->sum('castka');
            if($uzivatel->kauce_mobil > 0)
            {
                $this->template->money_bal = ($stavUctu - $uzivatel->kauce_mobil) . ' (kauce: ' . $uzivatel->kauce_mobil . ')';
            }
            else{
                $this->template->money_bal = $stavUctu;
            }

            $ipAdresy = $uzivatel->related('IPAdresa.Uzivatel_id');

            $this->template->adresy = $this->ipAdresa->getIPTable($ipAdresy);

            if($ipAdresy->count() > 0)
            {
                $this->template->adresyline = join(", ",array_values($ipAdresy->fetchPairs('id', 'ip_adresa')));
            }
            else
            {
                $this->template->adresyline = null;
            }
            $this->template->canViewOrEdit = $this->getUser()->getIdentity()->getId() == $uzivatel->id;
            $this->template->hasCC = $this->cestneClenstviUzivatele->getHasCC($uzivatel->id);
            //$this->template->logy = $this->log->getLogyUzivatele($uid);
            
            $this->template->activaceVisible = $uzivatel->money_aktivni == 0 && $uzivatel->money_deaktivace == 0 && ($stavUctu - $uzivatel->kauce_mobil) >= $this->parameters->getVyseClenskehoPrispevku();
            $this->template->reactivaceVisible = ($uzivatel->money_aktivni == 0 && $uzivatel->money_deaktivace == 1 && ($stavUctu - $uzivatel->kauce_mobil) >= $this->parameters->getVyseClenskehoPrispevku())
                                                    || ($uzivatel->money_aktivni == 1 && $uzivatel->money_deaktivace == 1);
            $this->template->deactivaceVisible = $uzivatel->money_aktivni == 1 && $uzivatel->money_deaktivace == 0;
        }
    }

    public function renderPlatba()
    {
        $id = $this->getParameter('id');
        $pohyb = $this->uzivatelskeKonto->findPohyb(array('PrichoziPlatba_id' => intval($id), 'Uzivatel_id NOT' => null));
        $this->template->canViewOrEdit = $this->ap->canViewOrEditAP($this->uzivatel->getUzivatel($pohyb->Uzivatel_id)->Ap_id, $this->getUser());
        $this->template->u = $pohyb->Uzivatel;
        $this->template->p = $this->prichoziPlatba->getPrichoziPlatba($this->getParam('id'));
    }
    
    public function renderAccount()
    {
        $this->template->canViewOrEdit = $this->uzivatel->getUzivatel($this->getParam('id'))->id == $this->getUser()->getIdentity()->getId();
        $this->template->u = $this->uzivatel->getUzivatel($this->getParam('id'));
    }
    
    protected function createComponentAccountgrid($name)
    {
        $canViewOrEdit = false;
    	$id = $this->getParameter('id');
        
        //\Tracy\Dumper::dump($search);

    	$grid = new \Grido\Grid($this, $name);
    	$grid->translator->setLang('cs');
        $grid->setExport('account_export');
        
        if($id){  
            $seznamTransakci = $this->uzivatelskeKonto->getUzivatelskeKontoUzivatele($id);

            $canViewOrEdit = $this->uzivatel->getUzivatel($this->getParam('id'))->id == $this->getUser()->getIdentity()->getId();
            
        } else {
            
            /*if($search)
            {
                $seznamTransakci = $this->uzivatel->findUserByFulltext($search,$this->getUser());
                $canViewOrEdit = $this->ap->canViewOrEditAll($this->getUser());
            }
            else
            {
                $seznamUzivatelu = $this->uzivatel->getSeznamUzivatelu();
                $canViewOrEdit = $this->ap->canViewOrEditAll($this->getUser());
            }
                        
            $grid->addColumnText('Ap_id', 'AP')->setCustomRender(function($item){
                  return $item->ref('Ap', 'Ap_id')->jmeno;
              })->setSortable();*/
        }
        
        $grid->setModel($seznamTransakci);
        
    	$grid->setDefaultPerPage(500);
        $grid->setPerPageList(array(25, 50, 100, 250, 500, 1000));
    	$grid->setDefaultSort(array('datum_cas' => 'DESC'));
        
        $presenter = $this;
        $grid->setRowCallback(function ($item, $tr) use ($presenter){  
                if($item->PrichoziPlatba_id)
                {
                    $tr->onclick = "window.location='".$presenter->link('Uzivatel:platba', array('id'=>$item->PrichoziPlatba_id))."'";
                }
                return $tr;
            });
                        
    	/*$grid->addColumnText('Uzivatel_id', 'UID')->setCustomRender(function($item) use ($presenter)
        {return Html::el('a')
            ->href($presenter->link('Uzivatel:show', array('id'=>$item->Uzivatel_id)))
            ->title($item->Uzivatel_id)
            ->setText($item->Uzivatel_id);})->setSortable();*/
            
        /*$grid->addColumnText('PrichoziPlatba_id', 'Příchozí platba')->setCustomRender(function($item) use ($presenter)
        {return Html::el('a')
            ->href($presenter->link('Uzivatel:platba', array('id'=>$item->PrichoziPlatba_id)))
            ->title($item->PrichoziPlatba_id)
            ->setText($item->PrichoziPlatba_id);})->setSortable();*/
            
        $grid->addColumnText('castka', 'Částka')->setSortable()->setFilterText();
        
        $grid->addColumnDate('datum_cas', 'Datum')->setDateFormat(\Grido\Components\Columns\Date::FORMAT_DATE)->setSortable()->setFilterText();
        
        $grid->addColumnText('TypPohybuNaUctu_id', 'Typ')->setCustomRender(function($item) {
            return Html::el('span')
                    ->alt($item->TypPohybuNaUctu_id)
                    ->setTitle($item->TypPohybuNaUctu->text)
                    ->setText($item->TypPohybuNaUctu->text)
                    ->data("toggle", "tooltip")
                    ->data("placement", "right");
            })->setSortable();
        
        $grid->addColumnText('poznamka', 'Poznámka')->setCustomRender(function($item){
                $el = Html::el('span');
                $el->title = $item->poznamka;
                $el->setText(Strings::truncate($item->poznamka, 100, $append='…'));
                return $el;
                })->setSortable()->setFilterText();
    }
}
