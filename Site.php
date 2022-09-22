<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Eloquent;
use App\CompanySites;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Companies;

/**
 * @property boolean $site_id
 * @property boolean $system_id
 * @property string $site_title
 * @property string $site_short_title
 * @property string $site_url
 * @property int $vendor_id
 * @property string $date_created
 * @property string $alt_gateway
 * @property int $alt_id
 * @property string $alt_collection
 * @property string $google_email
 * @property string $do_google
 * @property int $nominal_code
 * @property int $department_code
 * @property string $lang
 * @property string $address1
 * @property string $address2
 * @property string $postcode
 * @property string $web
 * @property string $phone
 * @property string $email
 * @property string $prod_desc
 * @property string $footer
 * @property string $footer2
 * @property string $footer3
 * @property string $client_acc
 * @property string $deleted
 * @property string $fdr_merc_id
 * @property string $api_token
 */
class Site extends Model
{
    public $timestamps = false;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'site';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'site_id';

    // Client to Office website (C2O)
    const CTO = 117;
    const PARTNERS = 'Partners';

    // these are EL client account "sites"
    const CA_SITES = [105, 106, 107, 109, 110, 108];

    // partners sites
    const PARTNER_SITES = [111, 112, 113, 114, 115, 116];

    /**
     * @var array
     */
    protected $fillable = ['site_id', 'system_id', 'site_title', 'site_short_title', 'site_url', 'vendor_id', 'date_created', 'alt_gateway', 'alt_id', 'alt_collection', 'google_email', 'do_google', 'nominal_code', 'department_code', 'lang', 'address1', 'address2', 'postcode', 'web', 'phone', 'email', 'prod_desc', 'footer', 'footer2', 'footer3', 'client_acc', 'deleted', 'fdr_merc_id', 'api_token'];

    /**
     * Join transactions
     */
    /*
   public function transaction()
   {
       return $this->hasMany('App\Transaction', 'transID');
   }
    */

    public function companies()
    {
        return $this->belongsToMany("App\Companies", "company_sites", "site_id", "company_id");
    }


    public static function getSelect($company_id)
    {
        $allSites = [];
        $allSites['All'] = 'All';
        $allSites['Partners'] = 'FDR Partners';

        $companySiteArray = false;
        if (ctype_digit($company_id)) {
            $companySites = CompanySites::where('company_id', $company_id)->get();
            foreach ($companySites as $row) {
                $companySiteArray[] = $row->site_id;
            }
        }

        $data = Site::select('site_id', 'site_short_title', 'site_title')
            //->where('site_id', 'in', $companySiteArray)
            ->when($companySiteArray, function ($query, $companySiteArray) {
                return $query->whereIn('site_id', $companySiteArray);
            })
            ->where('deleted', '=', '0')
            ->orderBy("site_title", "ASC")
            ->get()->filter(function($site){
                if (Auth::user()->can('view', $site)) {
                    return $site;
                }
            });

        foreach ($data as $result) {
            $allSites[$result->site_id] = $result->site_title . ' (' . $result->site_short_title . ')';
        }
        return $allSites;
    }

    /**
     * Return a list of ELAW sites
     * @return \Illuminate\Support\Collection
     */
    public static function elawSites()
    {
        return DB::table("site")->where("site_short_title", "LIKE", "%ELAW%")
            ->pluck('site_short_title', 'site_id')->prepend("N/A", "");
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function logo()
    {
        return $this->hasOne(Logo::class, 'id', 'logo_id');
    }

}
