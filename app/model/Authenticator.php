<?php

namespace App\Model;

use Nette,
    Nette\DateTime,
    App\Model,
    Nette\Security,
    Nette\Utils\Strings,
    Tracy\Debugger;

/**
 * Users authenticator.
 */
class Authenticator extends Nette\Object implements Security\IAuthenticator
{
    protected $context;
    private $fakeUser;
    private $spravceOb;
    
    public function __construct($fakeUser, Nette\Database\Context $ctx)
    {
        $this->context = $ctx;
        $this->fakeUser = $fakeUser;
    }
            
    /**
     * Performs an authentication.
     * @return Nette\Security\Identity
     * @throws Nette\Security\AuthenticationException
     */
    public function authenticate(array $credentials)
    {
        list($userID, $password) = $credentials;
        if (!$userID) {
            throw new Nette\Security\AuthenticationException('User not found.');
        }
      
        if($this->fakeUser != false)            /// debuging identity
        {
            $userID = $this->fakeUser["userID"];
            $args = array('nick' => $this->fakeUser["userName"]);
        }
        else
        {
            if(isset($_SERVER['givenName']))
            {
                $args = array('nick' => $_SERVER['givenName']);
            }
            else
            {
                $args = array('nick' => 'Anonymous');
            }
        }
               
        $date = new DateTime();   
        $spravcepro = $this->context->table("SpravceOblasti")->where('Uzivatel_id', $userID)->fetchAll();
        $roles = array();
        foreach ($spravcepro as $key => $value) {
            if($value->od->getTimestamp() < $date->getTimestamp() && (!$value->do || $value->do->getTimestamp() > $date->getTimestamp()))
            {
                if($value->Oblast) {
                    $roles[] = $value->ref('TypSpravceOblasti', 'TypSpravceOblasti_id')->text."-".$value->Oblast;
                } else {
                    $roles[] = $value->ref('TypSpravceOblasti', 'TypSpravceOblasti_id')->text;
                }
            }
        }

        return new Nette\Security\Identity($userID, $roles, $args);
    }
    

}
