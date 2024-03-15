<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestedAsset extends Model
{
    use HasFactory;
	
	protected $table = 'requested_assets';

    public function checkoutRequests()
    {
        return $this->belongsTo('app\Models\CheckoutRequest','checkout_requests_id','id');
    }


    public function responsible()
    {
        return $this->belongsTo(User::class, 'responsible_id', 'id');
    }


    public function requestingResponsible()
    {
        return $this->responsible()->first();
    }
}
