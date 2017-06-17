<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Mike42\Escpos\PrintConnectors\CupsPrintConnector;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\Printer;
use App\TransactionHeader;
use App\TransactionDetail;
use App\Branch;
use Carbon\Carbon;
use App\Member;
use DB;
use HelperService;
use Constant;

class ReportController extends Controller
{
    function printSalesReport($period, $spesific='0', $branch='0')
    {
        $header_sum =  $header_pelunasan_hutang = $headers = $details = null;
        $report = [];
        $report['branch'] = $branch;
        $report['branch_text'] = "SEMUA CABANG";
        if($report['branch'] != '0') {
            $report['obj_branch'] = Branch::find(intval($branch));
            if($report['obj_branch'] == null) abort(404);
            $report['branch_text'] = 'CABANG '.strtoupper($report['obj_branch']->branch_name.'['.$report['obj_branch']->prefix.']');
        }

        $selects = array(
            'sum(grand_total_item_price) AS sum_total_item_price',
            'SUM(total_paid) AS sum_total_paid',
            'sum(transaction_headers.change) AS sum_change',
            'sum(transaction_headers.debt) AS sum_debt',
            'sum(transaction_headers.total_item_discount) AS sum_item_discount',
            'sum(transaction_headers.discount_total_fixed_value) AS sum_discount_total',
            'sum(transaction_headers.others) AS sum_others',
        );

        $selects_pelunasan_hutang = array(
            'sum(total_paid2) AS sum_total_paid2',
            'sum(transaction_headers.change2) AS sum_change2',
        );
        $header_sum = $headers = $header_pelunasan_hutang = null;
        if($period == Constant::daily_period) {
            if($spesific=='0') {
                $spesific = Carbon::today()->toDateString();
                $spesifics = Carbon::today();
                $report['date_period'] = sprintf("%02d", $spesifics->day).'-'.sprintf("%02d", $spesifics->month).'-'.$spesifics->year;
            }
            else {
                $spesifics = explode('-', $spesific);
                // dd($spesifics);
                if(count($spesifics) != 3) {
                    abort(404);
                }
                else {
                    $report['date_period'] = $spesifics[2].'-'.$spesifics[1].'-'.$spesifics[0];
                }
                // dd($report);
            }
            $report['period'] = HelperService::inaDate($spesific);
            $header_sum =  TransactionHeader::whereDate('created_at', $spesific);
            $headers =  TransactionHeader::whereDate('created_at', $spesific);
            $header_pelunasan_hutang = TransactionHeader::whereDate('payment2_date', $spesific)
                        ->selectRaw(implode(',', $selects_pelunasan_hutang));
        }
        else if($period == Constant::monthly_period) {
            $report['year'] = date('Y');
            $report['month'] = date('m');
            if($spesific!='0') {
                $spesifics = explode('-', $spesific);
                if(count($spesifics) != 2) {
                    abort(404);
                }
                else {
                    $report['year'] = intval($spesifics[0]);
                    $report['month'] = intval($spesifics[1]);
                }
            }

            $report['period'] = HelperService::monthName($report['month']).' '.$report['year'];
            $header_sum =  TransactionHeader::whereMonth('created_at', '=', $report['month'])
                        ->whereYear('created_at', '=', $report['year']);
            $headers =  TransactionHeader::whereMonth('created_at', '=', $report['month'])
                        ->whereYear('created_at', '=', $report['year']);
            $header_pelunasan_hutang = TransactionHeader::whereMonth('created_at', '=', $report['month'])
                        ->whereYear('created_at', '=', $report['year']);
        }

        if($branch != '0') {
            $header_sum = $header_sum->where('branch_id', intval($branch));
            $headers = $headers->where('branch_id', intval($branch));
            $header_pelunasan_hutang = $header_pelunasan_hutang->where('branch_id', intval($branch));
        }

        $header_sum = $header_sum->selectRaw(implode(',', $selects))
                        ->get();
        $list_id_header = $headers->get(['id'])->pluck('id')->toArray();
        // dd($list_id_header);
        $headers = $headers->get();
        $header_pelunasan_hutang = $header_pelunasan_hutang->selectRaw(implode(',', $selects_pelunasan_hutang))->get();
        $selects_details = array(
            'item_id',
            'SUM(item_qty) AS item_qty_',
            'sum(item_total_price) AS item_total_price_',
        );
        // $details = TransactionDetail::with('itemInfo')->whereIn('header_id', $list_id_header)->get();

        $details = TransactionDetail::with('itemInfo')->whereIn('header_id', $list_id_header)->groupBy('item_id')
                ->selectRaw(implode(',', $selects_details))->get();

        $report['omset'] = $header_sum[0]['sum_total_paid']-$header_sum[0]['sum_change']
                            +$header_pelunasan_hutang[0]['sum_total_paid2']-$header_pelunasan_hutang[0]['sum_change2'];
        $report['potongan'] = $header_sum[0]['sum_item_discount']+$header_sum[0]['sum_discount_total'];
        $report['others'] = $header_sum[0]['sum_others'];
        $report['total_jual'] = $header_sum[0]['sum_total_item_price'] - $report['potongan'] + $header_sum[0]['sum_others'];
        $report['tunai'] = $header_sum[0]['sum_total_paid']-$header_sum[0]['sum_change'];
        $report['tunai_piutang'] = $header_pelunasan_hutang[0]['sum_total_paid2']-$header_pelunasan_hutang[0]['sum_change2'];
        $report['non_tunai'] = $header_sum[0]['sum_debt'] == null ? 0 :  $header_sum[0]['sum_debt'];
        // dd($details);

        // return "oke";
        $connector = new CupsPrintConnector("Epson-9-Pin");
        $printer = new Printer($connector);
        $printer -> setEmphasis(true);


        $printer -> setJustification(Printer::JUSTIFY_CENTER);

        // $img = EscposImage::load("tux.png");
        // $printer -> graphics($img);
        $printer -> selectPrintMode(Printer::MODE_EMPHASIZED);
        $printer -> text($report['branch_text']);
        $printer -> text("\n\n");
        $printer -> text("PENJUALAN ". $report['period']);
        $printer -> text("\n");
        $printer -> setJustification(Printer::JUSTIFY_LEFT);
        $printer -> selectPrintMode(Printer::MODE_FONT_B);

        $total_jual = str_pad('Total Jual', 21,' ');
        $printer -> text($total_jual);
        $total_jual_value = ': '. str_pad(number_format($report['total_jual'],0,",","."), 10,' ',STR_PAD_LEFT);
        $printer -> text($total_jual_value."\n");

        $tunai = str_pad('- Tunai', 21,' ');
        $printer -> text($tunai);
        $tunai_value = ': '. str_pad(number_format($report['tunai'],0,",","."), 10,' ',STR_PAD_LEFT);
        $printer -> text($tunai_value."\n");

        $non_tunai = str_pad('- Non Tunai (pituang)', 21,' ');
        $printer -> text($non_tunai);
        $non_tunai_value = ': '. str_pad(number_format($report['non_tunai'],0,",","."), 10,' ',STR_PAD_LEFT);
        $printer -> text($non_tunai_value."\n");

        $printer -> setJustification(Printer::JUSTIFY_CENTER);
        $printer -> text("\n***\n");
        $printer -> selectPrintMode(Printer::MODE_EMPHASIZED);
        $printer -> text("\nOMSET ". $report['period']."\n");
        $printer -> setJustification(Printer::JUSTIFY_LEFT);
        $printer -> selectPrintMode(Printer::MODE_FONT_B);

        $omset = str_pad('Omset', 10, ' ');
        $printer -> text($omset);
        $omset_value = ': '. str_pad(number_format($report['omset'],0,",","."), 21,' ',STR_PAD_LEFT);
        $printer -> text($omset_value."\n");

        $tunai = str_pad('- Penjualan Tunai', 18,' ');
        $printer -> text($tunai);
        $tunai_value = ': '. str_pad(number_format($report['tunai'],0,",","."), 13,' ',STR_PAD_LEFT);
        $printer -> text($tunai_value."\n");

        $pelunasan = str_pad('- Pelunasan Hutang', 18,' ');
        $printer -> text($pelunasan);
        $pelunasan_value = ': '. str_pad(number_format($report['tunai_piutang'],0,",","."), 13,' ',STR_PAD_LEFT);
        $printer -> text($pelunasan_value."\n");

        $printer -> setJustification(Printer::JUSTIFY_CENTER);
        $printer -> selectPrintMode(Printer::MODE_EMPHASIZED);
        $printer -> text("\n***\n");
        $printer -> text("\nRINCIAN PENJUALAN ". $report['period']."\n\n");
        $printer -> setJustification(Printer::JUSTIFY_LEFT);
        $printer -> selectPrintMode(Printer::MODE_FONT_B);

        $max_char_name = 20;
        foreach ($details as $detail) {
            $nama_item = $detail->itemInfo->item_name;
            $sisa_nama_item = '';
            if(strlen($nama_item) > $max_char_name) {
                $old_nama_item = $nama_item;
                $nama_item = substr($old_nama_item, 0, $max_char_name);
                $sisa_nama_item = substr($old_nama_item, $max_char_name, strlen($old_nama_item)-$max_char_name);
                // $sisa_nama_item =
            }
            $nama_item_fix = str_pad($nama_item,$max_char_name+4,' ');
            $item_total_price = number_format(intval($detail->item_total_price_),0,",",".");
            $text_print = $nama_item_fix.str_pad('x'.$detail->item_qty_,3,' ').str_pad($item_total_price,12,' ',STR_PAD_LEFT);
            $printer -> text($text_print."\n");
            if($sisa_nama_item!='') {
                $printer -> text($sisa_nama_item."\n");
            }
        }

        $grand_total_item_price =  HelperService::maskMoney($details->sum('item_total_price_'));
        $print = str_pad('TOTAL',19,' ',STR_PAD_LEFT).str_pad($grand_total_item_price.' [+]',20,' ',STR_PAD_LEFT);
        $printer -> text("\n".$print."\n");

        $grand_total_potongan =  HelperService::maskMoney($report['potongan']);
        $print = str_pad('POTONGAN',19,' ',STR_PAD_LEFT).str_pad($grand_total_potongan.' [-]',20,' ',STR_PAD_LEFT);
        $printer -> text($print."\n");

        $grand_others =  HelperService::maskMoney($report['others']);
        $print = str_pad('BIAYA LAIN-LAIN',19,' ',STR_PAD_LEFT).str_pad($grand_others.' [+]',20,' ',STR_PAD_LEFT);
        $printer -> text($print."\n");

        $netto = HelperService::maskMoney($report['total_jual']);
        $print = str_pad('NETTO',19,' ',STR_PAD_LEFT).str_pad($netto,20,' ',STR_PAD_LEFT);
        $printer -> text($print."\n");

        $printer -> setJustification(Printer::JUSTIFY_CENTER);
        $printer -> selectPrintMode(Printer::MODE_EMPHASIZED);
        $printer -> text("\n***\n");
        $printer -> text("\nRINCIAN INVOICE ". $report['period']."\n\n");
        $printer -> setJustification(Printer::JUSTIFY_LEFT);
        $printer -> selectPrintMode(Printer::MODE_FONT_B);

        $grand_total_headers = 0;
        foreach ($headers as $header) {
            $left_side = str_pad($header->invoice_id,24,' ');
            $total_jual_header = $header->grand_total_item_price -
                        $header->total_item_discount - $header->discount_total_fixed_value
                        + $header->others;
            $grand_total_headers += $total_jual_header;
            $total =  HelperService::maskMoney($total_jual_header);
            $print = $left_side.str_pad($total, 15,' ',STR_PAD_LEFT);
            $printer -> text($print."\n");
        }
        $left_side = str_pad('TOTAL',24,' ');
        $grand_total_headers = HelperService::maskMoney($grand_total_headers);
        $right_side = str_pad($grand_total_headers, 15,' ',STR_PAD_LEFT);
        $print = $left_side.$right_side;
        $printer -> text("\n".$print."\n");

        $printer -> cut();
        $printer -> pulse();
        $printer -> close();

        return redirect(env('URL_SERVER').'sales-report/'.$period.'/'.$spesific.'/'.$branch);
        return "printed";
    }
}
