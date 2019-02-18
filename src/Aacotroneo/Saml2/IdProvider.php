<?php
/**
 * Created by PhpStorm.
 * User: marijn
 * Date: 14-2-19
 * Time: 15:01
 */

namespace Aacotroneo\Saml2;

use Illuminate\Database\Eloquent\Model;

class IdProvider extends Model
{
    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'company_slug';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'domain',
        'entity_id',
        'sso_url',
        'slo_url',
        'cert_fingerprint',
    ];
}