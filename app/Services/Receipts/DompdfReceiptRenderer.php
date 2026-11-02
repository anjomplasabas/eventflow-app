<?php

namespace App\Services\Receipts;

use App\Contracts\Receipts\ReceiptRenderer;
use App\Models\Receipt;
use Barryvdh\DomPDF\Facade\Pdf;

class DompdfReceiptRenderer implements ReceiptRenderer
{
    public function render(Receipt $receipt): string
    {
        $receipt->loadMissing('items');

        return Pdf::loadView('receipts.pdf', [
            'receipt' => $receipt,
        ])->output();
    }
}
