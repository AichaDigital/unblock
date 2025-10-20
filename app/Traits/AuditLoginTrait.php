<?php

namespace App\Traits;

use App\Enums\AuditAction;
use App\Models\ActionAudit;
use Illuminate\Support\Facades\Log;
use Throwable;

trait AuditLoginTrait
{
    public function audit(string $ip, string $action, ?string $message, bool $is_fail = false): void
    {
        try {
            ActionAudit::create([
                'ip' => $ip,
                'action' => AuditAction::getValueByKey($action),
                'message' => $message,
                'is_fail' => $is_fail,
            ]);

        } catch (Throwable $th) {
            Log::error($th->getMessage());
        }

    }
}
