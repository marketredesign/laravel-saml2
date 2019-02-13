<?php

namespace Aacotroneo\Saml2\Events;


class Saml2Event
{
    protected $idp;

    /**
     * Saml2Event constructor.
     * @param $idp
     */
    public function __construct($idp)
    {
        $this->idp = $idp;
    }

    public function getSaml2Idp()
    {
        return $this->idp;
    }

}