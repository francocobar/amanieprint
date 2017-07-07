<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class NextPayment extends Model
{
    protected $guarded = ['id'];

    function cashier()
    {
        return $this->hasOne('App\User', 'id', 'cashier_user_id');
    }
}
