<?php

namespace Aacotroneo\Saml2\Events;

class Saml2LogoutEvent extends Saml2Event {

    public function __construct($idp)
    {
        parent::__construct($idp);
    }
}
