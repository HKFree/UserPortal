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

    function __construct(Model\Subnet $subnet, Model\SpravceOblasti $prava, Model\CestneClenstviUzivatele $cc, Model\TypSpravceOblasti $typSpravce, Model\TypPravniFormyUzivatele $typPravniFormyUzivatele, Model\TypClenstvi $typClenstvi, Model\TypCestnehoClenstvi $typCestnehoClenstvi, Model\ZpusobPripojeni $zpusobPripojeni, Model\TechnologiePripojeni $technologiePripojeni, Model\Uzivatel $uzivatel, Model\IPAdresa $ipAdresa, Model\AP $ap, Model\TypZarizeni $typZarizeni, Model\Log $log) {
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
        foreach($ipadrs as $ip)
        {
            $subnets = $this->subnet->getSubnetOfIP($ip);
            if(count($subnets) == 1) {
                $subnet = $subnets->fetch();
                if(empty($subnet->subnet)) {
                    $out[] = array('ip' => $ip, 'subnet' => 'subnet není v databázi', 'gateway' => 'subnet není v databázi', 'mask' => 'subnet není v databázi'); 
                } elseif( empty($subnet->gateway)) {
                    $out[] = array('ip' => $ip, 'subnet' => 'subnet není v databázi', 'gateway' => 'subnet není v databázi', 'mask' => 'subnet není v databázi'); 
                } else {
                    list($network, $cidr) = explode("/", $subnet->subnet);
                    $out[] = array('ip' => $ip, 'subnet' => $subnet->subnet, 'gateway' => $subnet->gateway, 'mask' => $this->subnet->CIDRToMask($cidr));  
                }
            } else {
                $out[] = array('ip' => $ip, 'subnet' => 'subnet není v databázi', 'gateway' => 'subnet není v databázi', 'mask' => 'subnet není v databázi'); 
            }
        }
        if(count($ipadrs) == 0)
        {
            $out[] = array('ip' => 'není přidána žádná ip', 'subnet' => 'subnet není v databázi', 'gateway' => 'subnet není v databázi', 'mask' => 'subnet není v databázi');                
        }
        $template->ips = $out;
        $pdf = new PDFResponse($template);
        $pdf->pageOrientaion = PDFResponse::ORIENTATION_PORTRAIT;
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
        
        $grid->setModel($seznamSpravcu);
        
    	$grid->setDefaultPerPage(100);
        $grid->setPerPageList(array(25, 50, 100, 250, 500, 1000));
    	$grid->setDefaultSort(array('zalozen' => 'ASC'));
        
        $presenter = $this;
    	$grid->addColumnText('id', 'UID')->setCustomRender(function($item) use ($presenter)
        {return Html::el('a')
            ->href($presenter->link('Uzivatel:show', array('id'=>$item->id)))
            ->title($item->id)
            ->setText($item->id);})->setSortable();
        $grid->addColumnText('nick', 'Nick')->setSortable();

        $grid->addColumnText('jmeno', 'Jméno a příjmení')->setCustomRender(function($item){                
                return $item->jmeno . ' '. $item->prijmeni;
            })->setSortable();
           
        $grid->addColumnEmail('email', 'E-mail')->setSortable();
        $grid->addColumnText('telefon', 'Telefon')->setSortable();

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
    		    $this->template->canViewOrEdit = $this->ap->canViewOrEditAP($uzivatel->Ap_id, $this->getUser());
    	    }
	        else
          {
            $this->template->canViewOrEdit = false;
          }
    }
    
   public function renderConfirm()
    {
        if($this->getParam('id'))
        {
            list($uid, $hash) = explode('-', base64_decode($this->getParam('id')));
            
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
    	$ips = $values->ip;
    	unset($values["ip"]);
    
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
        if (empty($values->poznamka)) {
                $values->poznamka = null;
            }
        
    	// Zpracujeme nejdriv uzivatele
    	if(empty($values->id)) {
            $values->regform_downloaded_password_sent = 0;
            $values->zalozen = new Nette\Utils\DateTime;
            $values->heslo = $this->uzivatel->generateStrongPassword();
            $values->id = $this->uzivatel->getNewID();
            $idUzivatele = $this->uzivatel->insert($values)->id;
            $this->log->logujInsert($values, 'Uzivatel', $log);
            
            $hash = base64_encode($values->id.'-'.md5($this->context->parameters["salt"].$values->zalozen));
            
            $so = $this->uzivatel->getUzivatel($this->getUser()->getIdentity()->getId());        
            $mail = new Message;
            $mail->setFrom($so->jmeno.' '.$so->prijmeni.' <'.$so->email.'>')
                ->addTo($values->email)
                ->setSubject('Žádost o potvrzení registrace člena hkfree.org z.s.')
                ->setHTMLBody('Dobrý den,<br><br>pro dokončení registrace člena hkfree.org z.s. je nutné kliknout na '
                        . 'následující odkaz:<br><br><a href="http://userdb.hkfree.org/userdb/uzivatel/confirm/'.$hash.'">http://userdb.hkfree.org/userdb/uzivatel/confirm/'.$hash.'</a><br><br>S pozdravem hkfree.org z.s.');
            $mailer = new SendmailMailer;
            $mailer->send($mail);

            $this->flashMessage('E-mail s žádostí o potvrzení registrace byl odeslán.');
            
        } else {
            $olduzivatel = $this->uzivatel->getUzivatel($idUzivatele);
    	    $this->uzivatel->update($idUzivatele, $values);
            $this->log->logujUpdate($olduzivatel, $values, 'Uzivatel', $log);
        }
        
    	// Potom zpracujeme IPcka
    	$newUserIPIDs = array();
    	foreach($ips as $ip)
    	{
    	    $ip->Uzivatel_id = $idUzivatele;
    	    $idIp = $ip->id;
            
            if (empty($ip->ip_adresa)) {
                $ip->ip_adresa = null;
            }
            if (empty($ip->hostname)) {
                $ip->hostname = null;
            }
            if (empty($ip->mac_adresa)) {
                $ip->mac_adresa = null;
            }
            if (empty($ip->popis)) {
                $ip->popis = null;
            }
            if (empty($ip->login)) {
                $ip->login = null;
            }
            if (empty($ip->heslo)) {
                $ip->heslo = null;
            }

            if(empty($ip->id)) {
                $idIp = $this->ipAdresa->insert($ip)->id;
                $this->log->logujInsert($ip, 'IPAdresa['.$idIp.']', $log);               
            } else {
                $oldip = $this->ipAdresa->getIPAdresa($idIp);
                $this->ipAdresa->update($idIp, $ip);
                $this->log->logujUpdate($oldip, $ip, 'IPAdresa['.$idIp.']', $log);
            }    
            $newUserIPIDs[] = intval($idIp);
    	}
    
    	// A tady smazeme v DB ty ipcka co jsme smazali
    	$userIPIDs = array_keys($this->uzivatel->getUzivatel($idUzivatele)->related('IPAdresa.Uzivatel_id')->fetchPairs('id', 'ip_adresa'));
    	$toDelete = array_values(array_diff($userIPIDs, $newUserIPIDs));
        if(!empty($toDelete)) {
            foreach($toDelete as $idIp) {
                $oldip = $this->ipAdresa->getIPAdresa($idIp);
                $this->log->logujDelete($oldip, 'IPAdresa['.$idIp.']', $log);
            }
        }
        $this->ipAdresa->deleteIPAdresy($toDelete);
    	
        $this->log->loguj('Uzivatel', $idUzivatele, $log);
        
    	$this->redirect('Uzivatel:show', array('id'=>$idUzivatele)); 
    	return true;
    }
    
    public function renderShow()
    {
   	    if($uzivatel = $this->uzivatel->getUzivatel($this->getUser()->getIdentity()->getId()))
        {
            $this->template->u = $uzivatel;

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
            $this->template->canViewOrEdit = $this->ap->canViewOrEditAP($uzivatel->Ap_id, $this->getUser());
            $this->template->hasCC = $this->cestneClenstviUzivatele->getHasCC($uzivatel->id);
            //$this->template->logy = $this->log->getLogyUzivatele($uid);
        }
    }

}
