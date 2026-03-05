<?php

namespace App\Services\Notifications;

use App\Support\Customers\CustomerMeta;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailNotificationService
{
    public function isEnabled(int $vendorId): bool
    {
        $val = CustomerMeta::get($vendorId, 'notify_email', '0');

        return \in_array($val, ['1', 1, true, 'true', 'yes', 'on'], true);
    }

    /**
     * Queue an email for a vendor if notifications are enabled.
     *
     * Deduplication: Builds a deterministic key from vendorId, recipient, mailable name, and a hash of args.
     * If args are unserializable, falls back to a random hash, which disables dedupe intentionally (fail-open).
     * This is by design to avoid silently suppressing emails due to serialization edge cases.
     *
     * @param  string|Mailable  $mailable  Class name or Mailable instance
     * @param  array  $args  Constructor args when $mailable is a class name
     * @param  ?string  $dedupeKey  Optional explicit dedupe key
     * @param  bool  $afterCommitOnly  Only queue after an active DB transaction commits
     */
    public function queueIfEnabledForVendor(
        int $vendorId,
        string $recipientEmail,
        string|Mailable $mailable,
        array $args = [],
        ?string $dedupeKey = null,
        bool $afterCommitOnly = false
    ): bool {
        if (! $this->isEnabled($vendorId)) {
            return false;
        }
        if (! \is_string($mailable) && ! empty($args)) {
            throw new \InvalidArgumentException('Cannot pass $args when $mailable is already an instance.');
        }
        $mailableName = \is_string($mailable) ? $mailable : \get_class($mailable);
        if ($dedupeKey === null) {
            try {
                $argsHash = md5(serialize($args));
            } catch (\Throwable) {
                try {
                    $argsHash = md5(json_encode($args, JSON_THROW_ON_ERROR) ?: '');
                } catch (\Throwable) {
                    try {
                        $fallback = bin2hex(random_bytes(16));
                    } catch (\Throwable) {
                        $fallback = uniqid('', true);
                    }
                    $argsHash = md5($fallback);
                    $atPos = strrpos($recipientEmail, '@');
                    $domain = $atPos !== false ? substr($recipientEmail, $atPos + 1) : '';
                    Log::warning('Unserializable email args; using random dedupe fallback', [
                        'vendor_id' => $vendorId,
                        'recipient_domain' => $domain,
                        'mailable' => $mailableName,
                    ]);
                }
            }
            $key = 'email:'.md5($vendorId.'|'.$recipientEmail.'|'.$mailableName.'|'.$argsHash);
        } else {
            $key = $dedupeKey;
        }

        // Use a configurable TTL to avoid indefinite cache growth. Default 24 hours.
        $hours = config('notifications.email_dedupe_hours', 24);
        $ttl = now()->addHours($hours);

        $instance = \is_string($mailable) ? new $mailable(...$args) : $mailable;

        if ($afterCommitOnly) {
            // Check dedupe key upfront before scheduling, for consistent return value
            if (! Cache::add($key, true, $ttl)) {
                return false;
            }
            DB::afterCommit(function () use ($instance, $recipientEmail, $vendorId, $mailableName, $key) {
                try {
                    Mail::to($recipientEmail)->queue($instance);
                } catch (\Throwable $e) {
                    $this->logEmailQueueError($e, $vendorId, $recipientEmail, $mailableName, $key);
                    Cache::forget($key);
                }
            });

            return true;
        }

        // Immediate behavior (no after-commit requirement)
        if (! Cache::add($key, true, $ttl)) {
            return false;
        }
        try {
            if (DB::transactionLevel() > 0) {
                $instance->afterCommit();
            }
            Mail::to($recipientEmail)->queue($instance);
        } catch (\Throwable $e) {
            $this->logEmailQueueError($e, $vendorId, $recipientEmail, $mailableName, $key);
            Cache::forget($key);

            return false;
        }

        return true;
    }

    private function logEmailQueueError(\Throwable $e, int $vendorId, string $recipientEmail, string $mailableName, string $key): void
    {
        $atPos = strrpos($recipientEmail, '@');
        $domain = $atPos !== false ? substr($recipientEmail, $atPos + 1) : '';
        Log::error('Failed to queue email notification', [
            'vendor_id' => $vendorId,
            'email_domain' => $domain,
            'mailable' => $mailableName,
            'dedupe_key' => $key,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}
