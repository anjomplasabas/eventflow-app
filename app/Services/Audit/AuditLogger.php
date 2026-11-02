<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    public function __construct(private readonly ?Request $request = null) {}

    /**
     * Record an audit entry.
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        string $action,
        Model $subject,
        ?array $before = null,
        ?array $after = null,
        ?User $actor = null,
    ): AuditLog {
        $actor ??= Auth::user() instanceof User ? Auth::user() : null;
        $request = $this->request ?? (app()->bound('request') ? app('request') : null);

        return AuditLog::create([
            'actor_user_id' => $actor?->getKey(),
            'actor_type' => $actor !== null ? $actor::class : null,
            'action' => $action,
            'subject_type' => $subject::class,
            'subject_id' => (int) $subject->getKey(),
            'before' => $before,
            'after' => $after,
            'request_id' => $request?->headers->get('X-Request-Id'),
            'ip' => $request?->ip(),
            'user_agent' => $request instanceof Request ? substr((string) $request->userAgent(), 0, 1024) : null,
        ]);
    }
}
