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
use HelperService;

class PrintController extends Controller
{
    function printInvoice($invoice_id)
    {
        // return "oke";
        $invoice_id = str_replace('-','/', $invoice_id);
        $header = TransactionHeader::where('invoice_id', $invoice_id)->first();

        $details = TransactionDetail::with(['itemInfo'])->where('header_id', $header->id)->get();
        $date_time = $header->created_at->day.'/'.$header->created_at->month.'/'.$header->created_at->year.' '.
                        $header->created_at->hour.':'.$header->created_at->minute;
        // dd($date_time);

        // dd($details);
        // return "";
        // $array_items = [];
        // $array_items[] = array(
        //     'item_name' => 'Item 1',
        //     'item_qty' => 9,
        //     'item_total_price' => 90000
        // );
        //
        // $array_items[] = array(
        //     'item_name' => 'Item 1234567891012345678912345678',
        //     'item_qty' => 90,
        //     'item_total_price' => 900000
        // );
        $connector = null;
        if(env('OS')=='windows') {
            $connector = new WindowsPrintConnector(env('PRINTER_NAME'));
        }
        else {
            $connector = new CupsPrintConnector(env('PRINTER_NAME'));
        }
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

        $printer -> setEmphasis(false);
        $printer -> setJustification(Printer::JUSTIFY_LEFT);
        $printer -> text($header->invoice_id.'  '.$date_time."\n");
        $cashier = 'Kasir : #'.$header->cashier->id.' '.$header->cashier->first_name;
        $printer -> text($cashier."\n");
        $member = 'Member : ';
        if($header->member_id!=null) {
            $member .= Member::where('member_id',$header->member_id)->first()->full_name.' '.$header->member_id;
        }
        else {
            $member .= '-';
        }
        $printer -> text($member."\n\n");
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

        $payment_type = array(
            1 => 'Tunai',
            2 => 'Kredit',
            3 => 'Credit Card',
            4 => 'Debit Card'
        );
        $printer -> text("\n");
        $bayar = $payment_type[$header->payment_type].' BAYAR';

        $bayar_2 = number_format(intval($header->total_paid),0,",",".");
        $bayar = str_pad($bayar,19,' ',STR_PAD_LEFT).str_pad($bayar_2,20,' ',STR_PAD_LEFT);
        $printer -> text($bayar."\n");
        if($header->payment_type == 1) {
            if($header->change > 0) {
                $change = number_format(intval($header->change),0,",",".");
                $change_2 = str_pad('KEMBALIAN',19,' ',STR_PAD_LEFT).str_pad($change,20,' ',STR_PAD_LEFT);
                $printer -> text($change_2."\n");
            }
        }
        else if($header->payment_type == 2) {
            if($header->is_debt && $header->debt > 0) {
                $debt = number_format(intval($header->debt),0,",",".");
                $debt_2 = str_pad('HUTANG',19,' ',STR_PAD_LEFT).str_pad($debt,20,' ',STR_PAD_LEFT);
                $printer -> text($debt_2."\n");
            }
            if($header->payment2_date != null) {
                $printer -> text("\n");
                $payment2_date = HelperService::createDateTimeObj($header->payment2_date);
                $date_time2= $payment2_date->day.'/'.$payment2_date->month.'/'.$payment2_date->year.' '.
                                $payment2_date->hour.':'.$payment2_date->minute;
                $printer -> text('Pelunasan : '.$date_time2."\n");
                $cashier = 'Kasir : #'.$header->cashier2->id.' '.$header->cashier2->first_name;
                $printer -> text($cashier."\n");

                $total_paid2 =  number_format(intval($header->total_paid2),0,",",".");
                $second_payment =  str_pad('PEMABAYARAN KE-2',19,' ',STR_PAD_LEFT).str_pad($total_paid2, 20,' ',STR_PAD_LEFT);
                $printer -> text("\n".$second_payment."\n");
                if($header->change2 > 0) {
                    $change = number_format(intval($header->change2),0,",",".");
                    $change_2 = str_pad('KEMBALIAN',19,' ',STR_PAD_LEFT).str_pad($change,20,' ',STR_PAD_LEFT);
                    $printer -> text($change_2."\n");
                }
            }
        }

        $printer -> selectPrintMode(169);
        $printer -> text("\n*** TERIMA KASIH ***\n");
        $printer -> cut();
        $printer -> pulse();
        $printer -> close();
        if(request()->redirect_back==1)
            return redirect(env('URL_SERVER').'cashier');
        else if(request()->redirect_back==2)
            return redirect(env('URL_SERVER').'search-invoices?invoice='.$invoice_id);

    }

    function printTest2()
    {
        $connector = new CupsPrintConnector("Epson-9-Pin");
        $printer = new Printer($connector);
        $printer -> setJustification(Printer::JUSTIFY_LEFT);

        $printer -> setJustification(Printer::JUSTIFY_CENTER);
        // return Printer::MODE_EMPHASIZED;
        // ,
   // Printer::MODE_EMPHASIZED,
   // Printer::MODE_DOUBLE_HEIGHT,
   // Printer::MODE_DOUBLE_WIDTH,
   // Printer::MODE_UNDERLINE);

   $modes = array(
    Printer::MODE_FONT_B,
    Printer::MODE_EMPHASIZED,
    Printer::MODE_DOUBLE_HEIGHT,
    Printer::MODE_DOUBLE_WIDTH,
    Printer::MODE_UNDERLINE);
for ($i = 0; $i < pow(2, count($modes)); $i++) {
    $bits = str_pad(decbin($i), count($modes), "0", STR_PAD_LEFT);
    $mode = 0;
    for ($j = 0; $j < strlen($bits); $j++) {
        if (substr($bits, $j, 1) == "1") {
            $mode |= $modes[$j];
        }
    }
    $printer -> selectPrintMode($mode);
    // $printer -> text($mode." | ");
}
    // $printer -> feed();
    // $printer -> cut();
    // $printer -> pulse();
    // $printer -> close();
   return "";

        $printer -> selectPrintMode(Printer::MODE_FONT_B);
        $printer -> text("RUMAH CANTIQUE AMANIE\n");
        $printer -> text("SALON DAN SPA MUSLIMAH\n");
             $printer -> selectPrintMode(Printer::MODE_EMPHASIZED);
             $printer -> text("RUMAH CANTIQUE AMANIE\n");
             $printer -> text("SALON DAN SPA MUSLIMAH\n");
                  $printer -> selectPrintMode(Printer::MODE_DOUBLE_HEIGHT);
                  $printer -> text("RUMAH CANTIQUE AMANIE\n");
                  $printer -> text("SALON DAN SPA MUSLIMAH\n");
                       $printer -> selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
                       $printer -> text("RUMAH CANTIQUE AMANIE\n");
                       $printer -> text("SALON DAN SPA MUSLIMAH\n");
                            $printer -> selectPrintMode(Printer::MODE_UNDERLINE);
                            $printer -> text("RUMAH CANTIQUE AMANIE\n");
                            $printer -> text("SALON DAN SPA MUSLIMAH\n");
        $printer -> feed();
        $printer -> cut();
        $printer -> pulse();
        $printer -> close();
        return phpinfo();
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
