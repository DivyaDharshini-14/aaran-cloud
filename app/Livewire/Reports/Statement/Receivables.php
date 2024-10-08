<?php

namespace App\Livewire\Reports\Statement;

use Aaran\Entries\Models\Sale;
use Aaran\Master\Models\Contact;
use Aaran\Transaction\Models\Transaction;
use App\Livewire\Trait\CommonTraitNew;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Receivables extends Component
{
    use CommonTraitNew;
    #region[properties]
    public Collection $contacts;
    public $byParty;
    public $byOrder;
    public $start_date;
    public $end_date;
    public mixed $opening_balance = '0';
    public mixed $sale_total = 0;
    public mixed $receipt_total = 0;
    public mixed $invoiceDate_first = '';
    #endregion

    #region[Contact]
    public function getContact()
    {
        $this->contacts = Contact::where('company_id', '=', session()->get('company_id'))->where('contact_type_id','124')->get();
    }
    #endregion

    #region[opening_balance]

    public function opening_Balance()
    {
        if ($this->byParty) {
            $obj = Contact::find($this->byParty);
            $this->opening_balance = $obj->opening_balance;

            $this->invoiceDate_first = Carbon::now()->subYear()->format('Y-m-d');

            $this->sale_total = Sale::whereDate('invoice_date', '<', $this->start_date?:$this->invoiceDate_first)
                ->where('contact_id','=',$this->byParty)
                ->sum('grand_total');

            $this->receipt_total = Transaction::whereDate('vdate', '<', $this->start_date?:$this->invoiceDate_first)
                ->where('contact_id','=',$this->byParty)
                ->where('mode_id','=',111)
                ->sum('vname');

            $this->opening_balance = $this->opening_balance + $this->sale_total - $this->receipt_total;
        }
        return $this->opening_balance;
    }
    #endregion


    #region[List]

    public function getList()
    {
        $this->opening_Balance();

        $sales = Transaction::select([
            'transactions.company_id',
            'transactions.contact_id',
            DB::raw("'receipt' as mode"),
            "transactions.id as vno",
            'transactions.vdate as vdate',
            DB::raw("'' as grand_total"),
            'transactions.vname',
        ])
            ->where('active_id', '=', 1)
            ->where('contact_id', '=', $this->byParty)
            ->where('mode_id','=',111)
            ->whereDate('vdate', '>=', $this->start_date ?: $this->invoiceDate_first)
            ->whereDate('vdate', '<=', $this->end_date ?: carbon::now()->format('Y-m-d'))
            ->where('company_id', '=', session()->get('company_id'));
        return Sale::select([
            'sales.company_id',
            'sales.contact_id',
            DB::raw("'invoice' as mode"),
            "sales.invoice_no as vno",
            'sales.invoice_date as vdate',
            'sales.grand_total',
            DB::raw("'' as transaction_amount"),
        ])
            ->where('active_id', '=', 1)
            ->where('contact_id', '=', $this->byParty)
            ->whereDate('invoice_date', '>=', $this->start_date ?: $this->invoiceDate_first)
            ->whereDate('invoice_date', '<=', $this->end_date ?: carbon::now()->format('Y-m-d'))
            ->where('company_id', '=', session()->get('company_id'))
            ->union($sales)
            ->orderBy('vdate')
            ->orderBy('mode')->get();
    }

    #endregion
    public function print()
    {
        if ($this->byParty != null) {
            $this->redirect(route('receviables.print',
                [
                    'party' => $this->byParty, 'start_date' => $this->start_date ?: $this->invoiceDate_first,
                    'end_date' => $this->end_date ?: Carbon::now()->format('Y-m-d'),

                ]));
        }
    }


    #region[Render]
    public function render()
    {
        $this->getContact();
        return view('livewire.reports.statement.receivables')->with([
            'list' => $this->getList()
        ]);
    }
    #endregion
}
