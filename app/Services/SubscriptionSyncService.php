<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\PaymentMethod;
use App\Models\SubscriptionOrder;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SubscriptionSyncService
{
    /**
     * @return array{package_slug:string,package_label:string,package_price:string,selected_services:array<int,string>,selected_services_details:array<int,array{label:string,price:string}>,has_promotion:bool}
     */
    public function extractPromotionData(Listing $listing): array
    {
        $module = (string) $listing->module;
        $packageSlug = '';
        $selectedServices = [];
        $selectedServicesDetails = [];

        if ($module === 'real-estate') {
            $wizardData = is_array($listing->propertyDetail?->wizard_data) ? $listing->propertyDetail->wizard_data : [];
            $packageSlug = strtolower(trim((string) ($wizardData['promotion_package'] ?? $wizardData['package'] ?? '')));

            if (! empty($wizardData['service_certify'])) {
                $selectedServices[] = 'certify';
            }
            if (! empty($wizardData['service_lifts'])) {
                $selectedServices[] = 'lifts';
            }
            if (! empty($wizardData['service_analytics'])) {
                $selectedServices[] = 'analytics';
            }
        } else {
            $featureTokens = collect(is_array($listing->features) ? $listing->features : [])
                ->map(fn ($value) => trim((string) $value))
                ->filter();

            $packageToken = (string) ($featureTokens->first(fn (string $token) => str_starts_with(strtolower($token), 'promo-package:')) ?? '');
            $packageSlug = $packageToken !== '' ? strtolower(substr($packageToken, strlen('promo-package:'))) : '';

            foreach (['certify', 'lifts', 'analytics'] as $serviceKey) {
                if ($featureTokens->contains('promo-service:' . $serviceKey)) {
                    $selectedServices[] = $serviceKey;
                }
            }
        }

        foreach ($selectedServices as $serviceKey) {
            $selectedServicesDetails[] = [
                'label' => $this->serviceLabel($module, $serviceKey),
                'price' => $this->servicePrice($serviceKey),
            ];
        }

        return [
            'package_slug' => $packageSlug,
            'package_label' => $this->packageLabel($packageSlug),
            'package_price' => $this->packagePrice($packageSlug),
            'selected_services' => array_values(array_unique($selectedServices)),
            'selected_services_details' => $selectedServicesDetails,
            'has_promotion' => $packageSlug !== '' || count($selectedServicesDetails) > 0,
        ];
    }

    public function syncForUser(User $user): void
    {
        $user->loadMissing([
            'listings.city',
            'listings.propertyDetail',
            'listings.contractorDetail',
            'listings.carDetail',
        ]);

        foreach ($user->listings as $listing) {
            $this->syncForListing($listing);
        }
    }

    public function syncForListing(Listing $listing): ?SubscriptionOrder
    {
        if (! $listing->user_id) {
            return null;
        }

        $listing->loadMissing(['city', 'propertyDetail', 'contractorDetail', 'carDetail']);
        $promotion = $this->extractPromotionData($listing);
        $now = Carbon::now();
        $paymentMethodId = PaymentMethod::query()
            ->where('user_id', $listing->user_id)
            ->where('status', 'active')
            ->orderByDesc('is_primary')
            ->latest('id')
            ->value('id');

        $activeQuery = SubscriptionOrder::query()
            ->where('user_id', $listing->user_id)
            ->where('listing_id', $listing->id)
            ->where('status', 'active');

        if (! $promotion['has_promotion']) {
            $activeQuery->update([
                'status' => 'previous',
                'last_synced_at' => $now,
            ]);

            return null;
        }

        $snapshotHash = sha1(json_encode([
            'module' => $listing->module,
            'package_slug' => $promotion['package_slug'],
            'services' => $promotion['selected_services'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');

        $current = $activeQuery->latest('id')->first();

        if ($current && $current->snapshot_hash === $snapshotHash) {
            $current->update([
                'module' => (string) $listing->module,
                'package_slug' => $promotion['package_slug'],
                'package_label' => $promotion['package_label'],
                'package_price' => $promotion['package_price'],
                'selected_services' => $promotion['selected_services'],
                'selected_services_details' => $promotion['selected_services_details'],
                'payment_method_id' => $paymentMethodId,
                'last_synced_at' => $now,
            ]);

            return $current->fresh();
        }

        $activeQuery->update([
            'status' => 'previous',
            'last_synced_at' => $now,
        ]);

        return SubscriptionOrder::query()->create([
            'user_id' => $listing->user_id,
            'listing_id' => $listing->id,
            'payment_method_id' => $paymentMethodId,
            'order_number' => $this->nextOrderNumber(),
            'module' => (string) $listing->module,
            'package_slug' => $promotion['package_slug'],
            'package_label' => $promotion['package_label'],
            'package_price' => $promotion['package_price'],
            'selected_services' => $promotion['selected_services'],
            'selected_services_details' => $promotion['selected_services_details'],
            'status' => 'active',
            'admin_status' => 'approved',
            'source' => 'listing-sync',
            'snapshot_hash' => $snapshotHash,
            'started_at' => $now,
            'last_synced_at' => $now,
        ]);
    }

    private function nextOrderNumber(): string
    {
        do {
            $orderNumber = 'SUB-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        } while (SubscriptionOrder::query()->where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }

    private function packageLabel(string $slug): string
    {
        return match ($slug) {
            'easy-start' => 'Easy Start',
            'fast-sale' => 'Fast Sale',
            'turbo-boost' => 'Turbo Boost',
            default => '',
        };
    }

    private function packagePrice(string $slug): string
    {
        return match ($slug) {
            'easy-start' => '$25 / month',
            'fast-sale' => '$49 / month',
            'turbo-boost' => '$70 / month',
            default => '',
        };
    }

    private function serviceLabel(string $module, string $serviceKey): string
    {
        return match ($serviceKey) {
            'certify' => match ($module) {
                'contractors' => 'Check and certify my business by Monaclick experts',
                'restaurants' => 'Check and certify my restaurant by Monaclick experts',
                default => 'Check and certify my ad by Monaclick experts',
            },
            'lifts' => '10 lifts to the top of the list (daily, 7 days)',
            'analytics' => 'Detailed user engagement analytics',
            default => Str::headline($serviceKey),
        };
    }

    private function servicePrice(string $serviceKey): string
    {
        return match ($serviceKey) {
            'certify' => '$35',
            'lifts' => '$29 / month',
            'analytics' => '$15 / month',
            default => '',
        };
    }
}
