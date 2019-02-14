<?php

Route::group([
    'middleware' => config('saml2_settings.routesMiddleware'),
], function () {

    Route::get('/{idp}/logout', array(
        'as' => 'saml_logout',
        'uses' => 'Aacotroneo\Saml2\Http\Controllers\Saml2Controller@logout',
    ));

    Route::get('/{idp}/login/{username?}', array(
        'as' => 'saml_login',
        'uses' => 'Aacotroneo\Saml2\Http\Controllers\Saml2Controller@login',
    ));

    Route::get('/{idp}/metadata', array(
        'as' => 'saml_metadata',
        'uses' => 'Aacotroneo\Saml2\Http\Controllers\Saml2Controller@metadata',
    ));

    Route::post('/{idp}/acs', array(
        'as' => 'saml_acs',
        'uses' => 'Aacotroneo\Saml2\Http\Controllers\Saml2Controller@acs',
    ));

    Route::get('/{idp}/sls', array(
        'as' => 'saml_sls',
        'uses' => 'Aacotroneo\Saml2\Http\Controllers\Saml2Controller@sls',
    ));
});
