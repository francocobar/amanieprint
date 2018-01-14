<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Mike42\Escpos\PrintConnectors\CupsPrintConnector;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\Printer;
use App\TransactionHeader;
use App\TransactionDetail;
use App\Branch;
use App\Member;
use App\NextPayment;
use HelperService;
use \Carbon\Carbon;

class PrintController extends Controller
{
    function printInvoice($invoice_id)
    {
        $invoice_id = str_replace('-','/', $invoice_id);
        $header = TransactionHeader::where('invoice_id', $invoice_id)->first();

        // dd($header->paymentStatus());
        $details = TransactionDetail::with(['itemInfo'])->where('header_id', $header->id)->get();
        $date_time = HelperService::inaDate($header->created_at,2);

        $connector = null;
        $connector = new WindowsPrintConnector(env('PRINTER_NAME'));
        $printer = new Printer($connector);
        $printer -> setEmphasis(true);
        $printer -> setUnderline(1);
        $printer -> setJustification(Printer::JUSTIFY_CENTER);

        // $img = EscposImage::load("tux.png");
        // $printer -> graphics($img);
        $printer -> selectPrintMode(Printer::MODE_EMPHASIZED);
        $printer -> text('RUMAH CANTIQUE AMANIE'."\n".'SALON & SPA MUSLIMAH'."\n");

        $printer -> selectPrintMode(Printer::MODE_FONT_B);
        $branch = Branch::find($header->branch_id);
        $printer -> setUnderline(0);
        $printer -> text('Cabang '.$branch->branch_name.': '.$branch->address."\n");
        $printer -> text($branch->phone."\n\n");
        //
        // $printer -> setEmphasis(false);

        $printer -> selectPrintMode(Printer::MODE_EMPHASIZED);
        $printer -> text($header->invoice_id.'  #'.$header->id."\n");
        $printer -> selectPrintMode(Printer::MODE_FONT_B);
        $printer -> setJustification(Printer::JUSTIFY_LEFT);
        $printer -> text('Waktu : '.$date_time."\n");
        $cashier = 'Kasir : #'.$header->cashier->id.' '.$header->cashier->first_name;
        $printer -> text($cashier."\n");

        $member = 'Member : ';
        if($header->member_id!=null) {
            $member .= Member::where('member_id',$header->member_id)->first()->full_name.' '.$header->member_id;
        }
        else {
            if($header->customer_name) {
                $member = 'Customer : '. $header->customer_name.' ('.$header->customer_phone.')';
            }
            else {
                $member .= '-';
            }
        }
        $printer -> text($member."\n\n");
        $max_char_name = 20;
        foreach ($details as $detail) {
            $nama_item =$detail->custom_name ? $detail->custom_name : $detail->itemInfo->item_name;
            $sisa_nama_item = '';
            if(strlen($nama_item) > $max_char_name) {
                $old_nama_item = $nama_item;
                $nama_item = substr($old_nama_item, 0, $max_char_name);
                $sisa_nama_item = substr($old_nama_item, $max_char_name, strlen($old_nama_item)-$max_char_name);
                // $sisa_nama_item =
            }
            $nama_item_fix = str_pad($nama_item,$max_char_name+4,' ');
            $item_total_price = number_format(intval($detail->item_total_price),0,",",".");
            $text_print = $nama_item_fix.str_pad('x'.$detail->item_qty,3,' ').str_pad($item_total_price,12,' ',STR_PAD_LEFT);
            $printer -> text($text_print."\n");
            if($sisa_nama_item!='') {
                $printer -> text($sisa_nama_item."\n");
            }
            $harga_satuan = '@'.number_format(intval($detail->item_price),0,",",".");;
            $printer -> text($harga_satuan."\n");
        }
        $grand_total_item_price = number_format(intval($header->grand_total_item_price),0,",",".");
        $total = str_pad('TOTAL',19,' ',STR_PAD_LEFT).str_pad($grand_total_item_price.' [+]',20,' ',STR_PAD_LEFT);
        $printer -> text("\n".$total."\n");

        $potongan_total = $header->total_item_discount+$header->discount_total_fixed_value;
        if($potongan_total>0) {
            $potongan_total_2 = number_format(intval($potongan_total),0,",",".");
            $potongan = str_pad('POTONGAN',19,' ',STR_PAD_LEFT).str_pad($potongan_total_2.' [-]',20,' ',STR_PAD_LEFT);
            $printer -> text($potongan."\n");
        }

        if($header->others>0) {
            $lain_lain = number_format(intval($header->others),0,",",".");
            $others = str_pad('LAIN-LAIN',19,' ',STR_PAD_LEFT).str_pad($lain_lain.' [+]',20,' ',STR_PAD_LEFT);
            $printer -> text($others."\n");
        }

        $total_akhir = $header->grand_total_item_price - $potongan_total + $header->others;

        if($total_akhir != $grand_total_item_price) {
            $total_akhir_2 = number_format(intval($total_akhir),0,",",".");
            $total_akhir_3 = str_pad('TOTAL AKHIR',19,' ',STR_PAD_LEFT).str_pad($total_akhir_2,20,' ',STR_PAD_LEFT);
            $printer -> text("\n".$total_akhir_3."\n");
        }

        if($header->debt > 0) {
            $dp = number_format(intval($header->paid_value),0,",",".");
            $dp_2 = str_pad('DP',19,' ',STR_PAD_LEFT).str_pad($dp,20,' ',STR_PAD_LEFT);
            $printer -> text("\n".$dp_2);
        }

        $payment_type = array(
            1 => 'Tunai',
            2 => 'Kredit',
            3 => 'Credit Card',
            4 => 'Debit Card'
        );
        $bayar = $payment_type[$header->payment_type].' BAYAR';

        $bayar_2 = number_format(intval($header->total_paid),0,",",".");
        $bayar = str_pad($bayar,19,' ',STR_PAD_LEFT).str_pad($bayar_2,20,' ',STR_PAD_LEFT);
        $printer -> text("\n".$bayar);
        if($header->payment_type == 1) {
            if($header->change > 0) {
                $change = number_format(intval($header->change),0,",",".");
                $change_2 = str_pad('KEMBALIAN',19,' ',STR_PAD_LEFT).str_pad($change,20,' ',STR_PAD_LEFT);
                $printer -> text("\n".$change_2);
            }
        }
        if($header->debt > 0) {
            $debt = number_format(intval($header->debt),0,",",".");
            $debt_2 = str_pad('BELUM BAYAR',19,' ',STR_PAD_LEFT).str_pad($debt,20,' ',STR_PAD_LEFT);
            $printer -> text("\n".$debt_2);
        }

        $next_payments = NextPayment::where('header_id',$header->id)->get();
        $no_payment=2;
        foreach($next_payments as $next_payment)
        {
            $printer -> selectPrintMode(Printer::MODE_EMPHASIZED);
            $print_text ="\nPEMBAYARAN KE-".$no_payment;
            $printer -> text("\n".$print_text);
            $printer -> selectPrintMode(Printer::MODE_FONT_B);
            $print_text = 'Waktu : '.HelperService::inaDate($next_payment->created_at,2)."\n";
            $print_text .= 'Kasir : #'.$next_payment->cashier->id.' '.$next_payment->cashier->first_name."\n";
            $value = number_format(intval($next_payment->paid_value),0,",",".");
            $value = str_pad('NILAI BAYAR',19,' ',STR_PAD_LEFT).str_pad($value,20,' ',STR_PAD_LEFT);
            $print_text .= "\n".$value."\n";
            $value = number_format(intval($next_payment->total_paid),0,",",".");
            $bayar = $payment_type[$next_payment->payment_type].' BAYAR';
            $value = str_pad($bayar,19,' ',STR_PAD_LEFT).str_pad($value,20,' ',STR_PAD_LEFT);
            $print_text .= $value;
            if($next_payment->change>0) {
                $value = number_format(intval($next_payment->change),0,",",".");
                $value = str_pad('KEMBALIAN',19,' ',STR_PAD_LEFT).str_pad($value,20,' ',STR_PAD_LEFT);
                $print_text .= "\n".$value;
            }
            if($next_payment->debt_after>0) {
                $value = number_format(intval($next_payment->debt_after),0,",",".");
                $value = str_pad('BELUM BAYAR',19,' ',STR_PAD_LEFT).str_pad($value,20,' ',STR_PAD_LEFT);
                $print_text .= "\n".$value;
            }
            $printer -> text("\n".$print_text);
            $no_payment++;
        }

        $status_payment = $header->paymentStatus();
        if($status_payment=='Lunas') {
            $print_text = "\nStatus Pembayaran: Lunas";
            $printer -> text("\n".$print_text."\n");
        }
        else {
            $print_text = "\nStatus Pembayaran per \n".HelperService::inaDate(Carbon::now(),2)."\n".$header->paymentStatus();
            $printer -> text("\n".$print_text."\n");
        }

        $printer -> selectPrintMode(169);
        $printer -> text("\n*** TERIMA KASIH ***\n\n");
        $printer -> cut();
        $printer -> pulse();
        $printer -> close();
        if(request()->redirect_back==1) {
            $branch = '';
            if(request()->b) {
                $branch = '?branch='.request()->b;
            }
            return redirect(env('URL_SERVER').'cashier-v2'.$branch);
        }

        else if(request()->redirect_back==2) {
            return redirect(env('URL_SERVER').'search-invoices?invoice='.$invoice_id);
        }
    }

    function printTest2()
    {
        $connector = null;
        $connector = new WindowsPrintConnector('EPSONPOS');

        $printer = new Printer($connector);
        $printer -> setEmphasis(true);
        $printer -> setUnderline(1);
        $printer -> setJustification(Printer::JUSTIFY_CENTER);

        // $img = EscposImage::load("tux.png");
        // $printer -> graphics($img);
        $printer -> selectPrintMode(Printer::MODE_EMPHASIZED);
        $printer -> text('RUMAH CANTIQUE AMANIE'."\n".'SALON & SPA MUSLIMAH'."\n");$printer -> selectPrintMode(169);
        $printer -> text("\n*** TERIMA KASIH ***\n");
        $printer -> cut();
        $printer -> pulse();
        $printer -> close();
    }

}

class item
{
    private $name;
    private $price;
    private $dollarSign;
    public function __construct($name = '', $price = '', $dollarSign = false)
    {
        $this -> name = $name;
        $this -> price = $price;
        $this -> dollarSign = $dollarSign;
    }

    public function __toString()
    {
        $rightCols = 10;
        $leftCols = 38;
        if ($this -> dollarSign) {
            $leftCols = $leftCols / 2 - $rightCols / 2;
        }
        $left = str_pad($this -> name, $leftCols) ;

        $sign = ($this -> dollarSign ? '$ ' : '');
        $right = str_pad($sign . $this -> price, $rightCols, ' ', STR_PAD_LEFT);
        return "$left$right\n";
    }
}
