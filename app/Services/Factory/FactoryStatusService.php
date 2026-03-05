<?php

namespace App\Services\Factory;

use App\Enums\AccountStatus;
use App\Enums\AccountVerificationStatus;
use App\Events\Factory\FactoryStatusChanged;
use App\Models\Factory\Factory;
use App\Models\Factory\FactoryStatusHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class FactoryStatusService
{
    /**
     * Mask email for safe logging
     */
    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***@***';  // Fallback for invalid emails
        }

        $localPart = $parts[0];
        $domain = $parts[1];

        // Mask local part: show first char and last char, mask middle
        if (strlen($localPart) <= 1) {
            // For very short local parts, fully mask to avoid exposing the character
            $masked = '***';
        } else {
            $masked = substr($localPart, 0, 1).str_repeat('*', max(0, strlen($localPart) - 2)).substr($localPart, -1);
        }

        return $masked.'@'.$domain;
    }

    /**
     * Check if all required factory details are filled
     */
    public function checkFactoryCompleteness(Factory $factory): array
    {
        $missingFields = $factory->getMissingFields();

        return [
            'basic_info' => [
                'first_name' => (bool) $factory->first_name,
                'last_name' => (bool) $factory->last_name,
                'email' => (bool) $factory->email,
                'phone_number' => (bool) $factory->phone_number,
                'email_verified' => (bool) $factory->email_verified_at,
            ],
            'business_info' => [
                'exists' => (bool) $factory->business,
                'details' => $factory->business ? [
                    'company_name' => (bool) $factory->business->company_name,
                    'registration_number' => (bool) $factory->business->registration_number,
                    'tax_vat_number' => (bool) $factory->business->tax_vat_number,
                    'registered_address' => (bool) $factory->business->registered_address,
                    'country_id' => (bool) $factory->business->country_id,
                    'state_id' => (bool) $factory->business->state_id,
                    'city' => (bool) $factory->business->city,
                    'postal_code' => (bool) $factory->business->postal_code,
                ] : null,
            ],
            'industries' => [
                'assigned' => $factory->relationLoaded('industries') ? $factory->industries->isNotEmpty() : $factory->industries()->exists(),
                'count' => $factory->relationLoaded('industries') ? $factory->industries->count() : $factory->industries()->count(),
            ],
            'location_info' => [
                'has_address' => $factory->relationLoaded('addresses') ? $factory->addresses->whereIn('type', ['facility', 'dist'])->isNotEmpty() : $factory->addresses()->whereIn('type', ['facility', 'dist'])->exists(),
            ],
            'completeness' => [
                'is_complete' => empty($missingFields),
                'missing_fields' => $missingFields,
            ],
        ];
    }

    /**
     * Update factory status
     */
    public function updateStatus(Factory $factory, array $data): array
    {
        DB::beginTransaction();

        try {
            $updateData = [];
            $changes = [];

            // Update account status if provided (must be staged first so that
            // canBeVerified() can reflect the new status when both fields are
            // sent in the same request, e.g. enable + verify simultaneously).
            if (isset($data['account_status'])) {
                $oldStatus = $factory->account_status;
                $newStatus = AccountStatus::tryFrom($data['account_status']);

                if ($newStatus !== null && $newStatus !== $oldStatus) {
                    $updateData['account_status'] = $newStatus;
                    $changes['account_status'] = [
                        'old' => $oldStatus?->label(),
                        'new' => $newStatus->label(),
                    ];
                }
            }

            // Now check verification eligibility using the effective (possibly
            // updated) account_status from this request.
            if (isset($data['account_verified'])) {
                if ((int) $data['account_verified'] === AccountVerificationStatus::VERIFIED->value) {
                    // Temporarily apply the staged account_status so canBeVerified()
                    // reflects the state that will exist after this transaction.
                    $effectiveFactory = clone $factory;
                    if (isset($updateData['account_status'])) {
                        $effectiveFactory->account_status = $updateData['account_status'];
                    }

                    if (! $effectiveFactory->canBeVerified()) {
                        DB::rollBack();

                        return [
                            'success' => false,
                            'factory' => $factory,
                            'changes' => [],
                            'message' => 'Cannot verify factory status. Missing required fields.',
                            'completeness' => $this->checkFactoryCompleteness($factory),
                        ];
                    }
                }
            }

            // Update verification status if provided
            if (isset($data['account_verified'])) {
                $oldStatus = $factory->account_verified;
                $newStatus = AccountVerificationStatus::tryFrom($data['account_verified']);

                if ($newStatus !== null && $newStatus !== $oldStatus) {
                    $updateData['account_verified'] = $newStatus;
                    $changes['account_verified'] = [
                        'old' => $oldStatus?->label(),
                        'new' => $newStatus->label(),
                    ];
                }
            }

            if (empty($updateData) && ! isset($data['verify_email'])) {
                DB::rollBack();

                return [
                    'success' => false,
                    'factory' => $factory,
                    'changes' => [],
                    'message' => 'At least one status must be provided.',
                ];
            }

            // Snapshot email verified status BEFORE applying any updates,
            // to prevent a stale-read race condition.
            $wasEmailVerified = (bool) $factory->email_verified_at;

            // Update status if data provided
            if (! empty($updateData)) {
                $factory->update($updateData);
            }

            // Handle email verification
            if (isset($data['verify_email']) && $data['verify_email']) {
                if (! $wasEmailVerified) {
                    // Mark email as verified
                    $factory->update([
                        'email_verified_at' => now(),
                    ]);

                    $changes['email_verified'] = [
                        'old' => 'Not Verified',
                        'new' => 'Verified by Admin',
                    ];

                    Log::info('Factory email verified by admin', [
                        'factory_id' => $factory->id,
                        'email_masked' => $this->maskEmail($factory->email),
                        'verified_by' => auth('admin_api')->id() ?? auth('admin')->id(),
                    ]);
                }
            }

            // Record history and Log the status changes
            $adminId = auth('admin_api')->id() ?? auth('admin')->id();

            foreach ($changes as $type => $change) {
                FactoryStatusHistory::create([
                    'factory_id' => $factory->id,
                    'admin_id' => $adminId,
                    'status_type' => $type,
                    'old_status' => $change['old'],
                    'new_status' => $change['new'],
                    'reason' => $data['reason'] ?? null,
                ]);
            }

            Log::info('Factory status updated', [
                'factory_id' => $factory->id,
                'changes' => $changes,
                'reason' => $data['reason'] ?? null,
                'changed_by' => $adminId,
            ]);

            // Dispatch Event Notification strictly if there are status or verification changes
            if (! empty($changes)) {
                Event::dispatch(new FactoryStatusChanged(
                    $factory,
                    $changes,
                    $data['reason'] ?? null
                ));
            }

            DB::commit();

            return [
                'success' => true,
                'factory' => $factory->fresh(),
                'changes' => $changes,
                'reason' => $data['reason'] ?? null,
                'message' => 'Factory status updated successfully.',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update factory status', [
                'factory_id' => $factory->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'factory' => $factory,
                'changes' => [],
                'message' => 'Failed to update factory status.',
            ];
        }
    }
}
