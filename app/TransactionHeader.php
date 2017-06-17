<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TransactionHeader extends Model
{
    protected $table = 'transaction_headers';

    function cashier()
    {
        return $this->hasOne('App\User', 'id', 'cashier_user_id');
    }

    function cashier2()
    {
        return $this->hasOne('App\User', 'id', 'cashier2_user_id');
    }
}
