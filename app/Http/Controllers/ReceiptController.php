<?php

namespace App\Http\Controllers;

use App\Contracts\Receipts\ReceiptRenderer;
use App\Models\Receipt;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReceiptController extends Controller
{
    public function show(Request $request, string $publicNumber): Response
    {
        $receipt = $this->findOwnedByUser($request, $publicNumber);

        return Inertia::render('me/receipts/show', [
            'receipt' => $this->toViewPayload($receipt),
        ]);
    }

    public function download(Request $request, string $publicNumber, ReceiptRenderer $renderer): StreamedResponse
    {
        $receipt = $this->findOwnedByUser($request, $publicNumber);

        if ($receipt->pdf_path !== null && Storage::disk('local')->exists($receipt->pdf_path)) {
            return Storage::disk('local')->download(
                $receipt->pdf_path,
                "receipt-{$receipt->public_number}.pdf",
                ['Content-Type' => 'application/pdf'],
            );
        }

        // Fallback: render on demand so the attendee is never left waiting.
        $bytes = $renderer->render($receipt);

        return response()->streamDownload(fn () => print $bytes, "receipt-{$receipt->public_number}.pdf", [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function verify(string $publicNumber): Response
    {
        $receipt = Receipt::query()->where('public_number', $publicNumber)->first();

        return Inertia::render('verify/receipt', [
            'receipt' => $receipt !== null ? [
                'public_number' => $receipt->public_number,
                'status' => $receipt->status->value,
                'issued_at' => $receipt->issued_at->toIso8601String(),
                'event_title' => (string) ($receipt->event_snapshot['title'] ?? ''),
                'organizer_name' => (string) ($receipt->organizer_snapshot['name'] ?? ''),
            ] : null,
        ]);
    }

    private function findOwnedByUser(Request $request, string $publicNumber): Receipt
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $receipt = Receipt::query()
            ->with(['items', 'registration'])
            ->where('public_number', $publicNumber)
            ->firstOrFail();

        if ($receipt->registration->user_id !== $user->id && ! $user->isSystemAdmin()) {
            abort(403);
        }

        return $receipt;
    }

    /**
     * @return array<string, mixed>
     */
    private function toViewPayload(Receipt $receipt): array
    {
        return [
            'public_number' => $receipt->public_number,
            'status' => $receipt->status->value,
            'issued_at' => $receipt->issued_at->toIso8601String(),
            'subtotal_display' => Money::of($receipt->subtotal_minor, $receipt->currency)->format(),
            'total_display' => Money::of($receipt->total_minor, $receipt->currency)->format(),
            'currency' => $receipt->currency,
            'organizer' => $receipt->organizer_snapshot,
            'event' => $receipt->event_snapshot,
            'attendee' => $receipt->attendee_snapshot,
            'items' => $receipt->items->map(fn ($item) => [
                'label' => $item->label,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price_display' => Money::of($item->unit_price_minor, $receipt->currency)->format(),
                'line_total_display' => Money::of($item->line_total_minor, $receipt->currency)->format(),
            ])->values(),
            'pdf_ready' => $receipt->pdf_path !== null,
        ];
    }
}
