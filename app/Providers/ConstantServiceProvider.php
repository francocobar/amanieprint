<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ConstantServiceProvider extends ServiceProvider
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
    public function register()
    {
        //
    }

    const type_id_produk = 1;
    const type_id_jasa = 2;
    const type_id_sewa = 3;

    const status_pending = 1;
    const status_rejected = 2;
    const status_approved = 3;

    const daily_period = 1;
    const monthly_period = 2;
}
