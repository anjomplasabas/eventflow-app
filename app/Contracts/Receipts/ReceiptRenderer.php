<?php

namespace App\Contracts\Receipts;

use App\Models\Receipt;

interface ReceiptRenderer
{
    /**
     * Render the receipt to a PDF byte string.
     */
    public function render(Receipt $receipt): string;
}
