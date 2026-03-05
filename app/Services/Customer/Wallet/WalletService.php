<?php

namespace App\Services\Customer\Wallet;

use App\Models\Customer\Payment\VendorWallet;
use App\Models\Customer\Payment\VendorWalletTransaction;
use Illuminate\Support\Str;

class WalletService
{
    public static function credit(int $vendorId, float $amount, string $paymentMethod, string $description, ?string $reference = null): VendorWallet
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException(__('Credit amount must be positive'));
        }
        $wallet = VendorWallet::firstOrCreate(
            ['vendor_id' => $vendorId],
            ['balance' => 0]
        );

        $wallet->increment('balance', $amount);
        $wallet->refresh();
        VendorWalletTransaction::create([
            'wallet_id' => $wallet->id,
            'transaction_id' => $reference ?? Str::uuid(),
            'type' => 'credit',
            'amount' => $amount,
            'balance_after' => $wallet->balance,
            'payment_method' => $paymentMethod,
            'description' => $description,
            'status' => VendorWalletTransaction::STATUS_COMPLETED,
        ]);

        return $wallet;
    }

    public static function initiateCredit(int $vendorId, float $amount, string $paymentMethod, string $description, ?string $reference = null): VendorWalletTransaction
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException(__('Credit amount must be positive'));
        }
        $wallet = VendorWallet::firstOrCreate(
            ['vendor_id' => $vendorId],
            ['balance' => 0]
        );

        return VendorWalletTransaction::create([
            'wallet_id' => $wallet->id,
            'transaction_id' => $reference ?? Str::uuid(),
            'type' => 'credit',
            'amount' => $amount,
            'status' => VendorWalletTransaction::STATUS_PENDING,
            'balance_after' => $wallet->balance,
            'payment_method' => $paymentMethod,
            'description' => $description,
        ]);
    }

    public static function confirmCredit(string $reference): ?VendorWallet
    {
        $transaction = VendorWalletTransaction::query()
            ->where('transaction_id', $reference)
            ->where('type', 'credit')
            ->first();

        if (! $transaction) {
            return null;
        }

        if ($transaction->status === VendorWalletTransaction::STATUS_COMPLETED) {
            return $transaction->wallet;
        }

        $wallet = $transaction->wallet;

        return \Illuminate\Support\Facades\DB::transaction(function () use ($wallet, $transaction) {
            $wallet->increment('balance', $transaction->amount);
            $wallet->refresh();

            $transaction->update([
                'status' => VendorWalletTransaction::STATUS_COMPLETED,
                'balance_after' => $wallet->balance,
            ]);

            return $wallet;
        });
    }

    public static function failCredit(string $reference): void
    {
        $transaction = VendorWalletTransaction::query()
            ->where('transaction_id', $reference)
            ->where('type', 'credit')
            ->first();

        if ($transaction && $transaction->status === VendorWalletTransaction::STATUS_PENDING) {
            $transaction->update(['status' => VendorWalletTransaction::STATUS_FAILED]);
        }
    }

    public static function debit(int $vendorId, float $amount, string $description, ?string $reference = null): VendorWallet
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException(__('Debit amount must be positive'));
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($vendorId, $amount, $description, $reference) {
            $wallet = VendorWallet::where('vendor_id', $vendorId)->lockForUpdate()->firstOrCreate(
                ['vendor_id' => $vendorId],
                ['balance' => 0]
            );

            if ($wallet->balance < $amount) {
                throw new \RuntimeException(__('Insufficient wallet balance'));
            }

            $wallet->decrement('balance', $amount);
            $wallet->refresh();

            VendorWalletTransaction::create([
                'wallet_id' => $wallet->id,
                'transaction_id' => $reference ?? Str::uuid(),
                'type' => 'debit',
                'amount' => $amount,
                'balance_after' => $wallet->balance,
                'payment_method' => 'wallet',
                'description' => $description,
                'status' => VendorWalletTransaction::STATUS_COMPLETED,
            ]);

            return $wallet;
        });
    }

    public static function getBalance(int $vendorId): float
    {
        return VendorWallet::where('vendor_id', $vendorId)->value('balance') ?? 0.0;
    }

    public static function hasReference(string $reference): bool
    {
        return VendorWalletTransaction::query()
            ->where('transaction_id', $reference)
            ->exists();
    }
}
