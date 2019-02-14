<?php

namespace Aacotroneo\Saml2\Http\Controllers;

use Aacotroneo\Saml2\IdProvider;
use Aacotroneo\Saml2\Events\Saml2LoginEvent;
use Aacotroneo\Saml2\Saml2Auth;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use OneLogin\Saml2\Auth as OneLogin_Saml2_Auth;
use URL;


class Saml2Controller extends Controller
{

    /**
     * Create a {@code Saml2Auth} object from an IdProvider object.
     *
     * @param IdProvider $idp
     * @return Saml2Auth
     * @throws \OneLogin\Saml2\Error
     */
    protected function createSamlAuth(IdProvider $idp)
    {
        $config = config('saml2_settings');
        $config['idp'] = array();
        $config['idp']['entityId'] = $idp->entity_id;
        $config['idp']['singleSignOnService'] = array(
            'url' => $idp->sso_url,
        );
        $config['idp']['singleLogoutService'] = array(
            'url' => $idp->slo_url,
        );
        $config['idp']['certFingerprint'] = $idp->cert_fingerprint;

        if (empty($config['sp']['entityId'])) {
            $config['sp']['entityId'] = route('saml_metadata', $idp->company_slug);
        }
        if (empty($config['sp']['assertionConsumerService']['url'])) {
            $config['sp']['assertionConsumerService']['url'] = route('saml_acs', $idp->company_slug);
        }
        if (!empty($config['sp']['singleLogoutService']) &&
            empty($config['sp']['singleLogoutService']['url'])) {
            $config['sp']['singleLogoutService']['url'] = route('saml_sls', $idp->company_slug);
        }
        if (strpos($config['sp']['privateKey'], 'file://')===0) {
            $config['sp']['privateKey'] = $this->extractPkeyFromFile($config['sp']['privateKey']);
        }
        if (strpos($config['sp']['x509cert'], 'file://')===0) {
            $config['sp']['x509cert'] = $this->extractCertFromFile($config['sp']['x509cert']);
        }

        $auth = new OneLogin_Saml2_Auth($config);

        return new Saml2Auth($auth);
    }

    protected function extractPkeyFromFile($path) {
        $res = openssl_get_privatekey($path);
        if (empty($res)) {
            throw new \Exception('Could not read private key-file at path \'' . $path . '\'');
        }
        openssl_pkey_export($res, $pkey);
        openssl_pkey_free($res);
        return $this->extractOpensslString($pkey, 'PRIVATE KEY');
    }

    protected function extractCertFromFile($path) {
        $res = openssl_x509_read(file_get_contents($path));
        if (empty($res)) {
            throw new \Exception('Could not read X509 certificate-file at path \'' . $path . '\'');
        }
        openssl_x509_export($res, $cert);
        openssl_x509_free($res);
        return $this->extractOpensslString($cert, 'CERTIFICATE');
    }

    protected function extractOpensslString($keyString, $delimiter) {
        $keyString = str_replace(["\r", "\n"], "", $keyString);
        $regex = '/-{5}BEGIN(?:\s|\w)+' . $delimiter . '-{5}\s*(.+?)\s*-{5}END(?:\s|\w)+' . $delimiter . '-{5}/m';
        preg_match($regex, $keyString, $matches);
        return empty($matches[1]) ? '' : $matches[1];
    }

    /**
     * Generate local sp metadata
     *
     * @param $idp String Identity provider company slug.
     * @return \Illuminate\Http\Response
     * @throws \OneLogin\Saml2\Error
     */
    public function metadata($idp)
    {
        $saml2Auth = $this->createSamlAuth(IdProvider::findOrFail($idp));
        $metadata = $saml2Auth->getMetadata();

        return response($metadata, 200, ['Content-Type' => 'text/xml']);
    }

    /**
     * Process an incoming saml2 assertion request.
     * Fires 'Saml2LoginEvent' event if a valid user is Found
     *
     * @param $idp String Identity provider company slug.
     * @return \Illuminate\Http\Response Redirect to appropriate login route.
     * @throws \OneLogin\Saml2\Error
     */
    public function acs($idp)
    {
        $saml2Auth = $this->createSamlAuth(IdProvider::findOrFail($idp));
        $errors = $saml2Auth->acs();

        if (!empty($errors)) {
            logger()->error('Saml2 error_detail', ['error' => $saml2Auth->getLastErrorReason()]);
            session()->flash('saml2_error_detail', [$saml2Auth->getLastErrorReason()]);

            logger()->error('Saml2 error', $errors);
            session()->flash('saml2_error', $errors);
            return redirect(config('saml2_settings.errorRoute'));
        }
        $user = $saml2Auth->getSaml2User();

        event(new Saml2LoginEvent($idp, $user, $saml2Auth));

        $redirectUrl = $user->getIntendedUrl();

        if ($redirectUrl !== null) {
            return redirect($redirectUrl);
        } else {

            return redirect(config('saml2_settings.loginRoute'));
        }
    }

    /**
     * Process an incoming saml2 logout request.
     * Fires 'Saml2LogoutEvent' event if its valid.
     * This means the user logged out of the SSO infrastructure, you 'should' log him out locally too.
     *
     * @param $idp String Identity provider company slug.
     * @return \Illuminate\Http\Response Redirect to appropriate logout route.
     * @throws \OneLogin\Saml2\Error
     */
    public function sls($idp)
    {
        $saml2Auth = $this->createSamlAuth(IdProvider::findOrFail($idp));
        $error = $saml2Auth->sls($idp, config('saml2_settings.retrieveParametersFromServer'));

        if (!empty($error)) {
            throw new \Exception("Could not log out");
        }

        return redirect(config('saml2_settings.logoutRoute')); //may be set a configurable default
    }

    /**
     * This initiates a logout request across all the SSO infrastructure.
     *
     * @param $request Request
     * @param $idp String Identity provider company slug.
     * @throws \OneLogin\Saml2\Error
     */
    public function logout(Request $request, $idp)
    {
        $saml2Auth = $this->createSamlAuth(IdProvider::findOrFail($idp));
        $returnTo = $request->query('returnTo');
        $sessionIndex = $request->query('sessionIndex');
        $nameId = $request->query('nameId');
        $saml2Auth->logout($returnTo, $nameId, $sessionIndex); //will actually end up in the sls endpoint
        //does not return
    }


    /**
     * This initiates a login request
     *
     * @param Request $request
     * @param $idp String Identity provider company slug.
     * @param null $username String If set a username parameter will be sent to the login page for auto-fill.
     * @throws \OneLogin\Saml2\Error
     */
    public function login(Request $request, $idp, $username = null)
    {
        $saml2Auth = $this->createSamlAuth(IdProvider::findOrFail($idp));
        $params = array();

        if ($username) {
            $params['username'] = $username;
        }

        $saml2Auth->login(config('saml2_settings.loginRoute'), $params);
    }

}
