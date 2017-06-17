<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/print-sales-report/{period}/{spesific?}/{branch?}', 'ReportController@printSalesReport');
Route::get('/test2/test2', 'PrintController@printTest2');
Route::get('/{invoice_id}', 'PrintController@printInvoice');
Route::get('/', function(){
    return "oke2";
});
