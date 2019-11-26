<?php

namespace App\Presenters;

use App\Model;

/**
 * Sprava presenter.
 */
class SpravaPresenter extends BasePresenter
{
    private $uzivatel;
    private $ap;

    private $googleMapsApiKey;

    function __construct(Model\Uzivatel $uzivatel, Model\AP $ap) {
    	$this->uzivatel = $uzivatel;
        $this->ap = $ap;
    }

    public function actionLogout() {
        $this->getUser()->logout();
        header("Location: https://moje.hkfree.org/Shibboleth.sso/Logout?return=https://idp.hkfree.org/idp/logout?return=http://www.hkfree.org");
        die();
    }

}
