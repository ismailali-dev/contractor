<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\ListingClaimRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ListingClaimController extends Controller
{
    private function ensureClaimable(Listing $listing): void
    {
        abort_unless(
            in_array($listing->module, ['contractors', 'restaurants', 'real-estate', 'cars'], true)
            && strtolower(trim((string) $listing->status)) === 'published',
            404
        );
    }

    /**
     * @return array<int, string>
     */
    private function claimableEmails(Listing $listing): array
    {
        $emails = [];
        $push = static function (array &$bucket, ?string $value): void {
            $email = strtolower(trim((string) $value));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $bucket[] = $email;
            }
        };

        $push($emails, $listing->user?->email);
        $push($emails, $listing->claim_email);

        if ($listing->module === 'cars') {
            $push($emails, $listing->carDetail?->contact_email);
        }

        if ($listing->module === 'real-estate') {
            $wizard = is_array($listing->propertyDetail?->wizard_data) ? $listing->propertyDetail->wizard_data : [];
            $push($emails, $wizard['email'] ?? null);
            $push($emails, $wizard['contact_email'] ?? null);
        }

        if ($listing->module === 'restaurants') {
            $decoded = json_decode((string) ($listing->excerpt ?? ''), true);
            if (is_array($decoded)) {
                $push($emails, $decoded['email'] ?? null);
            }
        }

        return array_values(array_unique($emails));
    }

    private function matchesClaimableEmail(Listing $listing, string $email): bool
    {
        return in_array($this->normalizeEmail($email), $this->claimableEmails($listing), true);
    }

    private function moduleLabel(Listing $listing): string
    {
        return match ($listing->module) {
            'contractors' => 'contractor',
            'restaurants' => 'restaurant',
            'real-estate' => 'property',
            'cars' => 'car profile',
            default => 'business',
        };
    }

    private function normalizeEmail(string $value): string
    {
        return strtolower(trim($value));
    }

    private function findActiveClaim(Listing $listing, string $email): ?ListingClaimRequest
    {
        return ListingClaimRequest::query()
            ->where('listing_id', $listing->id)
            ->whereRaw('LOWER(TRIM(email)) = ?', [$this->normalizeEmail($email)])
            ->whereNull('used_at')
            ->latest('id')
            ->first();
    }

    private function claimContactPhone(Listing $listing): ?string
    {
        if ($listing->module === 'cars') {
            $phone = trim((string) ($listing->carDetail?->contact_phone ?? ''));
            return $phone !== '' ? $phone : null;
        }

        if ($listing->module === 'real-estate') {
            $wizard = is_array($listing->propertyDetail?->wizard_data) ? $listing->propertyDetail->wizard_data : [];
            $phone = trim((string) ($wizard['phone'] ?? ''));
            return $phone !== '' ? $phone : null;
        }

        if ($listing->module === 'restaurants') {
            $decoded = json_decode((string) ($listing->excerpt ?? ''), true);
            if (is_array($decoded)) {
                $phone = trim((string) ($decoded['phone'] ?? ''));
                return $phone !== '' ? $phone : null;
            }
        }

        return null;
    }

    public function requestOtp(Request $request, Listing $listing): JsonResponse
    {
        $this->ensureClaimable($listing);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = $this->normalizeEmail((string) $data['email']);
        if (! $this->matchesClaimableEmail($listing, $email)) {
            return response()->json([
                'message' => 'The email does not match the registered user for this ' . $this->moduleLabel($listing) . '.',
            ], 422);
        }

        $existingUser = User::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->first();

        if ($existingUser && $listing->user_id && (int) $existingUser->id !== (int) $listing->user_id) {
            return response()->json([
                'message' => 'This email is already registered. Please sign in to continue.',
            ], 422);
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $requestModel = $this->findActiveClaim($listing, $email) ?? new ListingClaimRequest([
            'listing_id' => $listing->id,
            'email' => $email,
        ]);

        $requestModel->fill([
            'otp_hash' => Hash::make($otp),
            'otp_expires_at' => now()->addMinutes(10),
            'otp_attempts' => 0,
            'verified_at' => null,
            'claim_token_hash' => null,
            'claim_token_expires_at' => null,
            'claimed_by_user_id' => null,
            'used_at' => null,
        ]);
        $requestModel->save();

        Mail::raw(
            "Your Monaclick claim code for \"{$listing->title}\" is {$otp}. This code expires in 10 minutes.",
            static function ($message) use ($email, $listing): void {
                $message->to($email)
                    ->subject("Your Monaclick claim code for {$listing->title}");
            }
        );

        return response()->json([
            'ok' => true,
            'message' => 'We sent a 6-digit code to your email.',
            'expires_in_seconds' => 600,
        ]);
    }

    public function verifyOtp(Request $request, Listing $listing): JsonResponse
    {
        $this->ensureClaimable($listing);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'code' => ['required', 'digits:6'],
        ]);

        $email = $this->normalizeEmail((string) $data['email']);
        if (! $this->matchesClaimableEmail($listing, $email)) {
            return response()->json([
                'message' => 'The email does not match the registered user for this ' . $this->moduleLabel($listing) . '.',
            ], 422);
        }

        $claimRequest = $this->findActiveClaim($listing, $email);

        if (! $claimRequest || ! $claimRequest->otp_expires_at || $claimRequest->otp_expires_at->isPast()) {
            return response()->json([
                'message' => 'This code has expired. Please request a new one.',
            ], 422);
        }

        if ((int) $claimRequest->otp_attempts >= 5) {
            return response()->json([
                'message' => 'Too many incorrect attempts. Please request a new code.',
            ], 429);
        }

        if (! Hash::check((string) $data['code'], (string) $claimRequest->otp_hash)) {
            $claimRequest->increment('otp_attempts');

            return response()->json([
                'message' => 'The code you entered is incorrect.',
            ], 422);
        }

        $plainToken = Str::random(64);
        $claimRequest->forceFill([
            'verified_at' => now(),
            'claim_token_hash' => hash('sha256', $plainToken),
            'claim_token_expires_at' => now()->addMinutes(20),
        ])->save();

        return response()->json([
            'ok' => true,
            'message' => 'Code verified. You can now claim this profile.',
            'claim_token' => $plainToken,
        ]);
    }

    public function complete(Request $request, Listing $listing): JsonResponse
    {
        $this->ensureClaimable($listing);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'claim_token' => ['required', 'string', 'min:32'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $email = $this->normalizeEmail((string) $data['email']);
        $claimRequest = $this->findActiveClaim($listing, $email);

        if (
            ! $claimRequest
            || ! $claimRequest->verified_at
            || ! $claimRequest->claim_token_expires_at
            || $claimRequest->claim_token_expires_at->isPast()
            || ! hash_equals((string) $claimRequest->claim_token_hash, hash('sha256', (string) $data['claim_token']))
        ) {
            return response()->json([
                'message' => 'Your claim session is no longer valid. Please verify the code again.',
            ], 422);
        }

        $existingUser = User::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->first();

        $claimedUser = DB::transaction(function () use ($listing, $claimRequest, $email, $existingUser, $data) {
            $freshListing = Listing::query()
                ->whereKey($listing->id)
                ->lockForUpdate()
                ->firstOrFail();

            $displayName = trim((string) $freshListing->title);
            $user = $existingUser;

            if (! $user) {
                $user = User::create([
                    'name' => $displayName !== '' ? $displayName : 'Monaclick Business',
                    'email' => $email,
                    'account_type' => 'business',
                    'company_name' => $displayName !== '' ? $displayName : null,
                    'phone' => $this->claimContactPhone($freshListing),
                    'email_verified_at' => now(),
                    'password' => Hash::make((string) $data['password']),
                ]);
            } else {
                $payload = [
                    'password' => Hash::make((string) $data['password']),
                ];
                if (! $user->email_verified_at) {
                    $payload['email_verified_at'] = now();
                }
                $user->forceFill($payload)->save();
            }

            $freshListing->forceFill([
                'user_id' => $user->id,
            ])->save();

            $claimRequest->forceFill([
                'claimed_by_user_id' => $user->id,
                'used_at' => now(),
            ])->save();

            ListingClaimRequest::query()
                ->where('listing_id', $freshListing->id)
                ->whereKeyNot($claimRequest->id)
                ->whereNull('used_at')
                ->update([
                    'used_at' => now(),
                    'updated_at' => now(),
                ]);

            return $user;
        });

        Auth::login($claimedUser);
        $request->session()->regenerate();

        return response()->json([
            'ok' => true,
            'message' => 'Profile claimed successfully.',
            'redirect' => '/entry/' . $listing->module . '?slug=' . urlencode((string) $listing->slug),
        ]);
    }
}
