<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use App\Models\SubscriptionOrder;
use App\Services\SubscriptionSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountBillingController extends Controller
{
    public function __construct(
        private readonly SubscriptionSyncService $subscriptionSyncService,
    ) {
    }

    public function paymentMethods(Request $request): JsonResponse
    {
        $methods = $request->user()
            ->paymentMethods()
            ->orderByDesc('is_primary')
            ->latest('id')
            ->get()
            ->map(fn (PaymentMethod $method) => $this->transformPaymentMethod($method))
            ->values();

        return response()->json(['data' => $methods]);
    }

    public function storePaymentMethod(Request $request): JsonResponse
    {
        $data = $this->validatePaymentMethod($request);
        $user = $request->user();

        $method = $user->paymentMethods()->create($this->payloadForPaymentMethod($data, $user->paymentMethods()->doesntExist()));
        $this->syncActiveSubscriptionsPaymentMethod($user->id);

        return response()->json([
            'message' => 'Payment method saved.',
            'data' => $this->transformPaymentMethod($method->fresh()),
        ], 201);
    }

    public function updatePaymentMethod(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        abort_unless($paymentMethod->user_id === $request->user()->id, 404);

        $data = $this->validatePaymentMethod($request);
        $paymentMethod->update($this->payloadForPaymentMethod($data, (bool) $paymentMethod->is_primary));
        $this->syncActiveSubscriptionsPaymentMethod($request->user()->id);

        return response()->json([
            'message' => 'Payment method updated.',
            'data' => $this->transformPaymentMethod($paymentMethod->fresh()),
        ]);
    }

    public function destroyPaymentMethod(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        abort_unless($paymentMethod->user_id === $request->user()->id, 404);

        $wasPrimary = (bool) $paymentMethod->is_primary;
        $paymentMethod->delete();

        if ($wasPrimary) {
            $replacement = $request->user()->paymentMethods()->latest('id')->first();
            if ($replacement) {
                $replacement->update(['is_primary' => true]);
            }
        }
        $this->syncActiveSubscriptionsPaymentMethod($request->user()->id);

        return response()->json(['message' => 'Payment method deleted.']);
    }

    public function subscriptions(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->subscriptionSyncService->syncForUser($user);

        $orders = SubscriptionOrder::query()
            ->with(['listing.city', 'paymentMethod'])
            ->where('user_id', $user->id)
            ->latest('id')
            ->get()
            ->map(fn (SubscriptionOrder $order) => $this->transformSubscription($order))
            ->values();

        return response()->json(['data' => $orders]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePaymentMethod(Request $request): array
    {
        $base = $request->validate([
            'type' => ['required', 'in:card,paypal'],
        ]);

        if ($base['type'] === 'card') {
            return $request->validate([
                'type' => ['required', 'in:card'],
                'number' => ['required', 'string', 'min:13'],
                'name' => ['required', 'string', 'min:2', 'max:255'],
                'expiry' => ['required', 'regex:/^(0[1-9]|1[0-2])\/\d{2}$/'],
            ]);
        }

        return $request->validate([
            'type' => ['required', 'in:paypal'],
            'paypal_email' => ['required', 'email'],
        ]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function payloadForPaymentMethod(array $data, bool $isPrimary): array
    {
        if (($data['type'] ?? '') === 'paypal') {
            return [
                'type' => 'paypal',
                'provider' => 'paypal',
                'paypal_email' => strtolower(trim((string) $data['paypal_email'])),
                'is_primary' => $isPrimary,
                'status' => 'active',
            ];
        }

        $digits = preg_replace('/\D+/', '', (string) ($data['number'] ?? '')) ?: '';
        [$month, $year] = explode('/', (string) ($data['expiry'] ?? '00/00'));
        $fullYear = 2000 + (int) $year;

        return [
            'type' => 'card',
            'provider' => 'card',
            'brand' => $this->detectBrand($digits),
            'card_number' => $digits,
            'card_last_four' => substr($digits, -4),
            'card_holder_name' => trim((string) ($data['name'] ?? '')),
            'expiry_month' => (int) $month,
            'expiry_year' => $fullYear,
            'is_primary' => $isPrimary,
            'status' => 'active',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformPaymentMethod(PaymentMethod $method): array
    {
        return [
            'id' => (string) $method->id,
            'type' => (string) $method->type,
            'brand' => (string) ($method->brand ?? 'card'),
            'number' => (string) ($method->card_number ?? ''),
            'last_four' => (string) ($method->card_last_four ?? ''),
            'name' => (string) ($method->card_holder_name ?? ''),
            'expiry' => (string) $method->display_expiry,
            'paypal_email' => (string) ($method->paypal_email ?? ''),
            'is_primary' => (bool) $method->is_primary,
            'status' => (string) $method->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformSubscription(SubscriptionOrder $order): array
    {
        $listing = $order->listing;
        $module = (string) ($order->module ?: ($listing?->module ?? ''));

        return [
            'id' => $order->id,
            'order_number' => (string) $order->order_number,
            'listing_id' => $listing?->id,
            'title' => (string) ($listing?->title ?: 'Listing subscription'),
            'module' => $module,
            'module_label' => \App\Models\Listing::MODULE_OPTIONS[$module] ?? ucfirst(str_replace('-', ' ', $module)),
            'listing_status' => (string) ($listing?->status ?? ''),
            'package_slug' => (string) ($order->package_slug ?? ''),
            'package_label' => (string) ($order->package_label ?? ''),
            'package_price' => (string) ($order->package_price ?? ''),
            'selected_services' => is_array($order->selected_services) ? $order->selected_services : [],
            'selected_services_details' => is_array($order->selected_services_details) ? $order->selected_services_details : [],
            'status' => (string) $order->status,
            'admin_status' => (string) $order->admin_status,
            'started_at' => optional($order->started_at)->toIso8601String(),
            'created_at' => optional($order->created_at)->toIso8601String(),
            'payment_method' => $order->paymentMethod ? $this->transformPaymentMethod($order->paymentMethod) : null,
            'manage_url' => $listing ? $this->manageUrlForListing($listing->module, $listing->id) : '',
        ];
    }

    private function detectBrand(string $digits): string
    {
        if (preg_match('/^4/', $digits) === 1) {
            return 'visa';
        }

        if (preg_match('/^(5[1-5]|2[2-7])/', $digits) === 1) {
            return 'mastercard';
        }

        return 'card';
    }

    private function manageUrlForListing(string $module, int $listingId): string
    {
        return match ($module) {
            'contractors' => '/add-contractor-promotion?edit=' . $listingId,
            'cars' => '/add-car-promotion?edit=' . $listingId,
            'restaurants' => '/add-restaurant-promotion?edit=' . $listingId,
            default => '/add-property-promotion?edit=' . $listingId,
        };
    }

    private function syncActiveSubscriptionsPaymentMethod(int $userId): void
    {
        $paymentMethodId = PaymentMethod::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->orderByDesc('is_primary')
            ->latest('id')
            ->value('id');

        SubscriptionOrder::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->update(['payment_method_id' => $paymentMethodId]);
    }
}
