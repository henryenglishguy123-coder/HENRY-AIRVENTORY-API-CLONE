<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('sales_orders')) {
            return;
        }

        // We use DB facade to update existing records to avoid Model events or side effects
        // and to ensure we don't depend on the current state of the Model class.
        DB::table('sales_orders')
            ->whereNotNull('remote_ip')
            ->orderBy('id')
            ->chunk(100, function ($orders) {
                foreach ($orders as $order) {
                    $ip = $order->remote_ip;
                    $newIp = null;

                    if (empty($ip)) {
                        continue;
                    }

                    // Anonymize IPv4
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        $parts = explode('.', $ip);
                        array_pop($parts);
                        $parts[] = '0';
                        $newIp = implode('.', $parts);
                    }
                    // Anonymize IPv6
                    elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                        $packed = inet_pton($ip);
                        if ($packed !== false) {
                            // Mask last 80 bits (keep first 48 bits / 6 bytes)
                            $mask = str_repeat(chr(0xFF), 6).str_repeat(chr(0x00), 10);
                            $masked = $packed & $mask;
                            $newIp = inet_ntop($masked);
                        }
                    } else {
                        // Unknown format, anonymize with a keyed HMAC to prevent reversal
                        $newIp = 'anonymized-'.hash_hmac('sha256', $ip, (string) config('app.key', 'app-secret-key'));
                    }

                    if ($newIp && $newIp !== $ip) {
                        DB::table('sales_orders')
                            ->where('id', $order->id)
                            ->update(['remote_ip' => $newIp]);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Anonymization is irreversible.
    }
};
