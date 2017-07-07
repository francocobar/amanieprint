<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use HelperService;
use Carbon\Carbon;

class HelperServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register the application services.
     *
     * @return void
     */

     static function createDateTimeObj($date_time_string)
     {
         return Carbon::createFromFormat('Y-m-d H:i:s', $date_time_string);
     }
     static function arrayMonth()
     {
        return array(
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'Nopember',
            12 => 'Desember'
        );
     }
     static function monthName($int_month)
     {
         $array_month = HelperService::arrayMonth();
         return $array_month[intval($int_month)];
        //  if(intval($int_month)==1) {
        //      return "Januari";
        //  }
        //  else if(intval($int_month)==2) {
        //      return "Februari";
        //  }
        //  else if(intval($int_month)==3) {
        //      return "Maret";
        //  }
        //  else if(intval($int_month)==4) {
        //      return "April";
        //  }
        //  else if(intval($int_month)==5) {
        //      return "Mei";
        //  }
        //  else if(intval($int_month)==6) {
        //      return "Juni";
        //  }
        //  else if(intval($int_month)==7) {
        //      return "Juli";
        //  }
        //  else if(intval($int_month)==8) {
        //      return "Agustus";
        //  }
        //  else if(intval($int_month)==9) {
        //      return "September";
        //  }
        //  else if(intval($int_month)==10) {
        //      return "Oktober";
        //  }
        //  else if(intval($int_month)==11) {
        //      return "Nopember";
        //  }
        //  else if(intval($int_month)==12) {
        //      return "Desember";
        //  }
     }
     static function maskMoney($money)
     {
         // return intval($money);
         return number_format(intval($money),0,",",".");
     }

     static function unmaskMoney($masked_money)
     {
         return str_replace('.', '', $masked_money);
     }

     static function inaDate($db_date, $type=1)
     {
         if($type==1) {//date only
             $exploded = explode('-', $db_date);

             return $exploded[2].' '.HelperService::monthName($exploded[1]).' '.$exploded[0];
         }

         //datetime
         return HelperService::inaDate($db_date->toDateString()).' '.$db_date->toTimeString();

     }
}
