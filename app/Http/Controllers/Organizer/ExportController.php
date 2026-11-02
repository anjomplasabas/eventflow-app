<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateReceiptRegisterExport;
use App\Jobs\GenerateReceiptZipExport;
use App\Models\Export;
use App\Models\Organizer;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    use AuthorizesRequests;

    public function index(Organizer $organizer): Response
    {
        $this->authorize('update', $organizer);

        $exports = Export::query()
            ->where('organizer_id', $organizer->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (Export $e) => $this->toListItem($e));

        return Inertia::render('organizer/exports/index', [
            'organizer' => $organizer->only(['id', 'slug', 'name']),
            'exports' => $exports,
        ]);
    }

    public function storeRegister(Request $request, Organizer $organizer): RedirectResponse
    {
        $this->authorize('update', $organizer);

        $filters = $request->validate([
            'status' => ['nullable', 'string', 'max:32'],
            'issued_from' => ['nullable', 'date'],
            'issued_to' => ['nullable', 'date'],
        ]);

        $export = Export::query()->create([
            'organizer_id' => $organizer->id,
            'user_id' => (int) $request->user()?->id,
            'type' => 'receipt_register',
            'filters' => $filters !== [] ? $filters : null,
            'expires_at' => now()->addDays(7),
        ]);

        GenerateReceiptRegisterExport::dispatch($export->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('CSV export queued.')]);

        return redirect()->route('organizer.exports.index', $organizer);
    }

    public function storeZip(Request $request, Organizer $organizer): RedirectResponse
    {
        $this->authorize('update', $organizer);

        $filters = $request->validate([
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
        ]);

        $export = Export::query()->create([
            'organizer_id' => $organizer->id,
            'user_id' => (int) $request->user()?->id,
            'type' => 'receipt_zip',
            'filters' => $filters !== [] ? $filters : null,
            'expires_at' => now()->addDays(7),
        ]);

        GenerateReceiptZipExport::dispatch($export->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ZIP export queued.')]);

        return redirect()->route('organizer.exports.index', $organizer);
    }

    public function download(Organizer $organizer, string $uuid): StreamedResponse
    {
        $this->authorize('update', $organizer);

        $export = Export::query()
            ->where('organizer_id', $organizer->id)
            ->where('uuid', $uuid)
            ->firstOrFail();

        abort_unless($export->isCompleted(), 409, 'Export is not ready.');
        abort_if($export->isExpired(), 410, 'Export has expired.');
        abort_unless($export->file_path !== null && Storage::disk('local')->exists($export->file_path), 404);

        return Storage::disk('local')->download(
            $export->file_path,
            $export->filename ?? basename($export->file_path),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toListItem(Export $e): array
    {
        return [
            'uuid' => $e->uuid,
            'type' => $e->type,
            'status' => $e->status,
            'row_count' => $e->row_count,
            'filename' => $e->filename,
            'created_at' => $e->created_at?->toIso8601String(),
            'completed_at' => $e->completed_at?->toIso8601String(),
            'expires_at' => $e->expires_at->toIso8601String(),
            'failure_reason' => $e->failure_reason,
            'can_download' => $e->isCompleted() && ! $e->isExpired() && $e->file_path !== null,
        ];
    }
}
