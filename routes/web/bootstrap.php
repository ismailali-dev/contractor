<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\ListingSubmissionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AccountBillingController;
use App\Http\Controllers\Api\PublicListingController;
use App\Http\Controllers\Api\ListingClaimController;
use App\Models\Listing;
use App\Models\ListingClaimRequest;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\TaxonomyController;
use App\Http\Controllers\Admin\ExportController;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

$normalizeUsPhone = static function (?string $value): string {
    $raw = trim((string) $value);
    if ($raw === '') {
        return '';
    }

    $digits = preg_replace('/\D+/', '', $raw) ?? '';
    if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
        $digits = substr($digits, 1);
    }

    if (strlen($digits) !== 10) {
        throw ValidationException::withMessages([
            'phone' => 'Enter a valid US phone number with 10 digits.',
        ]);
    }

    return sprintf('(%s) %s-%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6, 4));
};

$formatUsPhoneForDisplay = static function (?string $value): string {
    $raw = trim((string) $value);
    if ($raw === '') {
        return '';
    }

    $digits = preg_replace('/\D+/', '', $raw) ?? '';
    if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
        $digits = substr($digits, 1);
    }

    if (strlen($digits) !== 10) {
        return $raw;
    }

    return sprintf('(%s) %s-%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6, 4));
};

$serve = static function (string $file) use ($formatUsPhoneForDisplay) {
    $path = public_path($file);
    $currentRequestPath = '/' . ltrim(request()->path(), '/');
    abort_unless(file_exists($path), 404);
    $html = file_get_contents($path);

    // Safety net for add-listing templates: if a route accidentally points to a self-redirect stub
    // (for example a finder shim that redirects back to the same pretty URL), serve the canonical
    // `*-page.html` template instead so the page doesn't get stuck in a reload loop.
    if (str_starts_with($file, 'add-') && str_ends_with($file, '.html')) {
        $fallbackFile = preg_replace('/\.html$/', '-page.html', $file);
        $fallbackPath = is_string($fallbackFile) ? public_path($fallbackFile) : null;
        if ($fallbackPath && file_exists($fallbackPath)) {
            $normalizedHtml = strtolower(preg_replace('/\s+/', ' ', $html) ?? $html);
            $normalizedRequestPath = strtolower($currentRequestPath);
            $selfRedirectMarkers = [
                'content="0;url=' . $normalizedRequestPath . '"',
                "window.location.replace('" . $normalizedRequestPath . "')",
                'window.location.replace("' . $normalizedRequestPath . '")',
            ];
            foreach ($selfRedirectMarkers as $marker) {
                if (! str_contains($normalizedHtml, $marker)) {
                    continue;
                }
                $path = $fallbackPath;
                $html = file_get_contents($path);
                break;
            }
        }
    }

    $html = str_replace('data-pwa="true"', 'data-pwa="false"', $html);
    // The combined homepage is served at the canonical root URL.
    // Keep legacy templates working without exposing /combined in navigation.
    $html = str_replace('href="/combined"', 'href="/"', $html);
    $html = str_replace('__CSRF_TOKEN__', csrf_token(), $html);
    $html = str_replace(
        [
            'href="/terms-and-conditions">Privacy</a>',
            'href="/terms-and-conditions.html" target="_blank" rel="noopener">Privacy Policy</a>',
        ],
        [
            'href="/privacy-policy">Privacy</a>',
            'href="/privacy-policy" target="_blank" rel="noopener">Privacy Policy</a>',
        ],
        $html
    );

    // Contractor onboarding must always load its validation guard, even when an older
    // static template is present in the shared-hosting document root.
    if (
        str_starts_with($currentRequestPath, '/add-contractor')
        && ! str_contains($html, 'monaclick-contractor-wizard.js')
    ) {
        $wizardScript = '<script src="/finder/assets/js/monaclick-contractor-wizard.js"></script>';
        $html = str_contains($html, '</body>')
            ? str_replace('</body>', $wizardScript . '</body>', $html)
            : $html . $wizardScript;
    }
    // Cache-bust frequently updated assets without touching every template file.
    $assetVersions = [
        '/finder/assets/js/monaclick-global-footer.js' => [
            public_path('finder/assets/js/monaclick-global-footer.js'),
            base_path('../finder/assets/js/monaclick-global-footer.js'),
        ],
        '/finder/assets/js/monaclick-listings-dynamic.js' => [
            public_path('finder/assets/js/monaclick-listings-dynamic.js'),
            base_path('../finder/assets/js/monaclick-listings-dynamic.js'),
        ],
        '/finder/assets/js/monaclick-entry-dynamic.js' => [
            public_path('finder/assets/js/monaclick-entry-dynamic.js'),
            base_path('../finder/assets/js/monaclick-entry-dynamic.js'),
        ],
        '/finder/assets/js/monaclick-claim-dynamic.js' => [
            public_path('finder/assets/js/monaclick-claim-dynamic.js'),
            base_path('../finder/assets/js/monaclick-claim-dynamic.js'),
        ],
        '/finder/assets/js/monaclick-entry-features-patch.js' => [
            public_path('finder/assets/js/monaclick-entry-features-patch.js'),
            base_path('../finder/assets/js/monaclick-entry-features-patch.js'),
        ],
        '/finder/assets/js/monaclick-contractor-wizard.js' => [
            public_path('finder/assets/js/monaclick-contractor-wizard.js'),
            base_path('../finder/assets/js/monaclick-contractor-wizard.js'),
        ],
        '/finder/assets/js/monaclick-home-combined.js' => [
            public_path('finder/assets/js/monaclick-home-combined.js'),
            base_path('../finder/assets/js/monaclick-home-combined.js'),
        ],
        '/finder/assets/css/theme.min.css' => [
            public_path('finder/assets/css/theme.min.css'),
        ],
        '/finder/assets/css/theme.rtl.min.css' => [
            public_path('finder/assets/css/theme.rtl.min.css'),
        ],
        '/finder/assets/icons/finder-icons.min.css' => [
            public_path('finder/assets/icons/finder-icons.min.css'),
        ],
        '/finder/assets/vendor/choices.js/public/assets/styles/choices.min.css' => [
            public_path('finder/assets/vendor/choices.js/public/assets/styles/choices.min.css'),
        ],
    ];
    foreach ($assetVersions as $webPath => $diskPath) {
        $candidates = is_array($diskPath) ? $diskPath : [$diskPath];

        // On shared hosting the web document root can be one level above Laravel's
        // public directory. Version the file the browser actually receives.
        $documentRoot = rtrim((string) request()->server('DOCUMENT_ROOT', ''), '/\\');
        if ($documentRoot !== '') {
            $documentRootAsset = $documentRoot . DIRECTORY_SEPARATOR
                . str_replace('/', DIRECTORY_SEPARATOR, ltrim($webPath, '/'));
            array_unshift($candidates, $documentRootAsset);
        }

        $resolved = null;
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && file_exists($candidate)) {
                $resolved = $candidate;
                break;
            }
        }
        if ($resolved === null) {
            continue;
        }
        $ver = (string) (filemtime($resolved) ?: time());
        $replacement = $webPath . '?v=' . $ver;

        // Replace existing versioned URLs first.
        $patternExisting = '~' . preg_quote($webPath, '~') . '\\?v=[^"\\\']+~';
        $html = preg_replace($patternExisting, $replacement, $html) ?? $html;

        // If the template didn't include ?v=..., append it to avoid stale browser caching.
        $patternMissing = '~' . preg_quote($webPath, '~') . '(?!\\?v=)~';
        $html = preg_replace($patternMissing, $replacement, $html) ?? $html;
    }
    if (!str_contains($html, 'window.__MC_AUTH__')) {
        $authFlag = Auth::check() ? 'true' : 'false';
        $csrf = csrf_token();
        $html = str_replace('</head>', "<script>window.__MC_AUTH__={$authFlag};window.__MC_CSRF__=" . json_encode($csrf) . ";</script></head>", $html);
    }
    $accountAuthPage = str_starts_with($file, 'account-') && Auth::check();
    $noFlashPage = $accountAuthPage || str_starts_with($file, 'add-');
    if ($noFlashPage) {
        $noFlashStyles = <<<'HTML'
<style id="account-noflash-style">
.content-wrapper{opacity:0;transition:opacity .12s ease;animation:mc-unhide 0s linear .25s forwards}
@keyframes mc-unhide{to{opacity:1}}
body.account-dom-ready .content-wrapper{opacity:1;animation:none}
</style>
HTML;
        $html = str_replace('</head>', $noFlashStyles . '</head>', $html);
    }

    $disableCustomizerThemePage = $accountAuthPage || str_starts_with($file, 'add-');
    if ($disableCustomizerThemePage) {
        $accountThemeReset = <<<'HTML'
<script id="mc-account-theme-reset">
(() => {
  const disableCustomizerTheme = () => {
    const customizerStyles = document.getElementById('customizer-styles');
    if (customizerStyles) customizerStyles.remove();

    const root = document.documentElement;
    [
      '--fn-primary',
      '--fn-primary-rgb',
      '--fn-primary-text-emphasis',
      '--fn-primary-bg-subtle',
      '--fn-primary-border-subtle',
      '--fn-success',
      '--fn-success-rgb',
      '--fn-success-text-emphasis',
      '--fn-success-bg-subtle',
      '--fn-success-border-subtle',
      '--fn-warning',
      '--fn-warning-rgb',
      '--fn-warning-text-emphasis',
      '--fn-warning-bg-subtle',
      '--fn-warning-border-subtle',
      '--fn-danger',
      '--fn-danger-rgb',
      '--fn-danger-text-emphasis',
      '--fn-danger-bg-subtle',
      '--fn-danger-border-subtle',
      '--fn-info',
      '--fn-info-rgb',
      '--fn-info-text-emphasis',
      '--fn-info-bg-subtle',
      '--fn-info-border-subtle',
      '--fn-border-width',
      '--fn-border-radius',
      '--fn-btn-bg',
      '--fn-btn-border-color',
      '--fn-btn-hover-bg',
      '--fn-btn-hover-border-color',
      '--fn-btn-active-bg',
      '--fn-btn-active-border-color',
      '--fn-btn-disabled-bg',
      '--fn-btn-disabled-border-color',
      '--fn-btn-color',
      '--fn-btn-disabled-color'
    ].forEach((name) => root.style.removeProperty(name));
  };

  disableCustomizerTheme();
})();
</script>
HTML;
        $html = str_replace('</head>', $accountThemeReset . '</head>', $html);
    }

    if (str_starts_with($file, 'single-entry-')) {
        $entryNoFlash = <<<'HTML'
<style id="entry-noflash-style">
body.monaclick-entry-shell[data-entry-ready="0"] .content-wrapper{opacity:0;visibility:hidden;transition:opacity .12s ease}
body.monaclick-entry-shell[data-entry-ready="1"] .content-wrapper{opacity:1;visibility:visible}
</style>
HTML;
        $html = str_replace('</head>', $entryNoFlash . '</head>', $html);

        $entryAuthPayload = [
            'userId' => Auth::id(),
            'isAuthenticated' => Auth::check(),
            'accountType' => (string) (Auth::user()?->account_type ?? ''),
            'userName' => trim((string) (
                ((Auth::user()?->account_type ?? 'business') === 'business'
                    ? (Auth::user()?->company_name ?? '')
                    : (((Auth::user()?->first_name ?? '') . ' ' . (Auth::user()?->last_name ?? '')))
                )
                ?: (Auth::user()?->name ?? '')
            )),
            'signInUrl' => '/signin',
            'csrfToken' => csrf_token(),
        ];
        $entryAuthJson = json_encode($entryAuthPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        $entryAuthScript = <<<'HTML'
<script>
window.MonaclickAuth = __ENTRY_AUTH_DATA__;
</script>
HTML;
        $entryAuthScript = str_replace('__ENTRY_AUTH_DATA__', $entryAuthJson ?: '{}', $entryAuthScript);
        $html = str_replace('</head>', $entryAuthScript . '</head>', $html);

        $entryReviewGuardScript = <<<'HTML'
<script>
(() => {
  const isAuthenticated = Boolean(window.MonaclickAuth && window.MonaclickAuth.isAuthenticated);
  const nextUrl = encodeURIComponent(`${window.location.pathname}${window.location.search}${window.location.hash || ''}`);
  const signInUrl = `/signin?next=${nextUrl}`;

  const reviewModal = document.getElementById('reviewForm');
  if (reviewModal) reviewModal.remove();

  document.querySelectorAll('[data-bs-target="#reviewForm"], [href="#reviewForm"]').forEach((trigger) => {
    if (isAuthenticated) {
      trigger.remove();
      return;
    }

    if (trigger.tagName === 'A') {
      trigger.setAttribute('href', signInUrl);
    } else {
      trigger.setAttribute('type', 'button');
      trigger.removeAttribute('data-bs-toggle');
      trigger.removeAttribute('data-bs-target');
      trigger.addEventListener('click', () => {
        window.location.href = signInUrl;
      });
    }

    const label = (trigger.textContent || '').trim().toLowerCase();
    if (label === 'add review' || label === 'leave a review') {
      trigger.textContent = 'Sign in to review';
    }
  });
})();
</script>
HTML;
        $html = str_replace('</body>', $entryReviewGuardScript . '</body>', $html);
    }

    // Events module removed: strip any leftover nav links in templates.
    $removeEventsScript = <<<'HTML'
<script id="mc-remove-events">
(() => {
  const selectors = [
    'a[href="/events"]',
    'a[href="/listings/events"]',
    'a[href^="/entry/events"]',
    'a[href*="listings-events.html"]',
    'a[href*="home-events.html"]',
    'a[href*="single-entry-events.html"]',
  ];
  selectors.forEach((sel) => {
    document.querySelectorAll(sel).forEach((a) => {
      const li = a.closest('li');
      (li || a).remove();
    });
  });
})();
</script>
HTML;
    $html = str_replace('</body>', $removeEventsScript . '</body>', $html);
    if (str_starts_with($file, 'add-')) {
        // Keep add flows on native selects to avoid double-rendered dropdown UI.
        $html = preg_replace(
            '~<script\s+src="(?:/finder/)?assets/vendor/choices\.js/public/assets/scripts/choices\.min\.js"></script>\s*~i',
            '',
            $html
        ) ?? $html;
    }
    if (Auth::check()) {
        $authNavScript = <<<'HTML'
<script>
(() => {
  // Signed-in state: remove top Account dropdown completely.
  document.querySelectorAll('.navbar-nav .nav-item.dropdown').forEach((item) => {
    const toggle = item.querySelector(':scope > .nav-link.dropdown-toggle');
    const text = (toggle?.textContent || '').trim().toLowerCase();
    if (text === 'account') item.remove();
  });

  const accountDropdowns = Array.from(document.querySelectorAll('.dropdown-menu'))
    .filter((menu) => Array.from(menu.querySelectorAll('a.dropdown-item')).some((a) => (a.textContent || '').trim() === 'Auth Pages'));

    accountDropdowns.forEach((menu) => {
    const authToggle = Array.from(menu.querySelectorAll('a.dropdown-item')).find((a) => (a.textContent || '').trim() === 'Auth Pages');
    const authLi = authToggle ? authToggle.closest('li') : null;
    if (authLi) authLi.remove();

    const hasProfile = Array.from(menu.querySelectorAll('a.dropdown-item')).some((a) => /my profile/i.test(a.textContent || ''));
    if (!hasProfile) {
      const divider = document.createElement('li');
      divider.innerHTML = '<hr class="dropdown-divider">';
      menu.appendChild(divider);
      menu.insertAdjacentHTML(
        'beforeend',
        '<li><a class="dropdown-item" href="/account/profile">My Profile</a></li>'
      );
    }

    const hasSignOut = Array.from(menu.querySelectorAll('a.dropdown-item')).some((a) => /sign out/i.test(a.textContent || ''));
    if (!hasSignOut) {
      menu.insertAdjacentHTML(
        'beforeend',
        '<li><a class="dropdown-item" href="/signout">Sign out</a></li>'
      );
    }
    });

  // Sidebar / account area: ensure sign-out points to real logout route
  document.querySelectorAll('a.list-group-item, a.dropdown-item, a').forEach((link) => {
    const text = (link.textContent || '').trim().toLowerCase();
    if (text === 'sign out' || text.includes('sign out of all sessions')) {
      link.setAttribute('href', '/signout');
      link.setAttribute('data-mc-no-loader', '1');
    }
  });
})();
</script>
HTML;
        $html = str_replace('</body>', $authNavScript . '</body>', $html);

        if (str_starts_with($file, 'account-')) {
            $authAvatar = (string) (Auth::user()?->avatar_url ?? '');
            $authAvatarJson = json_encode($authAvatar, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
            $avatarAndDefaultsStripScript = <<<'HTML'
<script>
(() => {
  const defaultAvatar = 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 120 120%22%3E%3Ccircle cx=%2260%22 cy=%2260%22 r=%2260%22 fill=%22%23e9ecef%22/%3E%3Cpath d=%22M60 62c-14 0-25-11-25-25s11-25 25-25 25 11 25 25-11 25-25 25zm0 8c22 0 40 12 40 28v10H20V98c0-16 18-28 40-28z%22 fill=%22%239aa4b2%22/%3E%3C/svg%3E';
  const authAvatar = __AUTH_AVATAR__;
  const avatar = authAvatar || defaultAvatar;
  document.querySelectorAll('#accountSidebar img, #personal-info img, .offcanvas-body img.rounded-circle, img[src*="/finder/assets/img/account/avatar"], img[src*="/finder/assets/img/avatar"]').forEach((img) => {
    img.src = avatar;
  });

  // If listing image is still template default, replace with neutral placeholder.
  const carTemplateImages = [
    '/finder/assets/img/listings/cars/grid/04.jpg',
    '/finder/assets/img/listings/cars/grid/03.jpg',
    '/finder/assets/img/listings/cars/grid/02.jpg',
    '/finder/assets/img/listings/cars/grid/01.jpg'
  ];
  const neutral = 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 640 427%22%3E%3Crect width=%22640%22 height=%22427%22 fill=%22%23eef2f6%22/%3E%3Cpath d=%22M183 260h274l35 47H148l35-47zm74-57h126l26 39H231l26-39z%22 fill=%22%23c6d0db%22/%3E%3Ccircle cx=%22223%22 cy=%22322%22 r=%2228%22 fill=%22%2398a6b6%22/%3E%3Ccircle cx=%22417%22 cy=%22322%22 r=%2228%22 fill=%22%2398a6b6%22/%3E%3C/svg%3E';
  document.querySelectorAll('article img, .card img').forEach((img) => {
    const src = img.getAttribute('src') || '';
    if (carTemplateImages.some((p) => src.includes(p))) {
      img.src = neutral;
    }
  });
})();
</script>
HTML;
            $avatarAndDefaultsStripScript = str_replace('__AUTH_AVATAR__', $authAvatarJson ?: "''", $avatarAndDefaultsStripScript);
            $html = str_replace('</body>', $avatarAndDefaultsStripScript . '</body>', $html);
        }

        if (str_starts_with($file, 'account-')) {
            $accountLogoutFallback = <<<'HTML'
<script>
(() => {
  if (document.body?.dataset.mcAccountLogoutReady === '1') return;
  if (document.body) document.body.dataset.mcAccountLogoutReady = '1';

  let modal = document.getElementById('globalLogoutModal');
  let confirmAction = document.getElementById('logoutConfirmAction');

  if (!modal) {
    modal = document.createElement('div');
    modal.id = 'globalLogoutModal';
    modal.style.cssText = 'display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:99999;align-items:center;justify-content:center;padding:16px;';
    modal.innerHTML = `
      <div style="width:min(520px,95vw);background:#fff;border-radius:14px;box-shadow:0 12px 40px rgba(0,0,0,.2);padding:22px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
          <h5 style="margin:0;">Confirm logout</h5>
          <button type="button" data-logout-close style="border:0;background:transparent;font-size:28px;line-height:1;cursor:pointer;">x</button>
        </div>
        <p style="margin:0 0 18px 0;color:#5b6475;">Are you sure you want to log out?</p>
        <div style="display:flex;justify-content:flex-end;gap:10px;">
          <button type="button" class="btn btn-outline-secondary" data-logout-close>Cancel</button>
          <a href="/signout" class="btn btn-primary" id="logoutConfirmAction" data-mc-no-loader="1">Yes, log out</a>
        </div>
      </div>
    `;
    document.body.appendChild(modal);
    confirmAction = document.getElementById('logoutConfirmAction');
  }

  const open = () => {
    if (modal) modal.style.display = 'flex';
  };
  const close = () => {
    if (modal) modal.style.display = 'none';
  };

  document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-logout-trigger]');
    if (trigger) {
      event.preventDefault();
      event.stopPropagation();
      event.stopImmediatePropagation();
      const targetHref = trigger.getAttribute('href') || '/signout';
      if (confirmAction) confirmAction.setAttribute('href', targetHref);
      open();
      return;
    }

    const confirmLink = event.target.closest('#logoutConfirmAction');
    if (confirmLink) {
      event.preventDefault();
      const href = confirmLink.getAttribute('href') || '/signout';
      close();
      if (typeof window.__MC_HIDE_PAGE_LOADER__ === 'function') {
        window.__MC_HIDE_PAGE_LOADER__();
      }
      window.location.assign(href);
      return;
    }

    if (event.target.closest('[data-logout-close]') || event.target === modal) {
      event.preventDefault();
      close();
    }
  }, true);

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') close();
  });
})();
</script>
HTML;
            $html = str_replace('</body>', $accountLogoutFallback . '</body>', $html);
        }
    }
    if (! Auth::check()) {
        $guestNavScript = <<<'HTML'
<script>
(() => {
  const accountDropdowns = Array.from(document.querySelectorAll('.dropdown-menu'))
    .filter((menu) => Array.from(menu.querySelectorAll('a.dropdown-item')).some((a) => (a.textContent || '').trim() === 'Auth Pages'));

  accountDropdowns.forEach((menu) => {
    menu.innerHTML = `
      <li><a class="dropdown-item" href="/signin">Sign In</a></li>
      <li><a class="dropdown-item" href="/signup">Sign Up</a></li>
    `;
  });
})();
</script>
HTML;
        $html = str_replace('</body>', $guestNavScript . '</body>', $html);
    }

    if (str_starts_with($file, 'account-') && Auth::check()) {
        $user = Auth::user();
        $name = htmlspecialchars((string) ($user?->name ?? 'Monaclick User'), ENT_QUOTES, 'UTF-8');
        $email = htmlspecialchars((string) ($user?->email ?? ''), ENT_QUOTES, 'UTF-8');
        $avatarUrl = (string) ($user?->avatar_url ?? '');
        if ($avatarUrl !== '') {
            $avatarEsc = htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8');
            $html = preg_replace(
                '~src="(?:/finder/assets/img/account/avatar(?:-lg)?\.jpg|/finder/assets/img/avatar[^"]*)"~',
                'src="' . $avatarEsc . '"',
                $html
            ) ?? $html;
        }
        $namePlaceholders = [
            'Jerome Bell',
            'Michael Williams',
        ];
        $emailPlaceholders = [
            'jerome.bell@example.com',
            'm.williams@example.com',
        ];
        $html = str_replace($namePlaceholders, $name, $html);
        $html = str_replace($emailPlaceholders, $email, $html);

        $userEmail = strtolower(trim((string) ($user?->email ?? '')));
        $userListings = $user?->id
            ? Listing::query()
                ->with(['city', 'propertyDetail', 'contractorDetail'])
                ->where('user_id', $user->id)
                ->latest('id')
                ->take(50)
                ->get()
            : collect();
        $directListingIds = $userListings->pluck('id');
        $recoveredListingIds = collect();

        if ($user && $userEmail !== '') {
            if (Schema::hasTable('property_details') && Schema::hasColumn('property_details', 'wizard_data')) {
                $recoveredListingIds = $recoveredListingIds->merge(
                    DB::table('property_details')
                        ->join('listings', 'listings.id', '=', 'property_details.listing_id')
                        ->whereNull('listings.user_id')
                        ->where(function ($query) use ($userEmail) {
                            $query
                                ->whereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(property_details.wizard_data, '$.email'))) = ?", [$userEmail])
                                ->orWhereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(property_details.wizard_data, '$.contact_email'))) = ?", [$userEmail]);
                        })
                        ->pluck('listings.id')
                );
            }

            if (Schema::hasTable('car_details') && Schema::hasColumn('car_details', 'contact_email')) {
                $recoveredListingIds = $recoveredListingIds->merge(
                    DB::table('car_details')
                        ->join('listings', 'listings.id', '=', 'car_details.listing_id')
                        ->whereNull('listings.user_id')
                        ->whereRaw('LOWER(TRIM(car_details.contact_email)) = ?', [$userEmail])
                        ->pluck('listings.id')
                );
            }

            $recoveredListingIds = $recoveredListingIds->merge(
                Listing::query()
                    ->whereNull('user_id')
                    ->where('module', 'restaurants')
                    ->whereRaw('LOWER(excerpt) LIKE ?', ['%"email":"' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $userEmail) . '"%'])
                    ->pluck('id')
            );
            $recoveredListingIds = $recoveredListingIds->unique()->values();
        }

        if ($recoveredListingIds->isNotEmpty()) {
            $recoveredListings = Listing::query()
                ->with(['city', 'propertyDetail', 'contractorDetail'])
                ->whereIn('id', $recoveredListingIds)
                ->latest('id')
                ->get();
            $userListings = $userListings
                ->concat($recoveredListings)
                ->unique('id')
                ->sortByDesc('id')
                ->take(50)
                ->values();
        }

        $listingCount = $userListings->count();
        $hasReviewsTable = DB::getSchemaBuilder()->hasTable('reviews');
        $hasFavoritesTable = DB::getSchemaBuilder()->hasTable('favorites');
        $reviewCount = $hasReviewsTable ? DB::table('reviews')->where('user_id', $user?->id)->count() : 0;
        $favoriteCount = $hasFavoritesTable ? DB::table('favorites')->where('user_id', $user?->id)->count() : 0;
        $isNewUser = $listingCount === 0 && $reviewCount === 0 && $favoriteCount === 0;

        if (in_array($file, ['account-profile.html', 'account-listings.html', 'account-payment.html', 'account-subscriptions.html'], true)) {
            $profileName = trim((string) (($user?->first_name ?? '') . ' ' . ($user?->last_name ?? '')));
            if ($profileName === '') {
                $profileName = (string) ($user?->name ?? 'User');
            }
            $profilePayload = [
                'first_name' => (string) ($user?->first_name ?? ''),
                'last_name' => (string) ($user?->last_name ?? ''),
                'name' => $profileName,
                'email' => (string) ($user?->email ?? ''),
                'account_type' => (string) ($user?->account_type ?? 'business'),
                'company_name' => (string) ($user?->company_name ?? ''),
                'phone' => $formatUsPhoneForDisplay($user?->phone),
                'birth_date' => (string) ($user?->birth_date ?? ''),
                'language' => (string) ($user?->language ?? ''),
                'address' => (string) ($user?->address ?? ''),
                'bio' => (string) ($user?->bio ?? ''),
                'avatar' => (string) ($user?->avatar_url ?? ''),
            ];
            $listingsPayload = $userListings->map(function (Listing $listing) {
                $promotionPackage = '';
                $promotionPackageLabel = '';
                $promotionPackagePrice = '';
                $selectedServicesDetails = [];
                if ($listing->module === 'real-estate') {
                    $wizardData = is_array($listing->propertyDetail?->wizard_data) ? $listing->propertyDetail->wizard_data : [];
                    $promotionPackage = strtolower(trim((string) ($wizardData['promotion_package'] ?? $wizardData['package'] ?? '')));
                    $promotionPackageLabel = match ($promotionPackage) {
                        'easy-start' => 'Easy Start',
                        'fast-sale' => 'Fast Sale',
                        'turbo-boost' => 'Turbo Boost',
                        default => '',
                    };
                    $promotionPackagePrice = match ($promotionPackage) {
                        'easy-start' => '$25 / month',
                        'fast-sale' => '$49 / month',
                        'turbo-boost' => '$70 / month',
                        default => '',
                    };
                    if (!empty($wizardData['service_certify'])) {
                        $selectedServicesDetails[] = [
                            'label' => 'Check and certify my ad by Monaclick experts',
                            'price' => '$35',
                        ];
                    }
                    if (!empty($wizardData['service_lifts'])) {
                        $selectedServicesDetails[] = [
                            'label' => '10 lifts to the top of the list (daily, 7 days)',
                            'price' => '$29 / month',
                        ];
                    }
                    if (!empty($wizardData['service_analytics'])) {
                        $selectedServicesDetails[] = [
                            'label' => 'Detailed user engagement analytics',
                            'price' => '$15 / month',
                        ];
                    }
                } elseif ($listing->module === 'contractors') {
                    $featureTokens = collect(is_array($listing->features) ? $listing->features : [])
                        ->map(fn ($value) => trim((string) $value))
                        ->filter();
                    $promotionPackage = strtolower((string) ($featureTokens->first(fn ($token) => str_starts_with($token, 'promo-package:')) ?? ''));
                    if ($promotionPackage !== '') {
                        $promotionPackage = substr($promotionPackage, strlen('promo-package:'));
                    }
                    $promotionPackageLabel = match ($promotionPackage) {
                        'easy-start' => 'Easy Start',
                        'fast-sale' => 'Fast Sale',
                        'turbo-boost' => 'Turbo Boost',
                        default => '',
                    };
                    $promotionPackagePrice = match ($promotionPackage) {
                        'easy-start' => '$25 / month',
                        'fast-sale' => '$49 / month',
                        'turbo-boost' => '$70 / month',
                        default => '',
                    };
                    if ($featureTokens->contains('promo-service:certify')) {
                        $selectedServicesDetails[] = [
                            'label' => 'Check and certify my business by Monaclick experts',
                            'price' => '$35',
                        ];
                    }
                    if ($featureTokens->contains('promo-service:lifts')) {
                        $selectedServicesDetails[] = [
                            'label' => '10 lifts to the top of the list (daily, 7 days)',
                            'price' => '$29 / month',
                        ];
                    }
                    if ($featureTokens->contains('promo-service:analytics')) {
                        $selectedServicesDetails[] = [
                            'label' => 'Detailed user engagement analytics',
                            'price' => '$15 / month',
                        ];
                    }
                } elseif ($listing->module === 'cars') {
                    $featureTokens = collect(is_array($listing->features) ? $listing->features : [])
                        ->map(fn ($value) => trim((string) $value))
                        ->filter();
                    $promotionPackage = strtolower((string) ($featureTokens->first(fn ($token) => str_starts_with($token, 'promo-package:')) ?? ''));
                    if ($promotionPackage !== '') {
                        $promotionPackage = substr($promotionPackage, strlen('promo-package:'));
                    }
                    $promotionPackageLabel = match ($promotionPackage) {
                        'easy-start' => 'Easy Start',
                        'fast-sale' => 'Fast Sale',
                        'turbo-boost' => 'Turbo Boost',
                        default => '',
                    };
                    $promotionPackagePrice = match ($promotionPackage) {
                        'easy-start' => '$25 / month',
                        'fast-sale' => '$49 / month',
                        'turbo-boost' => '$70 / month',
                        default => '',
                    };
                    if ($featureTokens->contains('promo-service:certify')) {
                        $selectedServicesDetails[] = [
                            'label' => 'Check and certify my ad by Monaclick experts',
                            'price' => '$35',
                        ];
                    }
                    if ($featureTokens->contains('promo-service:lifts')) {
                        $selectedServicesDetails[] = [
                            'label' => '10 lifts to the top of the list (daily, 7 days)',
                            'price' => '$29 / month',
                        ];
                    }
                    if ($featureTokens->contains('promo-service:analytics')) {
                        $selectedServicesDetails[] = [
                            'label' => 'Detailed user engagement analytics',
                            'price' => '$15 / month',
                        ];
                    }
                } elseif ($listing->module === 'restaurants') {
                    $featureTokens = collect(is_array($listing->features) ? $listing->features : [])
                        ->map(fn ($value) => trim((string) $value))
                        ->filter();
                    $promotionPackage = strtolower((string) ($featureTokens->first(fn ($token) => str_starts_with($token, 'promo-package:')) ?? ''));
                    if ($promotionPackage !== '') {
                        $promotionPackage = substr($promotionPackage, strlen('promo-package:'));
                    }
                    $promotionPackageLabel = match ($promotionPackage) {
                        'easy-start' => 'Easy Start',
                        'fast-sale' => 'Fast Sale',
                        'turbo-boost' => 'Turbo Boost',
                        default => '',
                    };
                    $promotionPackagePrice = match ($promotionPackage) {
                        'easy-start' => '$25 / month',
                        'fast-sale' => '$49 / month',
                        'turbo-boost' => '$70 / month',
                        default => '',
                    };
                    if ($featureTokens->contains('promo-service:certify')) {
                        $selectedServicesDetails[] = [
                            'label' => 'Check and certify my restaurant by Monaclick experts',
                            'price' => '$35',
                        ];
                    }
                    if ($featureTokens->contains('promo-service:lifts')) {
                        $selectedServicesDetails[] = [
                            'label' => '10 lifts to the top of the list (daily, 7 days)',
                            'price' => '$29 / month',
                        ];
                    }
                    if ($featureTokens->contains('promo-service:analytics')) {
                        $selectedServicesDetails[] = [
                            'label' => 'Detailed user engagement analytics',
                            'price' => '$15 / month',
                        ];
                    }
                }
                return [
                    'id' => $listing->id,
                    'title' => (string) $listing->title,
                    'slug' => (string) $listing->slug,
                    'module' => (string) $listing->module,
                    'module_label' => Listing::MODULE_OPTIONS[$listing->module] ?? ucfirst((string) $listing->module),
                    'price' => (string) ($listing->display_price ?: ($listing->price ?? '')),
                    'city' => (string) ($listing->city?->name ?? ''),
                    'image' => (string) $listing->image_url,
                    'status' => (string) $listing->status,
                    'admin_status' => (string) ($listing->admin_status ?? ''),
                    'published_at' => optional($listing->published_at)->toIso8601String() ?? '',
                    'created_at' => optional($listing->created_at)->format('d/m/Y') ?? '',
                    'promotion_package' => $promotionPackage,
                    'promotion_package_label' => $promotionPackageLabel,
                    'promotion_package_price' => $promotionPackagePrice,
                    'selected_services_details' => $selectedServicesDetails,
                ];
            })->values()->all();
            $profileJson = json_encode($profilePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
            $listingsJson = json_encode($listingsPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

            $accountListingsSyncScript = <<<'HTML'
<script>
(() => {
  const profile = __USER_PROFILE__;
  const listings = __USER_LISTINGS__;
  if (!Array.isArray(listings) || !profile) return;
  const csrfToken = '__SCRIPT_CSRF__';

  const esc = (v) => String(v || '').replace(/[&<>"']/g, (m) => (
    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m] || m
  ));
  const avatar = 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 120 120%22%3E%3Ccircle cx=%2260%22 cy=%2260%22 r=%2260%22 fill=%22%23e9ecef%22/%3E%3Cpath d=%22M60 62c-14 0-25-11-25-25s11-25 25-25 25 11 25 25-11 25-25 25zm0 8c22 0 40 12 40 28v10H20V98c0-16 18-28 40-28z%22 fill=%22%239aa4b2%22/%3E%3C/svg%3E';
  const isDraftItem = (item) => String(item?.status || '').trim().toLowerCase() === 'draft';
  const editHref = (item) => {
    if (item.module === 'cars') return `/sell-car?edit=${encodeURIComponent(item.id)}`;
    if (item.module === 'real-estate') return `/add-property?edit=${encodeURIComponent(item.id)}`;
    if (item.module === 'contractors') return `/add-contractor?edit=${encodeURIComponent(item.id)}`;
    if (item.module === 'restaurants') return `/add-restaurant?edit=${encodeURIComponent(item.id)}`;
    return '';
  };
  const viewHref = (item) => (
    isDraftItem(item)
      ? ''
      : (
        item.slug
          ? `/entry/${encodeURIComponent(item.module)}?slug=${encodeURIComponent(item.slug)}`
          : `/listings/${encodeURIComponent(item.module)}?q=${encodeURIComponent(item.title)}`
      )
  );
  const deleteForm = (item) => `
    <form method="post" action="/account/listings/delete" class="d-inline">
      <input type="hidden" name="_token" value="${esc(csrfToken)}">
      <input type="hidden" name="listing_id" value="${esc(item.id)}">
      <button type="submit" class="dropdown-item text-start" style="color:#f03d3d" onclick="return confirm('Delete this listing?')">
        <i class="fi-trash opacity-75 me-2"></i>
        Delete
      </button>
    </form>
  `;

  const promotionSummary = (item) => {
    if (item.module !== 'real-estate') return '';
    const packageLabel = String(item?.promotion_package_label || '').trim();
    const packagePrice = String(item?.promotion_package_price || '').trim();
    const serviceDetails = Array.isArray(item?.selected_services_details)
      ? item.selected_services_details
        .map((service) => ({
          label: String(service?.label || '').trim(),
          price: String(service?.price || '').trim(),
        }))
        .filter((service) => service.label)
      : [];
    if (!packageLabel && !serviceDetails.length) return '';

    return `
      <div class="border rounded p-3 mt-3 bg-body-tertiary">
        <div class="small text-body-secondary mb-1">Ad Promotion</div>
        ${packageLabel ? `<div class="fw-semibold">${esc(packageLabel)}${packagePrice ? ` <span class="text-body-secondary fw-normal">(${esc(packagePrice)})</span>` : ''}</div>` : ''}
        ${serviceDetails.length ? `
          <div class="small text-body-secondary mt-2 mb-1">Other services</div>
          <div class="d-flex flex-column gap-1">
            ${serviceDetails.map((service) => `
              <div class="small d-flex justify-content-between gap-2">
                <span>${esc(service.label)}</span>
                <span class="text-body-secondary">${esc(service.price)}</span>
              </div>
            `).join('')}
          </div>
        ` : ''}
      </div>
    `;
  };

  const card = (item, compact = false) => `
    <article class="card border-0 shadow-sm mb-3">
      <div class="row g-0">
        <div class="${compact ? 'col-sm-4' : 'col-md-4'}">
          <img class="w-100 h-100 object-fit-cover rounded-start" style="min-height: 180px" src="${esc(item.image)}" alt="${esc(item.title)}">
        </div>
        <div class="${compact ? 'col-sm-8' : 'col-md-8'}">
          <div class="card-body d-flex flex-column h-100">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <span class="badge ${item.status === 'draft' ? 'text-bg-secondary' : 'text-bg-success'}">${esc(item.status)}</span>
              <small class="text-body-secondary">${esc(item.created_at)}</small>
            </div>
            <h3 class="h6 mb-1">${esc(item.title)}</h3>
            <div class="text-body-secondary small mb-2">${esc(item.module_label)}${item.city ? ' â€¢ ' + esc(item.city) : ''}</div>
            <div class="fw-semibold mb-3">${esc(item.price || 'Price on request')}</div>
            ${promotionSummary(item)}
            <div class="mt-auto d-flex gap-2 flex-wrap">
              ${viewHref(item) ? `<a class="btn btn-sm btn-outline-secondary" href="${viewHref(item)}">View</a>` : `<button type="button" class="btn btn-sm btn-outline-secondary" disabled>Draft only</button>`}
              ${editHref(item) ? `<a class="btn btn-sm btn-primary" href="${editHref(item)}">Edit</a>` : ''}
              ${deleteForm(item)}
            </div>
          </div>
        </div>
      </div>
    </article>
  `;

  const normalizeListingStatus = (item) => {
    const raw = String(item?.status || '').trim().toLowerCase();
    const admin = String(item?.admin_status || '').trim().toLowerCase();
    const hasPublishedAt = !!String(item?.published_at || '').trim();
    if (raw === 'draft') return 'draft';
    if (raw === 'archived') return 'archived';
    if (hasPublishedAt) return 'published';
    if (admin === 'published' || admin === 'approved' || admin === 'live' || admin === 'active') return 'published';
    if (raw === 'published' || raw === 'active' || raw === 'live') return 'published';
    return 'published';
  };

  const emptyStateCard = (title, body) => `
    <div class="card border-0 bg-body-tertiary">
      <div class="card-body py-5 text-center">
        <h3 class="h5 mb-2">${title}</h3>
        <p class="text-body-secondary mb-4">${body}</p>
        <a class="btn btn-primary" href="/add-listing">Add new listing</a>
      </div>
    </div>
  `;

  const publishForm = (item, label = 'Finish and publish') => `
    <form method="post" action="/account/listings/publish" class="d-inline">
      <input type="hidden" name="_token" value="${esc(csrfToken)}">
      <input type="hidden" name="listing_id" value="${esc(item.id)}">
      <button type="submit" class="btn btn-outline-dark">${label}</button>
    </form>
  `;

  const finderAccountCard = (item) => {
    const isDraft = normalizeListingStatus(item) === 'draft';
    const metaLine = [item.module_label, item.city].filter(Boolean).join(' â€¢ ');
    return `
      <div class="d-sm-flex align-items-center">
        <div class="d-inline-flex position-relative z-2 pt-1 pb-2 ps-2 p-sm-0 ms-2 ms-sm-0 me-sm-2">
          <div class="form-check position-relative z-1 fs-lg m-0">
            <input type="checkbox" class="form-check-input">
          </div>
          <span class="position-absolute top-0 start-0 w-100 h-100 bg-body border rounded d-sm-none"></span>
        </div>
        <article class="card w-100">
          <div class="d-sm-none" style="margin-top: -44px"></div>
          <div class="row g-0">
            <div class="col-sm-4 col-md-3 rounded overflow-hidden pb-2 pb-sm-0 pe-sm-2">
              <div class="position-relative d-flex h-100 bg-body-tertiary" style="min-height: 174px">
                <img src="${esc(item.image)}" class="position-absolute top-0 start-0 w-100 h-100 object-fit-cover" alt="${esc(item.title)}">
                <div class="ratio d-none d-sm-block" style="--fn-aspect-ratio: calc(180 / 240 * 100%)"></div>
                <div class="ratio ratio-16x9 d-sm-none"></div>
              </div>
            </div>
            <div class="col-sm-8 col-md-9 align-self-center">
              <div class="card-body d-flex justify-content-between p-3 py-sm-4 ps-sm-2 ps-md-3 pe-md-4 mt-n1 mt-sm-0">
                <div class="position-relative pe-3">
                  <span class="badge text-body-emphasis bg-body-secondary mb-2">${esc(item.module_label || (isDraft ? 'Draft' : 'Published'))}</span>
                  ${String(item?.promotion_package_label || '').trim() ? `<span class="badge text-bg-info ms-2 mb-2">${esc(String(item.promotion_package_label).trim())} Package</span>` : ''}
                  <div class="h5 mb-2">${esc(item.price || 'Price on request')}</div>
                  ${viewHref(item) ? `<a class="stretched-link d-block fs-sm text-body text-decoration-none mb-2" href="${viewHref(item)}">${esc(item.title)}</a>` : `<div class="d-block fs-sm text-body text-decoration-none mb-2">${esc(item.title)}</div>`}
                  <div class="h6 fs-sm mb-0">${esc(metaLine || 'No additional details')}</div>
                  ${String(item?.promotion_package_label || '').trim() ? `<div class="fs-xs text-body-secondary mt-2">Promotion package: ${esc(String(item.promotion_package_label).trim())}</div>` : ''}
                </div>
                <div class="text-end">
                  <div class="fs-xs text-body-secondary mb-3">Created: ${esc(item.created_at)}</div>
                  <div class="d-flex justify-content-end gap-2 mb-3 flex-wrap">
                    ${isDraft ? publishForm(item) : `<a class="btn btn-outline-secondary" href="${viewHref(item)}">View</a>`}
                    <div class="dropdown">
                      <button type="button" class="btn btn-icon btn-outline-secondary" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Settings">
                        <i class="fi-settings fs-base"></i>
                      </button>
                      <ul class="dropdown-menu dropdown-menu-end">
                        ${editHref(item) ? `
                          <li>
                            <a class="dropdown-item" href="${editHref(item)}">
                              <i class="fi-edit opacity-75 me-2"></i>
                              Edit
                            </a>
                          </li>
                        ` : ''}
                        ${viewHref(item) ? `
                          <li>
                            <a class="dropdown-item" href="${viewHref(item)}">
                              <i class="fi-eye opacity-75 me-2"></i>
                              View
                            </a>
                          </li>
                        ` : ''}
                        <li>
                          ${deleteForm(item)}
                        </li>
                      </ul>
                    </div>
                  </div>
                  <ul class="list-unstyled flex-row flex-wrap justify-content-end fs-sm mb-0">
                    <li class="d-flex align-items-center me-2 me-md-3">
                      <i class="fi-eye fs-base me-1"></i>
                      0
                    </li>
                    <li class="d-flex align-items-center me-2 me-md-3">
                      <i class="fi-heart fs-base me-1"></i>
                      0
                    </li>
                    <li class="d-flex align-items-center">
                      <i class="fi-phone-incoming fs-base me-1"></i>
                      0
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </article>
      </div>
    `;
  };

  if (location.pathname === '/account/profile') {
    const root = document.querySelector('.col-lg-9');
    if (!root) return;
    const avatarSrc = profile.avatar ? profile.avatar : avatar;
    root.innerHTML = `
      <div class="d-flex flex-wrap justify-content-end gap-2 pb-2 pb-lg-3">
        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#mcEditProfileModal">Edit profile</button>
      </div>
      <section class="pb-5 mb-md-3">
        <div class="d-flex align-items-start gap-3 mb-4">
          <img src="${esc(avatarSrc)}" alt="Avatar" width="96" height="96" class="rounded-circle border" data-mc-avatar>
          <div>
            <h2 class="h4 text-dark fw-bold mb-2">${esc(profile.name)}</h2>
            <div class="text-body-secondary">${esc(profile.email)}</div>
          </div>
        </div>
        <div class="vstack gap-3">
          ${profile.account_type === 'business' && profile.company_name ? `
            <div>
              <div class="fs-sm text-dark fw-bold mb-1">Company name</div>
              <div>${esc(profile.company_name)}</div>
            </div>
          ` : ''}
          ${profile.phone ? `
            <div>
              <div class="fs-sm text-dark fw-bold mb-1">Phone</div>
              <div>${esc(profile.phone)}</div>
            </div>
          ` : ''}
          ${profile.birth_date ? `
            <div>
              <div class="fs-sm text-dark fw-bold mb-1">Date of birth</div>
              <div>${esc(profile.birth_date)}</div>
            </div>
          ` : ''}
          ${profile.language ? `
            <div>
              <div class="fs-sm text-dark fw-bold mb-1">Language</div>
              <div>${esc(profile.language)}</div>
            </div>
          ` : ''}
          ${profile.address ? `
            <div>
              <div class="fs-sm text-dark fw-bold mb-1">Address</div>
              <div>${esc(profile.address)}</div>
            </div>
          ` : ''}
          ${profile.bio ? `
            <div>
              <div class="fs-sm text-dark fw-bold mb-1">About</div>
              <div>${esc(profile.bio)}</div>
            </div>
          ` : ''}
        </div>
      </section>

      <div class="modal fade" id="mcEditProfileModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Edit profile</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="/account/settings" enctype="multipart/form-data" data-mc-no-loader="1">
              <div class="modal-body">
                <input type="hidden" name="_token" value="${esc(csrfToken)}">
                <div class="row g-3">
                  ${profile.account_type === 'business' ? `
                    <div class="col-12">
                      <label class="form-label" for="mcCompanyName">Company name</label>
                      <input class="form-control" id="mcCompanyName" name="company_name" value="${esc(profile.company_name || '')}">
                    </div>
                  ` : ''}
                  <div class="col-md-6">
                    <label class="form-label" for="mcFirstName">First name</label>
                    <input class="form-control" id="mcFirstName" name="first_name" value="${esc(profile.first_name || '')}">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="mcLastName">Last name</label>
                    <input class="form-control" id="mcLastName" name="last_name" value="${esc(profile.last_name || '')}">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="mcEmail">Email</label>
                    <input class="form-control" type="email" id="mcEmail" name="email" value="${esc(profile.email || '')}" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="mcPhone">Phone</label>
                      <input class="form-control" id="mcPhone" name="phone" value="${esc(profile.phone || '')}" inputmode="tel" maxlength="14" placeholder="(555) 123-4567">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="mcBirthDate">Date of birth</label>
                    <div class="position-relative">
                      <input type="text" class="form-control form-icon-end" id="mcBirthDate" name="birth_date" value="${esc(profile.birth_date || '')}" data-datepicker='{"dateFormat":"Y-m-d"}' placeholder="YYYY-MM-DD">
                      <i class="fi-calendar fs-lg position-absolute top-50 end-0 translate-middle-y me-3"></i>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="mcLanguage">Language</label>
                    <select class="form-select" id="mcLanguage" name="language" data-select='{"removeItemButton": false}' aria-label="Language select">
                      <option value="">Select language</option>
                      <option value="English">English</option>
                      <option value="Spanish">Spanish</option>
                      <option value="French">French</option>
                      <option value="German">German</option>
                      <option value="Italian">Italian</option>
                    </select>
                  </div>
                  <div class="col-12">
                    <label class="form-label" for="mcAddress">Address</label>
                    <input class="form-control" id="mcAddress" name="address" value="${esc(profile.address || '')}">
                  </div>
                  <div class="col-12">
                    <label class="form-label" for="mcBio">About</label>
                    <textarea class="form-control" id="mcBio" name="bio" rows="3">${esc(profile.bio || '')}</textarea>
                  </div>
                  <div class="col-12">
                    <label class="form-label" for="mcProfileAvatar">Profile photo</label>
                    <input class="form-control" type="file" id="mcProfileAvatar" name="avatar" accept="image/*">
                  </div>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save changes</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    `;

    const avatarInput = root.querySelector('#mcProfileAvatar');
    const avatarImg = root.querySelector('img[data-mc-avatar]');
    const langSelect = root.querySelector('#mcLanguage');
    const profileForm = root.querySelector('#mcEditProfileModal form');
    const profileModalEl = root.querySelector('#mcEditProfileModal');
    const profileModal = window.bootstrap?.Modal && profileModalEl
      ? window.bootstrap.Modal.getOrCreateInstance(profileModalEl)
      : null;
    if (langSelect) langSelect.value = profile.language || '';

    root.querySelectorAll('[data-datepicker]').forEach((input) => {
      if (input && input._flatpickr) return;
      if (typeof window.flatpickr !== 'function') return;
      let config = {};
      try {
        const raw = input.getAttribute('data-datepicker');
        config = raw ? JSON.parse(raw) : {};
      } catch (e) {
        config = {};
      }
      try {
        window.flatpickr(input, config);
      } catch (e) {
        // ignore
      }
    });

    root.querySelectorAll('select[data-select]').forEach((select) => {
      if (!select || select.__mcChoices) return;
      if (typeof window.Choices !== 'function') return;
      let config = {};
      try {
        const raw = select.getAttribute('data-select');
        config = raw ? JSON.parse(raw) : {};
      } catch (e) {
        config = {};
      }
      try {
        select.__mcChoices = new window.Choices(select, config);
      } catch (e) {
        // ignore
      }
    });

    if (avatarInput && avatarImg) {
      avatarInput.addEventListener('change', () => {
        const file = avatarInput.files && avatarInput.files[0];
        if (!file) return;
        avatarImg.src = URL.createObjectURL(file);
      });
    }

    if (profileForm) {
      profileForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        const submitBtn = profileForm.querySelector('button[type="submit"]');
        const originalLabel = submitBtn ? submitBtn.textContent : '';
        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.textContent = 'Saving...';
        }

        try {
          const formData = new FormData(profileForm);
          const response = await fetch(profileForm.action, {
            method: 'POST',
            body: formData,
            headers: {
              'X-Requested-With': 'XMLHttpRequest',
              'Accept': 'application/json',
            },
            credentials: 'same-origin',
          });

          const payload = await response.json().catch(() => ({}));
          if (!response.ok || !payload?.ok || !payload?.profile) {
            throw new Error('Unable to save profile');
          }

          Object.assign(profile, payload.profile);

          const nameNode = root.querySelector('h2.h4');
          if (nameNode) nameNode.textContent = profile.name || 'User';

          const emailNode = root.querySelector('.text-body-secondary');
          if (emailNode) emailNode.textContent = profile.email || '';

          const detailMap = {
            Phone: profile.phone || '',
            'Date of birth': profile.birth_date || '',
            Language: profile.language || '',
            Address: profile.address || '',
            About: profile.bio || '',
          };

          root.querySelectorAll('.vstack.gap-3 > div').forEach((block) => {
            const label = block.querySelector('.fs-sm.text-dark.fw-bold')?.textContent?.trim() || '';
            if (!label || !(label in detailMap)) return;
            const valueNode = block.lastElementChild;
            if (valueNode) valueNode.textContent = detailMap[label];
            block.classList.toggle('d-none', !detailMap[label]);
          });

          if (avatarImg && profile.avatar) {
            avatarImg.src = profile.avatar;
          }

          profileModal?.hide();
        } catch (error) {
          window.alert('Profile save nahi ho saka. Please dobara try karein.');
        } finally {
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = originalLabel || 'Save changes';
          }
        }
      });
    }
  }

  if (location.pathname === '/account/listings') {
    const root = document.querySelector('.col-lg-9');
    if (!root) return;
    const publishedListings = listings.filter((item) => normalizeListingStatus(item) === 'published');
    const draftListings = listings.filter((item) => normalizeListingStatus(item) === 'draft');
    const url = new URL(window.location.href);
    const savedState = String(url.searchParams.get('saved') || '').trim().toLowerCase();
    const hasEditParam = String(url.searchParams.get('edit') || '').trim() !== '';

    root.innerHTML = `
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 pb-2 pb-lg-3">
        <div class="d-flex align-items-center">
          <h1 class="h2 mb-0">My listings</h1>
        </div>
        <a class="btn btn-primary" href="/add-listing" data-mc-add-listing-btn>
          <i class="fi-plus fs-base me-2"></i>
          Add new listing
        </a>
      </div>

      <div class="nav overflow-x-auto mb-2">
        <ul class="nav nav-pills flex-nowrap gap-2 pb-2 mb-1" role="tablist">
          <li class="nav-item me-1" role="presentation">
            <button type="button" class="nav-link text-nowrap active" id="published-tab" data-bs-toggle="pill" data-bs-target="#published" role="tab" aria-controls="published" aria-selected="true">
              Published (${publishedListings.length})
            </button>
          </li>
          <li class="nav-item me-1" role="presentation">
            <button type="button" class="nav-link text-nowrap" id="drafts-tab" data-bs-toggle="pill" data-bs-target="#drafts" role="tab" aria-controls="drafts" aria-selected="false">
              Drafts (${draftListings.length})
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button type="button" class="nav-link text-nowrap" id="archived-tab" data-bs-toggle="pill" data-bs-target="#archived" role="tab" aria-controls="archived" aria-selected="false">
              Archived (0)
            </button>
          </li>
        </ul>
      </div>

      <div class="tab-content">
        <div class="tab-pane fade show active" id="published" role="tabpanel" aria-labelledby="published-tab">
          <div class="vstack gap-4 pt-2" id="publishedSelection">
            ${publishedListings.length
              ? publishedListings.map((item) => finderAccountCard(item)).join('')
              : emptyStateCard('No published listings yet', 'Your published listings will appear here once you publish them.')}
          </div>
        </div>
        <div class="tab-pane fade" id="drafts" role="tabpanel" aria-labelledby="drafts-tab">
          <div class="vstack gap-4 pt-2" id="draftsSelection">
            ${draftListings.length
              ? draftListings.map((item) => finderAccountCard(item)).join('')
              : emptyStateCard('No drafts yet', 'Draft listings you save for later will appear here.')}
          </div>
        </div>
        <div class="tab-pane fade" id="archived" role="tabpanel" aria-labelledby="archived-tab">
          <h2 class="h6 pt-2 mb-2">You have no archived ads</h2>
          <p class="fs-sm mb-4" style="max-width: 640px">Archived listings will appear here when you move items out of your active account.</p>
        </div>
      </div>
    `;

    const publishedTab = root.querySelector('#published-tab');
    const draftsTab = root.querySelector('#drafts-tab');
    const archivedTab = root.querySelector('#archived-tab');
    const publishedPane = root.querySelector('#published');
    const draftsPane = root.querySelector('#drafts');
    const archivedPane = root.querySelector('#archived');

    const activateListingsTab = (target) => {
      const tabMap = {
        published: { button: publishedTab, pane: publishedPane },
        drafts: { button: draftsTab, pane: draftsPane },
        archived: { button: archivedTab, pane: archivedPane },
      };
      const selected = tabMap[target];
      if (!selected?.button || !selected?.pane) return;

      Object.values(tabMap).forEach((entry) => {
        if (!entry?.button || !entry?.pane) return;
        const isActive = entry === selected;
        entry.button.classList.toggle('active', isActive);
        entry.button.setAttribute('aria-selected', isActive ? 'true' : 'false');
        entry.pane.classList.toggle('show', isActive);
        entry.pane.classList.toggle('active', isActive);
      });

      if (window.bootstrap?.Tab) {
        try {
          window.bootstrap.Tab.getOrCreateInstance(selected.button).show();
        } catch (e) {
          // Fall back to the manual class toggles above.
        }
      }
    };

    const preferredTab = savedState === 'draft' || hasEditParam || (publishedListings.length === 0 && draftListings.length > 0)
      ? 'drafts'
      : 'published';
    activateListingsTab(preferredTab);
  }
})();
</script>
HTML;
            $accountListingsSyncScript = str_replace('__USER_PROFILE__', $profileJson ?: '{}', $accountListingsSyncScript);
            $accountListingsSyncScript = str_replace('__USER_LISTINGS__', $listingsJson ?: '[]', $accountListingsSyncScript);
            $accountListingsSyncScript = str_replace('__SCRIPT_CSRF__', csrf_token(), $accountListingsSyncScript);
            $html = str_replace('</body>', $accountListingsSyncScript . '</body>', $html);
        }

        if ($isNewUser && str_starts_with($file, 'account-')) {
            $emptyAvatarEverywhereScript = <<<'HTML'
<script>
(() => {
  const placeholder = 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 120 120%22%3E%3Ccircle cx=%2260%22 cy=%2260%22 r=%2260%22 fill=%22%23e9ecef%22/%3E%3Cpath d=%22M60 62c-14 0-25-11-25-25s11-25 25-25 25 11 25 25-11 25-25 25zm0 8c22 0 40 12 40 28v10H20V98c0-16 18-28 40-28z%22 fill=%22%239aa4b2%22/%3E%3C/svg%3E';
  document.querySelectorAll('img[src*="/finder/assets/img/account/avatar"], img[src*="/finder/assets/img/avatar"]').forEach((img) => {
    img.src = placeholder;
  });
})();
</script>
HTML;
            $html = str_replace('</body>', $emptyAvatarEverywhereScript . '</body>', $html);
        }

        if ($isNewUser) {
            if ($file === 'account-profile.html') {
                $isUserAccount = (string) ($user?->account_type ?? 'business') === 'user';
                $emptyProfileScript = <<<'HTML'
<script>
(() => {
  const profileRoot = document.querySelector('.col-lg-9');
  if (!profileRoot) return;

  const walletSection = profileRoot.querySelector('section.row.g-3.g-xl-4.pb-5.mb-md-3');
  if (walletSection) walletSection.remove();

  const userInfoSection = profileRoot.querySelector('section.pb-5.mb-md-3');
  if (userInfoSection) {
    const phone = userInfoSection.querySelector('li:nth-child(2)');
    const location = userInfoSection.querySelector('li:nth-child(3)');
    if (phone) phone.remove();
    if (location) location.remove();

    const bio = userInfoSection.querySelector('p.fs-sm.pb-sm-1.pb-md-0.mb-md-4');
    if (bio) bio.textContent = 'Your profile is empty. Add your details from Account settings.';
  }

  const listingsSection = Array.from(profileRoot.querySelectorAll('section.pb-5.mb-md-3'))
    .find((section) => section.querySelector('h2.h4')?.textContent?.trim() === 'My listings');

  if (listingsSection) {
    listingsSection.innerHTML = `
      <div class="card border-0 bg-body-tertiary">
        <div class="card-body py-5 text-center">
          <h3 class="h5 mb-2">No listings yet</h3>
          <p class="text-body-secondary mb-4">__LISTINGS_EMPTY_TEXT__</p>
          __LISTINGS_EMPTY_CTA__
        </div>
      </div>
    `;
  }

  const reviewsSection = Array.from(profileRoot.querySelectorAll('section.pb-5.mb-md-3'))
    .find((section) => section.querySelector('h2.h4')?.textContent?.trim() === 'Reviews');
  if (reviewsSection) {
    reviewsSection.innerHTML = `
      <div class="card border-0 bg-body-tertiary">
        <div class="card-body py-5 text-center">
          <h3 class="h5 mb-2">No reviews yet</h3>
          <p class="text-body-secondary mb-0">__REVIEWS_EMPTY_TEXT__</p>
        </div>
      </div>
    `;
  }

  const favoritesSection = Array.from(profileRoot.querySelectorAll('section.pb-5.mb-md-3'))
    .find((section) => section.querySelector('h2.h4')?.textContent?.trim() === 'Favorites');
  if (favoritesSection) {
    favoritesSection.innerHTML = `
      <div class="card border-0 bg-body-tertiary">
        <div class="card-body py-5 text-center">
          <h3 class="h5 mb-2">No favorites yet</h3>
          <p class="text-body-secondary mb-0">When you save listings, they will show up here.</p>
        </div>
      </div>
    `;
  }
})();
</script>
HTML;
                $emptyProfileScript = str_replace(
                    ['__LISTINGS_EMPTY_TEXT__', '__LISTINGS_EMPTY_CTA__', '__REVIEWS_EMPTY_TEXT__'],
                    $isUserAccount
                        ? [
                            'Browse listings and save the ones you like. Your activity will grow from here.',
                            '<a class="btn btn-primary" href="/listings/contractors">Browse listings</a>',
                            'Reviews you write after signing in will appear in your account.',
                        ]
                        : [
                            'You have not added any listings yet. Start by creating your first listing.',
                            '<a class="btn btn-primary" href="/add-listing">Add your first listing</a>',
                            'Reviews about your profile will appear here.',
                        ],
                    $emptyProfileScript
                );
                $html = str_replace('</body>', $emptyProfileScript . '</body>', $html);
            }

            if ($file === 'account-reviews.html') {
                $emptyReviewsScript = <<<'HTML'
<script>
(() => {
  const root = document.querySelector('.col-lg-9');
  if (!root) return;
  root.innerHTML = `
    <h1 class="h2 pb-2 pb-lg-3">Reviews</h1>
    <div class="card border-0 bg-body-tertiary">
      <div class="card-body py-5 text-center">
        <h3 class="h5 mb-2">No reviews yet</h3>
        <p class="text-body-secondary mb-0">You do not have any reviews yet.</p>
      </div>
    </div>
  `;
})();
</script>
HTML;
                $html = str_replace('</body>', $emptyReviewsScript . '</body>', $html);
            }

            if ($file === 'account-favorites.html') {
                $favoritesScript = <<<'HTML'
<script>
(() => {
  const root = document.querySelector('.col-lg-9');
  if (!root) return;

  const STORAGE_KEY = 'mc_related_favorite_items_v1';

  const escapeHtml = (value) =>
    String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');

  const readItems = () => {
    try {
      const parsed = JSON.parse(window.localStorage.getItem(STORAGE_KEY) || '{}');
      return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
    } catch (e) {
      return {};
    }
  };

  const writeItems = (items) => {
    try {
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify(items || {}));
    } catch (e) {}
  };

  const emptyState = () => `
    <h1 class="h2 pb-2 pb-lg-3">Favorites</h1>
    <div class="card border-0 bg-body-tertiary">
      <div class="card-body py-5 text-center">
        <h3 class="h5 mb-2">No favorites yet</h3>
        <p class="text-body-secondary mb-4">Save listings to see them here.</p>
        <a class="btn btn-primary" href="/listings/cars">Browse listings</a>
      </div>
    </div>
  `;

  const card = (item) => `
    <div class="col">
      <article class="card h-100 border-0 bg-body-tertiary shadow-sm">
        <div class="position-relative">
          <a href="${escapeHtml(item.detail_url || '/listings/cars')}" class="d-block">
            <img src="${escapeHtml(item.image_url || '/finder/assets/img/placeholders/preview-square.svg')}" alt="${escapeHtml(item.title || 'Favorite listing')}" class="card-img-top" style="height:220px;object-fit:cover;" onerror="this.onerror=null;this.src='/finder/assets/img/placeholders/preview-square.svg';">
          </a>
          <button type="button" class="btn btn-sm btn-light position-absolute top-0 end-0 m-3 rounded-circle" data-remove-favorite="${escapeHtml(item.slug || '')}" aria-label="Remove favorite">
            <i class="fi-heart-filled text-danger"></i>
          </button>
        </div>
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between gap-3 mb-2">
            <div class="fs-sm text-body-secondary">${escapeHtml(item.city || 'Location')}</div>
            <div class="fs-xs text-body-secondary">${escapeHtml(item.year || '')}</div>
          </div>
          <h3 class="h5 mb-2">
            <a class="text-decoration-none text-dark-emphasis hover-effect-underline" href="${escapeHtml(item.detail_url || '/listings/cars')}">${escapeHtml(item.title || 'Listing')}</a>
          </h3>
          <div class="h5 mb-3">${escapeHtml(item.price || 'Price on request')}</div>
          <div class="row row-cols-2 g-2 fs-sm text-body-secondary">
            <div class="col d-flex align-items-center gap-2">
              <i class="fi-tachometer"></i>
              ${escapeHtml(item.mileage || 'N/A')}
            </div>
            <div class="col d-flex align-items-center gap-2">
              <i class="fi-gas-pump"></i>
              ${escapeHtml(item.fuel_type || 'N/A')}
            </div>
            <div class="col d-flex align-items-center gap-2">
              <i class="fi-gearbox"></i>
              ${escapeHtml(item.transmission || 'N/A')}
            </div>
            <div class="col d-flex align-items-center gap-2">
              <i class="fi-heart-filled text-danger"></i>
              Saved
            </div>
          </div>
        </div>
      </article>
    </div>
  `;

  const render = () => {
    const items = Object.values(readItems()).filter(Boolean);
    if (!items.length) {
      root.innerHTML = emptyState();
      return;
    }

    root.innerHTML = `
      <div class="d-flex align-items-center justify-content-between gap-3 pb-2 pb-lg-3">
        <h1 class="h2 mb-0">Favorites</h1>
        <div class="fs-sm text-body-secondary">${items.length} saved listing${items.length === 1 ? '' : 's'}</div>
      </div>
      <div class="row row-cols-1 row-cols-md-2 g-4">
        ${items.map(card).join('')}
      </div>
    `;

    root.querySelectorAll('[data-remove-favorite]').forEach((button) => {
      button.addEventListener('click', () => {
        const slug = String(button.getAttribute('data-remove-favorite') || '').trim();
        if (!slug) return;
        const itemsMap = readItems();
        delete itemsMap[slug];
        writeItems(itemsMap);
        render();
      });
    });
  };

  render();
})();
</script>
HTML;
                $html = str_replace('</body>', $favoritesScript . '</body>', $html);
            }

        }
    }

    if ($file === 'account-signin.html') {
        $signinAuditScript = <<<'HTML'
<script>
(() => {
  const params = new URLSearchParams(window.location.search);
  const email = params.get('email') || '';
  const next = params.get('next') || '';
  const input = document.querySelector('input[type="email"], input[name="email"]');
  if (input && email) input.value = email;
  const form = document.querySelector('form[action="/signin"]');
  if (form && next) {
    let hidden = form.querySelector('input[name="next"]');
    if (!hidden) {
      hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = 'next';
      form.prepend(hidden);
    }
    hidden.value = next;
  }
})();
</script>
HTML;
        $html = str_replace('</body>', $signinAuditScript . '</body>', $html);
    }

    if ($file === 'account-signup.html') {
        $signupAuditScript = <<<'HTML'
<script>
(() => {
  const params = new URLSearchParams(window.location.search);
  const next = params.get('next') || '';
  document.querySelectorAll('form[action="/signup"]').forEach((form) => {
    const pass = form.querySelector('input[name="password"]');
    const confirm = form.querySelector('input[name="password_confirmation"]');
    const company = form.querySelector('input[name="company_name"]');
    const fullName = form.querySelector('input[name="name"]');
    const phone = form.querySelector('input[name="phone"]');
    const submit = form.querySelector('button[type="submit"]');
    if (!pass || !submit) return;
    if (next) {
      let hidden = form.querySelector('input[name="next"]');
      if (!hidden) {
        hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'next';
        form.prepend(hidden);
      }
      hidden.value = next;
    }
    const formatPhone = () => {
      if (!phone) return;
      let digits = String(phone.value || '').replace(/\D+/g, '');
      if (digits.length > 11) digits = digits.slice(0, 11);
      if (digits.length === 11 && digits.startsWith('1')) digits = digits.slice(1);
      if (digits.length > 10) digits = digits.slice(0, 10);
      if (!digits) {
        phone.value = '';
        return;
      }
      if (digits.length < 4) {
        phone.value = `(${digits}`;
      } else if (digits.length < 7) {
        phone.value = `(${digits.slice(0, 3)}) ${digits.slice(3)}`;
      } else {
        phone.value = `(${digits.slice(0, 3)}) ${digits.slice(3, 6)}-${digits.slice(6, 10)}`;
      }
    };
    if (phone) {
      phone.setAttribute('inputmode', 'tel');
      phone.setAttribute('maxlength', '14');
      phone.setAttribute('placeholder', '(555) 123-4567');
      phone.addEventListener('input', formatPhone);
      phone.addEventListener('blur', formatPhone);
    }
    form.addEventListener('submit', (e) => {
      const old = form.querySelector('.js-signup-error');
      if (old) old.remove();
      if (company && !(company.value || '').trim()) {
        e.preventDefault();
        form.insertAdjacentHTML('afterbegin', '<div class="alert alert-danger js-signup-error">Company name is required for business owner accounts.</div>');
        return;
      }
      if (fullName && !(fullName.value || '').trim()) {
        e.preventDefault();
        form.insertAdjacentHTML('afterbegin', '<div class="alert alert-danger js-signup-error">Full name is required for user accounts.</div>');
        return;
      }
      if (phone && !(phone.value || '').trim()) {
        e.preventDefault();
        form.insertAdjacentHTML('afterbegin', '<div class="alert alert-danger js-signup-error">Phone number is required.</div>');
        return;
      }
      if (phone) {
        const digits = String(phone.value || '').replace(/\D+/g, '');
        if (digits.length !== 10) {
          e.preventDefault();
          form.insertAdjacentHTML('afterbegin', '<div class="alert alert-danger js-signup-error">Enter a valid US phone number with 10 digits.</div>');
          return;
        }
      }
      if ((pass.value || '').length < 8) {
        e.preventDefault();
        form.insertAdjacentHTML('afterbegin', '<div class="alert alert-danger js-signup-error">Password must be at least 8 characters.</div>');
        return;
      }
      if (confirm && (pass.value || '') !== (confirm.value || '')) {
        e.preventDefault();
        form.insertAdjacentHTML('afterbegin', '<div class="alert alert-danger js-signup-error">Password confirmation does not match.</div>');
      }
    });
  });
})();
</script>
HTML;
        $html = str_replace('</body>', $signupAuditScript . '</body>', $html);
    }

    if ($file === 'account-password-recovery.html') {
        $recoveryAuditScript = <<<'HTML'
<script>
(() => {
  const params = new URLSearchParams(window.location.search);
  const email = params.get('email') || '';
  const input = document.querySelector('input[type="email"], input[name="email"]');
  if (input && email) input.value = email;

  const form = document.querySelector('form[action="/password-recovery"]');
  if (!form) return;
  if (params.get('status') === 'sent') {
    form.insertAdjacentHTML('afterbegin', '<div class="alert alert-success">If this email exists, reset instructions were sent.</div>');
  } else if (params.get('status') === 'missing') {
    form.insertAdjacentHTML('afterbegin', '<div class="alert alert-warning">No account found with this email.</div>');
  }
})();
</script>
HTML;
        $html = str_replace('</body>', $recoveryAuditScript . '</body>', $html);
    }

    if ($file === 'account-settings.html' && Auth::check()) {
        $settingsUser = Auth::user();
        if (
            $settingsUser
            && ! $settingsUser->email_verified_at
            && (string) ($settingsUser->account_type ?? 'business') === 'business'
        ) {
            $ownsClaimedListing = Listing::query()
                ->where('user_id', (int) $settingsUser->id)
                ->exists();
            $hasUsedClaim = ListingClaimRequest::query()
                ->where('claimed_by_user_id', (int) $settingsUser->id)
                ->whereNotNull('used_at')
                ->exists();

            if ($ownsClaimedListing || $hasUsedClaim) {
                $settingsUser->forceFill([
                    'email_verified_at' => now(),
                ])->save();
                $settingsUser->refresh();
            }
        }

        $settingsPayload = [
            'first_name' => (string) ($settingsUser?->first_name ?? ''),
            'last_name' => (string) ($settingsUser?->last_name ?? ''),
            'email' => (string) ($settingsUser?->email ?? ''),
            'email_verified' => (bool) ($settingsUser?->email_verified_at),
            'account_type' => (string) ($settingsUser?->account_type ?? 'business'),
            'company_name' => (string) ($settingsUser?->company_name ?? ''),
            'phone' => $formatUsPhoneForDisplay($settingsUser?->phone),
            'birth_date' => (string) ($settingsUser?->birth_date ?? ''),
            'language' => (string) ($settingsUser?->language ?? ''),
            'address' => (string) ($settingsUser?->address ?? ''),
            'bio' => (string) ($settingsUser?->bio ?? ''),
            'avatar' => (string) ($settingsUser?->avatar_url ?? ''),
        ];
        $settingsJson = json_encode($settingsPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        $settingsScript = <<<'HTML'
<script>
(() => {
  const profile = __PROFILE_DATA__ || {};
  const params = new URLSearchParams(window.location.search);
  const csrf = '__SCRIPT_CSRF__';

  const showNotice = (message, type = 'success') => {
    const root = document.querySelector('.col-lg-9');
    if (!root) return;
    const old = root.querySelector('.monaclick-settings-notice');
    if (old) old.remove();
    const note = document.createElement('div');
    note.className = `alert alert-${type} monaclick-settings-notice`;
    note.textContent = message;
    root.prepend(note);
  };

  if (params.get('settings') === 'updated') showNotice('Profile updated successfully.', 'success');
  if (params.get('password') === 'updated') showNotice('Password updated successfully.', 'success');
  if (params.get('error') === 'password') showNotice('Current password is invalid or new password confirmation failed.', 'danger');

  const setVal = (selector, value) => {
    const el = document.querySelector(selector);
    if (el && typeof value === 'string') el.value = value;
  };

  const updateEmailBadge = () => {
    const badge = document.querySelector('label[for="email"] .badge');
    if (!badge) return;
    if (profile.email_verified) {
      badge.textContent = 'Verified';
      badge.className = 'badge bg-success ms-2';
      return;
    }
    badge.textContent = 'Verify email';
    badge.className = 'badge text-danger bg-danger-subtle ms-2';
  };

  setVal('#fn', profile.first_name || '');
  setVal('#ln', profile.last_name || '');
  setVal('#email', profile.email || '');
  setVal('#phone', profile.phone || '');
  setVal('#birth-date', profile.birth_date || '');
  setVal('#language', profile.language || '');
  setVal('#address', profile.address || '');
  setVal('#user-info', profile.bio || '');
  updateEmailBadge();

  const progressRoot = document.querySelector('#personal-info .card.bg-warning-subtle');
  const progressRing = progressRoot?.querySelector('.circular-progress');
  const progressLabel = progressRing?.querySelector('h5');
  const computeProgress = () => {
    const fields = [
      document.querySelector('#fn'),
      document.querySelector('#ln'),
      document.querySelector('#email'),
      document.querySelector('#phone'),
      document.querySelector('#birth-date'),
      document.querySelector('#language'),
      document.querySelector('#address'),
      document.querySelector('#user-info'),
    ].filter(Boolean);
    const total = fields.length || 1;
    const done = fields.filter((el) => String(el.value || '').trim() !== '').length;
    const percent = Math.round((done / total) * 100);
    if (progressRing) {
      progressRing.style.setProperty('--fn-progress', String(percent));
      progressRing.setAttribute('aria-valuenow', String(percent));
    }
    if (progressLabel) progressLabel.textContent = `${percent}%`;
  };
  ['#fn', '#ln', '#email', '#phone', '#birth-date', '#language', '#address', '#user-info'].forEach((selector) => {
    const el = document.querySelector(selector);
    if (!el) return;
    el.addEventListener('input', computeProgress);
    el.addEventListener('change', computeProgress);
  });
  computeProgress();

  const securityEmail = document.querySelector('#security p span.fw-medium.text-primary');
  if (securityEmail && profile.email) securityEmail.textContent = profile.email;
  const sidebarName = document.querySelector('#accountSidebar h6.mb-1');
  if (sidebarName) sidebarName.textContent = profile.first_name || profile.last_name
    ? `${profile.first_name || ''} ${profile.last_name || ''}`.trim()
    : (profile.email || 'Monaclick User');
  const sidebarEmail = document.querySelector('#accountSidebar .offcanvas-body .fs-sm');
  if (sidebarEmail && profile.email) sidebarEmail.textContent = profile.email;

  const deviceHistoryHeading = Array.from(document.querySelectorAll('#security h3')).find((h) => (h.textContent || '').trim().toLowerCase() === 'device history');
  if (deviceHistoryHeading) {
    const cardGrid = deviceHistoryHeading.nextElementSibling;
    if (cardGrid) cardGrid.remove();
    const signOutAll = deviceHistoryHeading.nextElementSibling;
    if (signOutAll) signOutAll.remove();
    deviceHistoryHeading.remove();
  }

  const avatarImages = Array.from(document.querySelectorAll('#personal-info img, #accountSidebar img, .offcanvas-body img.rounded-circle, img[alt*="Avatar"], img[alt*="photo"]'))
    .filter((img) => img && img.tagName === 'IMG');
  avatarImages.forEach((img) => {
    if (profile.avatar) img.src = profile.avatar;
  });

  const personalForm = document.querySelector('#personal-info form.needs-validation');
  if (personalForm) {
    personalForm.method = 'post';
    personalForm.action = '/account/settings';
    personalForm.enctype = 'multipart/form-data';

    const ensureHidden = (name, value) => {
      let field = personalForm.querySelector(`input[type="hidden"][name="${name}"]`);
      if (!field) {
        field = document.createElement('input');
        field.type = 'hidden';
        field.name = name;
        personalForm.prepend(field);
      }
      field.value = value;
    };

    ensureHidden('_token', csrf);
    ensureHidden('_method', 'POST');

    const mapName = (selector, name) => {
      const el = personalForm.querySelector(selector);
      if (el) el.name = name;
    };
    mapName('#fn', 'first_name');
    mapName('#ln', 'last_name');
    mapName('#email', 'email');
    mapName('#phone', 'phone');
    mapName('#birth-date', 'birth_date');
    mapName('#language', 'language');
    mapName('#address', 'address');
    mapName('#user-info', 'bio');

    let avatarInput = personalForm.querySelector('input[type="file"][name="avatar"]');
    if (!avatarInput) {
      avatarInput = document.createElement('input');
      avatarInput.type = 'file';
      avatarInput.name = 'avatar';
      avatarInput.accept = 'image/*';
      avatarInput.className = 'd-none';
      personalForm.appendChild(avatarInput);
    }

    const updatePhotoBtn = Array.from(document.querySelectorAll('#personal-info button, #personal-info a'))
      .find((el) => (el.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase() === 'update photo');
    if (updatePhotoBtn) {
      updatePhotoBtn.addEventListener('click', (event) => {
        event.preventDefault();
        avatarInput.click();
      });
      avatarInput.addEventListener('change', () => {
        const file = avatarInput.files && avatarInput.files[0];
        if (!file) return;
        const src = URL.createObjectURL(file);
        avatarImages.forEach((img) => { img.src = src; });
        const fd = new FormData();
        fd.append('_token', csrf);
        fd.append('avatar', file);
        fetch('/account/avatar', { method: 'POST', body: fd, credentials: 'same-origin' })
          .then((r) => r.ok ? r.json() : Promise.reject(new Error('upload failed')))
          .then((res) => {
            if (res && res.avatar_url) {
              avatarImages.forEach((img) => { img.src = res.avatar_url; });
            }
            showNotice('Profile photo updated successfully.', 'success');
          })
          .catch(() => {
            showNotice('Photo upload failed. Please try again.', 'danger');
          });
      });
    }
  }

  const passwordForm = document.querySelector('#security form.needs-validation');
  if (passwordForm) {
    passwordForm.method = 'post';
    passwordForm.action = '/account/password';
    const ensureHidden = (name, value) => {
      let field = passwordForm.querySelector(`input[type="hidden"][name="${name}"]`);
      if (!field) {
        field = document.createElement('input');
        field.type = 'hidden';
        field.name = name;
        passwordForm.prepend(field);
      }
      field.value = value;
    };
    ensureHidden('_token', csrf);
    ensureHidden('_method', 'POST');

    const current = passwordForm.querySelector('#current-password');
    const next = passwordForm.querySelector('#new-password');
    const confirm = passwordForm.querySelector('#confirm-new-password');
    if (current) current.name = 'current_password';
    if (next) next.name = 'password';
    if (confirm) confirm.name = 'password_confirmation';
  }
})();
</script>
HTML;
        $settingsScript = str_replace('__PROFILE_DATA__', $settingsJson ?: '{}', $settingsScript);
        $settingsScript = str_replace('__SCRIPT_CSRF__', csrf_token(), $settingsScript);
        $html = str_replace('</body>', $settingsScript . '</body>', $html);
    }

    if ($file === 'account-reviews.html' && Auth::check()) {
        $reviewsPayload = [
            'account_type' => (string) (Auth::user()?->account_type ?? 'business'),
            'written' => [],
            'received' => [],
        ];

        if (Schema::hasTable('reviews')) {
            $writtenReviews = Review::query()
                ->with(['listing:id,title,slug,module,image', 'user:id,name,first_name,last_name,account_type,company_name'])
                ->where('user_id', Auth::id())
                ->latest('updated_at')
                ->get();

            $ownedListingIds = Listing::query()
                ->where('user_id', Auth::id())
                ->pluck('id');

            $receivedReviews = $ownedListingIds->isEmpty()
                ? collect()
                : Review::query()
                    ->with(['listing:id,title,slug,module,image', 'user:id,name,first_name,last_name,account_type,company_name'])
                    ->whereIn('listing_id', $ownedListingIds)
                    ->latest('updated_at')
                    ->get();

            $mapReview = static function (Review $review): array {
                $authorName = trim((string) (
                    (($review->user?->account_type ?? 'business') === 'business'
                        ? ($review->user?->company_name ?? '')
                        : (($review->user?->first_name ?? '') . ' ' . ($review->user?->last_name ?? ''))
                    )
                ));
                if ($authorName === '') {
                    $authorName = trim((string) ($review->user?->name ?? 'Monaclick User'));
                }

                return [
                    'id' => $review->id,
                    'rating' => (int) $review->rating,
                    'comment' => (string) ($review->comment ?? ''),
                    'is_approved' => (bool) $review->is_approved,
                    'date' => optional($review->updated_at)->format('M j, Y'),
                    'author_name' => $authorName !== '' ? $authorName : 'Monaclick User',
                    'listing_title' => (string) ($review->listing?->title ?? 'Listing'),
                    'listing_slug' => (string) ($review->listing?->slug ?? ''),
                    'listing_module' => (string) ($review->listing?->module ?? ''),
                    'listing_image' => $review->listing?->image_url ?? '/finder/assets/img/placeholders/preview-square.svg',
                ];
            };

            $reviewsPayload['written'] = $writtenReviews->map($mapReview)->values()->all();
            $reviewsPayload['received'] = $receivedReviews->map($mapReview)->values()->all();
        }

        $reviewsJson = json_encode($reviewsPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        $forceEmptyReviewsScript = <<<'HTML'
<script>
(() => {
  const payload = __REVIEWS_DATA__ || { written: [], received: [], account_type: 'business' };
  const root = document.querySelector('.col-lg-9');
  if (!root) return;

  const escapeHtml = (value) =>
    String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');

  const renderStars = (rating) => Array.from({ length: 5 }, (_, index) =>
    `<i class="${index < Number(rating || 0) ? 'fi-star-filled text-warning' : 'fi-star text-body-tertiary'}"></i>`
  ).join('');

  const emptyCard = (title, text, ctaHtml = '') => `
    <div class="card border-0 bg-body-tertiary">
      <div class="card-body py-5 text-center">
        <h3 class="h5 mb-2">${title}</h3>
        <p class="text-body-secondary mb-${ctaHtml ? '4' : '0'}">${text}</p>
        ${ctaHtml}
      </div>
    </div>
  `;

  const reviewCard = (item, mode) => `
    <article class="card border-0 bg-body-tertiary shadow-sm h-100">
      <div class="card-body">
        <div class="d-flex align-items-start gap-3">
          <img src="${escapeHtml(item.listing_image || '/finder/assets/img/placeholders/preview-square.svg')}" alt="${escapeHtml(item.listing_title || 'Listing')}" class="rounded-4 flex-shrink-0" width="88" height="88" style="object-fit:cover;">
          <div class="w-100">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
              <a class="text-decoration-none text-dark-emphasis fw-semibold" href="${escapeHtml(item.listing_slug ? `/entry/${item.listing_slug}` : '#')}">${escapeHtml(item.listing_title || 'Listing')}</a>
              <span class="fs-sm text-body-secondary">${escapeHtml(item.date || '')}</span>
            </div>
            <div class="d-flex align-items-center gap-2 fs-sm mb-2">
              <span>${renderStars(item.rating)}</span>
              <span class="text-body-secondary">${Number(item.rating || 0)} / 5</span>
            </div>
            <p class="mb-3">${escapeHtml(item.comment || '')}</p>
            <div class="fs-sm text-body-secondary">
              ${mode === 'received' ? `From ${escapeHtml(item.author_name || 'Monaclick User')}` : `Written by ${escapeHtml(item.author_name || 'You')}`}
            </div>
          </div>
        </div>
      </div>
    </article>
  `;

  const written = Array.isArray(payload.written) ? payload.written : [];
  const received = Array.isArray(payload.received) ? payload.received : [];
  const isUserAccount = String(payload.account_type || 'business') === 'user';
  const showWrittenReviews = isUserAccount;
  const showReceivedReviews = !isUserAccount;

  root.innerHTML = `
    <div class="d-flex align-items-center justify-content-between gap-3 pb-2 pb-lg-3">
      <div>
        <h1 class="h2 mb-1">Reviews</h1>
        <p class="text-body-secondary mb-0">${isUserAccount ? 'Track the reviews you have written for listings.' : 'Manage reviews about your listings.'}</p>
      </div>
    </div>
    <div class="row g-4">
      ${showWrittenReviews ? `
      <div class="col-12">
        <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
          <h2 class="h4 mb-0">Reviews by you</h2>
          <span class="badge bg-body-secondary text-body">${written.length}</span>
        </div>
        ${written.length
          ? `<div class="row row-cols-1 g-3">${written.map((item) => `<div class="col">${reviewCard(item, 'written')}</div>`).join('')}</div>`
          : emptyCard('No reviews written yet', 'Once you leave a review on a listing, it will appear here.', '<a class="btn btn-primary" href="/listings/contractors">Browse listings</a>')}
      </div>
      ` : ''}
      ${showReceivedReviews ? `
        <div class="col-12">
          <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
            <h2 class="h4 mb-0">Reviews about your listings</h2>
            <span class="badge bg-body-secondary text-body">${received.length}</span>
          </div>
          ${received.length
            ? `<div class="row row-cols-1 g-3">${received.map((item) => `<div class="col">${reviewCard(item, 'received')}</div>`).join('')}</div>`
            : emptyCard('No reviews received yet', 'Reviews from signed-in users will appear here when they are submitted for your listings.')}
        </div>
      ` : ''}
    </div>
  `;
})();
</script>
HTML;
        $forceEmptyReviewsScript = str_replace('__REVIEWS_DATA__', $reviewsJson ?: '{}', $forceEmptyReviewsScript);
        $html = str_replace('</body>', $forceEmptyReviewsScript . '</body>', $html);
    }

    if ($file === 'account-favorites.html') {
        $favoritesScript = <<<'HTML'
<script>
(() => {
  const root = document.querySelector('.col-lg-9');
  if (!root) return;

  const STORAGE_KEY = 'mc_related_favorite_items_v1';

  const escapeHtml = (value) =>
    String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');

  const readItems = () => {
    try {
      const parsed = JSON.parse(window.localStorage.getItem(STORAGE_KEY) || '{}');
      return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
    } catch (e) {
      return {};
    }
  };

  const writeItems = (items) => {
    try {
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify(items || {}));
    } catch (e) {}
  };

  const emptyState = () => `
    <h1 class="h2 pb-2 pb-lg-3">Favorites</h1>
    <div class="card border-0 bg-body-tertiary">
      <div class="card-body py-5 text-center">
        <h3 class="h5 mb-2">No favorites yet</h3>
        <p class="text-body-secondary mb-4">Save listings to see them here.</p>
        <a class="btn btn-primary" href="/listings/cars">Browse listings</a>
      </div>
    </div>
  `;

  const card = (item) => `
    <div class="col">
      <article class="card h-100 border-0 bg-body-tertiary shadow-sm">
        <div class="position-relative">
          <a href="${escapeHtml(item.detail_url || '/listings/cars')}" class="d-block">
            <img src="${escapeHtml(item.image_url || '/finder/assets/img/placeholders/preview-square.svg')}" alt="${escapeHtml(item.title || 'Favorite listing')}" class="card-img-top" style="height:220px;object-fit:cover;" onerror="this.onerror=null;this.src='/finder/assets/img/placeholders/preview-square.svg';">
          </a>
          <button type="button" class="btn btn-sm btn-light position-absolute top-0 end-0 m-3 rounded-circle" data-remove-favorite="${escapeHtml(item.slug || '')}" aria-label="Remove favorite">
            <i class="fi-heart-filled text-danger"></i>
          </button>
        </div>
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between gap-3 mb-2">
            <div class="fs-sm text-body-secondary">${escapeHtml(item.city || 'Location')}</div>
            <div class="fs-xs text-body-secondary">${escapeHtml(item.year || '')}</div>
          </div>
          <h3 class="h5 mb-2">
            <a class="text-decoration-none text-dark-emphasis hover-effect-underline" href="${escapeHtml(item.detail_url || '/listings/cars')}">${escapeHtml(item.title || 'Listing')}</a>
          </h3>
          <div class="h5 mb-3">${escapeHtml(item.price || 'Price on request')}</div>
          <div class="row row-cols-2 g-2 fs-sm text-body-secondary">
            <div class="col d-flex align-items-center gap-2">
              <i class="fi-tachometer"></i>
              ${escapeHtml(item.mileage || 'N/A')}
            </div>
            <div class="col d-flex align-items-center gap-2">
              <i class="fi-gas-pump"></i>
              ${escapeHtml(item.fuel_type || 'N/A')}
            </div>
            <div class="col d-flex align-items-center gap-2">
              <i class="fi-gearbox"></i>
              ${escapeHtml(item.transmission || 'N/A')}
            </div>
            <div class="col d-flex align-items-center gap-2">
              <i class="fi-heart-filled text-danger"></i>
              Saved
            </div>
          </div>
        </div>
      </article>
    </div>
  `;

  const render = () => {
    const items = Object.values(readItems()).filter(Boolean);
    if (!items.length) {
      root.innerHTML = emptyState();
      return;
    }

    root.innerHTML = `
      <div class="d-flex align-items-center justify-content-between gap-3 pb-2 pb-lg-3">
        <h1 class="h2 mb-0">Favorites</h1>
        <div class="fs-sm text-body-secondary">${items.length} saved listing${items.length === 1 ? '' : 's'}</div>
      </div>
      <div class="row row-cols-1 row-cols-md-2 g-4">
        ${items.map(card).join('')}
      </div>
    `;

    root.querySelectorAll('[data-remove-favorite]').forEach((button) => {
      button.addEventListener('click', () => {
        const slug = String(button.getAttribute('data-remove-favorite') || '').trim();
        if (!slug) return;
        const itemsMap = readItems();
        delete itemsMap[slug];
        writeItems(itemsMap);
        render();
      });
    });
  };

  render();
})();
</script>
HTML;
        $html = str_replace('</body>', $favoritesScript . '</body>', $html);
    }

    if ($file === 'add-listing.html') {
        $resetPropertyWizardScript = <<<'HTML'
<script>
(() => {
  try {
    localStorage.removeItem('propertyWizardSession');
    localStorage.removeItem('propertyWizardBaseData');
    localStorage.removeItem('propertyWizardData');
  } catch (_) {}
})();
</script>
HTML;
        $html = str_replace('</body>', $resetPropertyWizardScript . '</body>', $html);

        if (! Auth::check()) {
            $addListingGuestGuard = <<<'HTML'
<script>
(() => {
  const next = encodeURIComponent('/add-listing');
  const signinUrl = `/signin?next=${next}`;
  const signupUrl = `/signup?next=${next}`;

  const ensureModal = () => {
    let modalEl = document.getElementById('mcAddListingGuestModal');
    if (modalEl) return modalEl;

    modalEl = document.createElement('div');
    modalEl.id = 'mcAddListingGuestModal';
    modalEl.className = 'modal fade';
    modalEl.tabIndex = -1;
    modalEl.setAttribute('aria-hidden', 'true');
    modalEl.innerHTML = `
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
          <div class="modal-header border-0 pb-0">
            <h2 class="h4 modal-title mb-0">Create a listing</h2>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body pt-3">
            <p class="text-body-secondary mb-4">Please sign in or sign up first to create a listing.</p>
            <div class="d-flex flex-column gap-2">
              <a class="btn btn-primary" href="${signinUrl}">Sign in</a>
              <a class="btn btn-outline-secondary" href="${signupUrl}">Sign up</a>
            </div>
          </div>
        </div>
      </div>
    `;
    document.body.appendChild(modalEl);
    return modalEl;
  };

  const showPrompt = () => {
    const modalEl = ensureModal();
    if (window.bootstrap && window.bootstrap.Modal) {
      window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
      return;
    }
    const wantsSignIn = window.confirm('Please sign in or sign up first to create a listing.\n\nPress OK for Sign in, or Cancel for Sign up.');
    window.location.href = wantsSignIn ? signinUrl : signupUrl;
  };

  const guardedPaths = new Set([
    '/add-property',
    '/add-property-location',
    '/add-property-photos',
    '/add-property-details',
    '/add-property-price',
    '/add-property-contact-info',
    '/add-property-promotion',
    '/add-contractor-location',
    '/add-contractor-services',
    '/add-contractor-profile',
    '/add-contractor-price-hours',
    '/add-contractor-project',
    '/add-car',
    '/add-restaurant',
  ]);

  document.addEventListener('click', (event) => {
    const anchor = event.target.closest('a[href]');
    if (!(anchor instanceof HTMLAnchorElement)) return;
    let url;
    try {
      url = new URL(anchor.href, window.location.origin);
    } catch (_) {
      return;
    }
    if (!guardedPaths.has(url.pathname)) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    showPrompt();
  }, true);

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      window.setTimeout(showPrompt, 100);
    }, { once: true });
  } else {
    window.setTimeout(showPrompt, 100);
  }
})();
</script>
HTML;
            $html = str_replace('</body>', $addListingGuestGuard . '</body>', $html);
        }
    }

    if ($file === 'add-property-type.html') {
        $propertyEditPayload = null;
        $editId = (int) request()->query('edit', 0);
        if ($editId > 0) {
            $editQuery = Listing::query()
                ->with(['propertyDetail', 'city', 'category'])
                ->where('id', $editId)
                ->where('module', 'real-estate');
            if (Auth::check()) {
                $editQuery->where('user_id', Auth::id());
            }
            $editListing = $editQuery->first();
            if ($editListing) {
                $propertyEditPayload = [
                    'id' => $editListing->id,
                    'title' => (string) $editListing->title,
                    'listing_type' => (string) ($editListing->propertyDetail?->listing_type ?? 'sale'),
                    'property_type' => (string) ($editListing->propertyDetail?->property_type ?? ''),
                ];
            }
        }
        $propertyEditJson = json_encode($propertyEditPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        $propertyScript = <<<'HTML'
<script>
(() => {
  const search = new URLSearchParams(window.location.search);
  const forceFresh = search.get('fresh') === '1';
  const resumeFlow = search.get('resume') === '1';
  const editData = forceFresh ? null : __PROPERTY_EDIT_DATA__;
  const isEdit = !!(editData && editData.id);
  let isSubmitting = false;
  const wizardKey = 'propertyWizardSession';
  const baseDataKey = 'propertyWizardBaseData';
  const wizardDataKey = 'propertyWizardData';
  const randomWizardSession = () => `${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
  const ensureWizardSession = () => {
    try {
      let value = localStorage.getItem(wizardKey);
      // Never reuse an old numeric session (often from editId) when starting a new listing.
      if (!value || /^\d+$/.test(String(value).trim())) {
        value = randomWizardSession();
        localStorage.setItem(wizardKey, value);
      }
      return value;
    } catch (_) {
      return randomWizardSession();
    }
  };

  // New listing from entry point: always reset wizard state so the form opens empty.
  if (!isEdit && !resumeFlow) {
    try {
      localStorage.removeItem(baseDataKey);
      localStorage.removeItem(wizardDataKey);
      localStorage.setItem(wizardKey, randomWizardSession());
    } catch (_) {}
  } else if (!isEdit) {
    try {
      localStorage.setItem(wizardKey, localStorage.getItem(wizardKey) || randomWizardSession());
    } catch (_) {}
  }
  if ((forceFresh || resumeFlow) && window.location.search) {
    try {
      window.history.replaceState({}, '', '/add-property');
    } catch (_) {}
  }
  const heading = document.querySelector('h1.h2');
  if (isEdit && heading) heading.textContent = 'Edit property';

  const getCheckedLabel = (name) => {
    const checked = document.querySelector(`input[name="${name}"]:checked`);
    if (!checked) return '';
    const label = document.querySelector(`label[for="${checked.id}"]`);
    return (label?.textContent || '').trim();
  };
  const setCheckedByName = (name, id) => {
    const el = document.getElementById(id);
    if (!el) return;
    el.checked = true;
    el.dispatchEvent(new Event('change', { bubbles: true }));
  };
  const buildBasePayload = () => ({
    'radio:category': getCheckedLabel('category').toLowerCase().includes('rent') ? 'rent' : (getCheckedLabel('category') ? 'sell' : ''),
    'radio:type': getCheckedLabel('type'),
    'radio:condition': getCheckedLabel('condition'),
    'wizard_session': ensureWizardSession()
  });
  const validateBaseStep = () => {
    const hidePageLoader = () => {
      if (typeof window.__MC_HIDE_PAGE_LOADER__ === 'function') {
        window.__MC_HIDE_PAGE_LOADER__();
      }
    };
    const payload = buildBasePayload();
    if (!payload['radio:category']) {
      hidePageLoader();
      alert('Please select a property category.');
      document.querySelector('input[name="category"]')?.focus();
      return false;
    }
    if (!payload['radio:type']) {
      hidePageLoader();
      alert('Please select a property type.');
      document.querySelector('input[name="type"]')?.focus();
      return false;
    }
    if (!payload['radio:condition']) {
      hidePageLoader();
      alert('Please select the property condition.');
      document.querySelector('input[name="condition"]')?.focus();
      return false;
    }
    return true;
  };
  const persistBasePayload = () => {
    const payload = buildBasePayload();
    try {
      localStorage.setItem(baseDataKey, JSON.stringify(payload));
      localStorage.setItem(wizardDataKey, JSON.stringify({
        ...(JSON.parse(localStorage.getItem(wizardDataKey) || '{}')),
        ...payload,
      }));
    } catch (_) {}
  };
  const submitPayload = (isDraft, nextPath = '') => {
    if (isSubmitting) return;
    isSubmitting = true;
    const payload = buildBasePayload();
    const form = document.createElement('form');
    form.method = 'post';
    form.action = '/submit/property';
    form.innerHTML = `
      <input type="hidden" name="_token" value="__SCRIPT_CSRF__">
      <input type="hidden" name="payload" value="${btoa(unescape(encodeURIComponent(JSON.stringify(payload))))}">
      ${isEdit ? '' : '<input type="hidden" name="fresh_start" value="1">'}
      ${isDraft ? '<input type="hidden" name="draft" value="1">' : ''}
      ${isEdit ? `<input type="hidden" name="listing_id" value="${String(editData.id)}">` : ''}
      ${nextPath ? `<input type="hidden" name="next" value="${nextPath}">` : ''}
    `;
    document.body.appendChild(form);
    form.submit();
  };

  const actionsBar = document.querySelector('.pt-5.d-flex.flex-wrap.gap-3.align-items-center');
  if (actionsBar) actionsBar.classList.add('justify-content-start');
  const draftBtn = document.querySelector('.pt-5 .btn.btn-lg.btn-outline-secondary');
  const nextStepBtn = document.querySelector('.pt-5 .btn.btn-lg.btn-dark');
  if (draftBtn) {
    draftBtn.type = 'button';
    draftBtn.addEventListener('click', () => submitPayload(true));
  }
  if (nextStepBtn) {
    nextStepBtn.setAttribute('href', isEdit ? `/add-property-location?edit=${encodeURIComponent(String(editData.id))}` : '/add-property-location');
    nextStepBtn.addEventListener('click', (event) => {
      event.preventDefault();
      if (!validateBaseStep()) return;
      persistBasePayload();
      window.location.href = nextStepBtn.getAttribute('href') || '/add-property-location';
    });
  }
  if (isEdit) {
    if ((editData.listing_type || '').toLowerCase() === 'rent') setCheckedByName('category', 'rent');
    else setCheckedByName('category', 'sell');
    if ((editData.property_type || '').toLowerCase() === 'commercial') setCheckedByName('type', 'commercial');
    else setCheckedByName('type', 'house');
    persistBasePayload();

    const actions = document.querySelector('.pt-5.d-flex.flex-wrap.gap-3.align-items-center');
    if (actions) {
      const delForm = document.createElement('form');
      delForm.method = 'post';
      delForm.action = '/account/listings/delete';
      delForm.innerHTML = `
        <input type="hidden" name="_token" value="__SCRIPT_CSRF__">
        <input type="hidden" name="listing_id" value="${String(editData.id)}">
        <button type="submit" class="btn btn-lg btn-outline-danger">Delete listing</button>
      `;
      delForm.addEventListener('submit', (event) => {
        if (!window.confirm('Delete this listing?')) event.preventDefault();
      });
      actions.appendChild(delForm);
    }
  }
})();
</script>
HTML;
        $propertyScript = str_replace('__PROPERTY_EDIT_DATA__', $propertyEditJson ?: 'null', $propertyScript);
        $propertyScript = str_replace('__SCRIPT_CSRF__', csrf_token(), $propertyScript);
        $html = str_replace('</body>', $propertyScript . '</body>', $html);
    }

    if (str_starts_with($file, 'add-property-')) {
        $propertyEditPayload = null;
        $editId = (int) request()->query('edit', 0);
        if ($editId > 0) {
            $editQuery = Listing::query()
                ->with(['propertyDetail', 'city', 'category', 'images'])
                ->where('id', $editId)
                ->where('module', 'real-estate');
            if (Auth::check()) {
                $editQuery->where('user_id', Auth::id());
            }
            $editListing = $editQuery->first();
            if ($editListing) {
                $wizardData = $editListing->propertyDetail?->wizard_data;
                $wizardData = is_array($wizardData) ? $wizardData : [];

                $cityName = (string) ($editListing->city?->name ?? '');
                if ($cityName !== '') {
                    $wizardData['select:city-select'] = $wizardData['select:city-select'] ?? $cityName;
                    $wizardData['city'] = $wizardData['city'] ?? $cityName;
                }

                if (Schema::hasColumn('cities', 'state_code')) {
                    $stateCode = (string) ($editListing->city?->state_code ?? '');
                    if ($stateCode !== '') {
                        $wizardData['state'] = $wizardData['state'] ?? $stateCode;
                    }
                }

                $propertyEditPayload = [
                    'id' => $editListing->id,
                    'title' => (string) $editListing->title,
                    'listing_type' => (string) ($editListing->propertyDetail?->listing_type ?? 'sale'),
                    'property_type' => (string) ($editListing->propertyDetail?->property_type ?? ''),
                    'wizard_data' => $wizardData,
                    'images' => $editListing->images->map(fn ($img) => (string) $img->image_url)->values()->all(),
                ];
            }
        }
        $propertyEditJson = json_encode($propertyEditPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        $propertyNavScript = <<<'HTML'
<script>
(() => {
  const search = new URLSearchParams(window.location.search);
  const forceFresh = search.get('fresh') === '1';
  const resumeFlow = search.get('resume') === '1';
  const editData = forceFresh ? null : (__PROPERTY_EDIT_DATA__ || null);
  let isSubmitting = false;
  const wizardKey = 'propertyWizardSession';
  const baseDataKey = 'propertyWizardBaseData';
  const wizardDataKey = 'propertyWizardData';
  const pendingFinalActionKey = 'propertyPendingFinalAction';
  const randomWizardSession = () => `${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
  const ensureWizardSession = () => {
    try {
      let value = localStorage.getItem(wizardKey);
      // If we don't have an active editId and the saved value looks like a numeric listing id,
      // treat it as stale and start a new wizard session (otherwise new listing overwrites old one).
      if (!value || (!editId && /^\d+$/.test(String(value).trim()))) {
        value = randomWizardSession();
        localStorage.setItem(wizardKey, value);
      }
      return value;
    } catch (_) {
      return randomWizardSession();
    }
  };
  const stepMap = [
    { key: 'property type', url: '/add-property' },
    { key: 'location', url: '/add-property-location' },
    { key: 'photos and videos', url: '/add-property-photos' },
    { key: 'property details', url: '/add-property-details' },
    { key: 'price', url: '/add-property-price' },
    { key: 'contact info', url: '/add-property-contact-info' }
  ];
  const editId = forceFresh ? '' : (search.get('edit') || (editData?.id ? String(editData.id) : '')).trim();
  // Starting a new listing from the entry point should clear old wizard state.
  if (!editId && window.location.pathname === '/add-property' && !resumeFlow) {
    try {
      localStorage.removeItem(baseDataKey);
      localStorage.removeItem(wizardDataKey);
      localStorage.removeItem(pendingFinalActionKey);
      localStorage.setItem(wizardKey, randomWizardSession());
    } catch (_) {}
  }
  if ((forceFresh || resumeFlow) && !editId && window.location.pathname.startsWith('/add-property')) {
    try {
      const cleanPath = window.location.pathname;
      window.history.replaceState({}, '', cleanPath);
    } catch (_) {}
  }
  const readStoredWizardData = () => {
    try {
      const raw = localStorage.getItem(wizardDataKey);
      const parsed = raw ? JSON.parse(raw) : null;
      return parsed && typeof parsed === 'object' ? parsed : {};
    } catch (_) {
      return {};
    }
  };
  const writeStoredWizardData = (payload) => {
    try {
      localStorage.setItem(wizardDataKey, JSON.stringify(payload || {}));
    } catch (_) {}
  };
  const mergeStoredWizardData = (payload) => {
    const merged = { ...readStoredWizardData(), ...(payload || {}) };
    writeStoredWizardData(merged);
    return merged;
  };
  const readBasePayload = () => {
    try {
      const raw = localStorage.getItem(baseDataKey);
      const parsed = raw ? JSON.parse(raw) : null;
      return parsed && typeof parsed === 'object' ? parsed : {};
    } catch (_) {
      return {};
    }
  };
  const serverWizard = (editData && editData.wizard_data && typeof editData.wizard_data === 'object') ? editData.wizard_data : {};
  const wizard = { ...readStoredWizardData(), ...serverWizard };
  window.__propertyEditData = editData;
  const withEdit = (url) => {
    if (editId) return `${url}?edit=${encodeURIComponent(editId)}`;
    if (url === '/add-property') return '/add-property?resume=1';
    return url;
  };
  const currentStepIndex = stepMap.findIndex((step) => step.url === window.location.pathname);

  // Sidebar steps: allow random step navigation on click.
  const sidebarLinks = Array.from(document.querySelectorAll('.col-lg-3 .nav.flex-lg-column .nav-link'));
  sidebarLinks.forEach((link) => {
    const text = (link.textContent || '').trim().toLowerCase();
    const step = stepMap.find((s) => text.includes(s.key));
    if (!step) return;
    const stepIndex = stepMap.findIndex((s) => s.url === step.url);
    const isCurrentStep = stepIndex === currentStepIndex;
    const isCompletedStep = currentStepIndex > -1 && stepIndex < currentStepIndex;
    const icon = link.querySelector('i');

    link.classList.remove('active', 'disabled', 'pe-none', 'position-relative');
    link.removeAttribute('aria-current');
    link.classList.add('d-inline-flex', 'px-0', 'px-lg-3');

    if (isCurrentStep) {
      link.classList.add('pe-none');
      link.setAttribute('aria-current', 'page');
      if (icon) {
        icon.className = 'fi-arrow-right-circle d-none d-lg-inline-flex fs-lg me-2';
        const mobileIcon = document.createElement('i');
        mobileIcon.className = 'fi-arrow-down-circle d-lg-none fs-lg me-2';
        if (!link.querySelector('.fi-arrow-down-circle')) {
          icon.insertAdjacentElement('afterend', mobileIcon);
        } else {
          link.querySelector('.fi-arrow-down-circle').className = mobileIcon.className;
        }
      }
    } else {
      link.querySelectorAll('.fi-arrow-down-circle').forEach((node) => node.remove());
      if (isCompletedStep) {
        link.classList.add('active', 'position-relative');
        if (icon) icon.className = 'fi-check-circle fs-lg me-2';
      } else if (icon) {
        icon.className = 'fi-circle fs-lg me-2';
      }
    }

    if (!isCurrentStep) {
      link.classList.remove('disabled', 'pe-none');
    }
    link.removeAttribute('aria-disabled');
    link.setAttribute('href', withEdit(step.url));
  });

  // Normalize next-step buttons that still point to *.html.
  document.querySelectorAll('a.btn.btn-lg.btn-dark[href]').forEach((btn) => {
    const href = (btn.getAttribute('href') || '').trim();
    const m = href.match(/^add-property-([a-z-]+)\.html$/i);
    if (!m) return;
    btn.setAttribute('href', withEdit(`/add-property-${m[1].toLowerCase()}`));
  });

  // New listing mode: clear template demo defaults only when starting fresh on the first step.
  if (!editId && window.location.pathname === '/add-property' && !Object.keys(wizard).length) {
    const clear = () => {
      const root = document.querySelector('main') || document;
      root.querySelectorAll('form').forEach((form) => {
        form.setAttribute('autocomplete', 'off');
      });

      root.querySelectorAll('input, textarea, select').forEach((el) => {
        if (el.tagName === 'INPUT') {
          const type = (el.getAttribute('type') || 'text').toLowerCase();
          if (type === 'hidden' || type === 'submit' || type === 'button' || type === 'file') return;

          // Reduce browser autofill; keep it consistent across fields.
          el.setAttribute('autocomplete', 'off');
          if (type === 'email') el.setAttribute('autocomplete', 'new-password');
          if (type === 'tel') el.setAttribute('autocomplete', 'new-password');

          if (type === 'checkbox' || type === 'radio') {
            el.checked = false;
            el.defaultChecked = false;
            el.removeAttribute('checked');
            return;
          }

          el.value = '';
          el.defaultValue = '';
          el.removeAttribute('value');
          return;
        }

        if (el.tagName === 'TEXTAREA') {
          el.value = '';
          el.defaultValue = '';
          el.textContent = '';
          return;
        }

        if (el.tagName === 'SELECT') {
          el.selectedIndex = 0;
          el.dispatchEvent(new Event('change', { bubbles: true }));
        }
      });
    };

    // Run twice to override late autofill and template defaults.
    clear();
    setTimeout(clear, 50);
    setTimeout(clear, 250);
  }

  const setInput = (id, value) => {
    const el = document.getElementById(id);
    if (!el || typeof value === 'undefined' || value === null) return;
    el.value = String(value);
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
  };
  const setSelectByValue = (ariaLabel, value) => {
    if (!value) return;
    const select = document.querySelector(`select[aria-label="${ariaLabel}"]`);
    if (!select) return;

    const wanted = String(value).trim().toLowerCase();
    const trySet = (attemptsLeft) => {
      // Wait for async option population (theme script) when possible.
      if (select.options.length <= 1 && attemptsLeft > 0) {
        setTimeout(() => trySet(attemptsLeft - 1), 75);
        return;
      }
      let option = Array.from(select.options).find((o) => (o.value || o.textContent || '').trim().toLowerCase() === wanted);
      if (!option) {
        option = document.createElement('option');
        option.value = String(value);
        option.textContent = String(value);
        select.appendChild(option);
      }
      select.value = option.value;
      select.dispatchEvent(new Event('change', { bubbles: true }));
    };
    trySet(20);
  };
  const setChecked = (id, checked = true) => {
    const el = document.getElementById(id);
    if (!el) return;
    el.checked = !!checked;
    el.dispatchEvent(new Event('change', { bubbles: true }));
  };
  const selectedId = (name) => document.querySelector(`input[name="${name}"]:checked`)?.id || '';
  const hasAnyWizardValue = (keys) => keys.some((key) => {
    const value = wizard[key];
    if (typeof value === 'boolean') return value;
    if (Array.isArray(value)) return value.length > 0;
    return String(value ?? '').trim() !== '';
  });
  const resetStepFields = (config) => {
    (config.inputs || []).forEach((id) => {
      const el = document.getElementById(id);
      if (!el) return;
      el.setAttribute('autocomplete', 'off');
      el.value = '';
      el.defaultValue = '';
      el.dispatchEvent(new Event('input', { bubbles: true }));
      el.dispatchEvent(new Event('change', { bubbles: true }));
    });
    (config.selects || []).forEach((id) => {
      const el = document.getElementById(id);
      if (!(el instanceof HTMLSelectElement)) return;
      el.selectedIndex = 0;
      el.dispatchEvent(new Event('change', { bubbles: true }));
    });
    (config.radios || []).forEach(({ name, fallbackId }) => {
      document.querySelectorAll(`input[name="${name}"]`).forEach((el) => {
        el.checked = false;
        el.defaultChecked = false;
      });
      if (fallbackId) {
        const fallback = document.getElementById(fallbackId);
        if (fallback) {
          fallback.checked = true;
          fallback.defaultChecked = true;
          fallback.dispatchEvent(new Event('change', { bubbles: true }));
        }
      }
    });
    (config.checkboxes || []).forEach((id) => {
      const el = document.getElementById(id);
      if (!el) return;
      el.checked = false;
      el.defaultChecked = false;
      el.dispatchEvent(new Event('change', { bubbles: true }));
    });
  };
  const clearStepFieldsWithRetry = (config) => {
    const run = () => resetStepFields(config);
    run();
    setTimeout(run, 50);
    setTimeout(run, 250);
  };
  const detailAmenityIds = ['tv', 'washing', 'kitchen', 'ac', 'workspace', 'fridge', 'drying', 'closet', 'patio', 'fireplace', 'shower', 'whirlpool', 'cctv', 'balcony', 'bar'];
  const path = window.location.pathname;

  if (!editId) {
    if (path === '/add-property-location') {
      setSelectByValue('State select', wizard.state);
      setSelectByValue('City select', wizard['select:city-select'] || wizard.city);
      setInput('district', wizard.district);
      setInput('zip', wizard.zip);
      setInput('address', wizard.address);
    }
    if (path === '/add-property-details') {
      if (wizard.ownership) setChecked(String(wizard.ownership));
      setInput('floor', wizard.floor || wizard.floors_total);
      setInput('total-area', wizard.total_area || wizard['total-area']);
      setInput('living-area', wizard.living_area);
      setInput('kitchen-area', wizard.kitchen_area);
      if (wizard.bedrooms || wizard['radio:bedrooms']) setChecked(`bedrooms-${wizard.bedrooms || wizard['radio:bedrooms']}`);
      if (wizard.bathrooms || wizard['radio:bathrooms']) setChecked(`bathrooms-${wizard.bathrooms || wizard['radio:bathrooms']}`);
      if (wizard.parking) setChecked(`parking-${wizard.parking}`);
      detailAmenityIds.forEach((id) => setChecked(id, !!wizard[id]));
    }
    if (path === '/add-property-price') {
      setInput('price', wizard.price);
      setChecked('negotiated', !!wizard.negotiated);
      if (wizard.offer_type === 'private' || wizard.offer_type === 'agent') {
        setChecked('private', wizard.offer_type === 'private');
        setChecked('agent', wizard.offer_type === 'agent');
      }
      setChecked('no-credit', !!wizard.no_credit);
      setChecked('ready-agents', !!wizard.ready_agents);
      setChecked('exchange', !!wizard.exchange);
    }
    if (path === '/add-property-contact-info') {
      setInput('fn', wizard.fn);
      setInput('ln', wizard.ln);
      setInput('email', wizard.email);
      setInput('phone', wizard.phone);
      setChecked('tour', !!wizard.tour);
    }
  }

  if (editId) {
    if (path === '/add-property-location') {
      setSelectByValue('State select', wizard.state);
      setSelectByValue('City select', wizard['select:city-select'] || wizard.city);
      setInput('district', wizard.district);
      setInput('zip', wizard.zip);
      setInput('address', wizard.address);
    }
    if (path === '/add-property-details') {
      if (wizard.ownership) setChecked(String(wizard.ownership));
      setInput('floor', wizard.floor || wizard.floors_total);
      setInput('total-area', wizard.total_area);
      setInput('living-area', wizard.living_area);
      setInput('kitchen-area', wizard.kitchen_area);
      if (wizard.bedrooms) setChecked(`bedrooms-${wizard.bedrooms}`);
      if (wizard.bathrooms) setChecked(`bathrooms-${wizard.bathrooms}`);
      if (wizard.parking) setChecked(`parking-${wizard.parking}`);
    }
    if (path === '/add-property-price') {
      setInput('price', wizard.price);
      setChecked('negotiated', !!wizard.negotiated);
      if (wizard.offer_type === 'private' || wizard.offer_type === 'agent') {
        setChecked('private', wizard.offer_type === 'private');
        setChecked('agent', wizard.offer_type === 'agent');
      }
      setChecked('no-credit', !!wizard.no_credit);
      setChecked('ready-agents', !!wizard.ready_agents);
      setChecked('exchange', !!wizard.exchange);
    }
    if (path === '/add-property-contact-info') {
      setInput('fn', wizard.fn);
      setInput('ln', wizard.ln);
      setInput('email', wizard.email);
      setInput('phone', wizard.phone);
      setChecked('tour', !!wizard.tour);
    }
  }

  if (path === '/add-property-location' && !hasAnyWizardValue([
    'state', 'city', 'select:city-select', 'district', 'zip', 'address'
  ])) {
    clearStepFieldsWithRetry({
      inputs: ['district', 'zip', 'address'],
      selects: ['state', 'city'],
      radios: [],
      checkboxes: [],
    });
  }
  if (path === '/add-property-details' && !hasAnyWizardValue([
    'ownership', 'floor', 'floors_total', 'total_area', 'total-area', 'living_area', 'kitchen_area',
    'radio:bedrooms', 'radio:bathrooms', 'parking', ...detailAmenityIds
  ])) {
    clearStepFieldsWithRetry({
      inputs: ['floor', 'total-area', 'living-area', 'kitchen-area'],
      radios: [
        { name: 'ownership' },
        { name: 'bedrooms' },
        { name: 'bathrooms' },
        { name: 'parking' },
      ],
      checkboxes: detailAmenityIds,
    });
  }
  if (path === '/add-property-price' && !hasAnyWizardValue([
    'price', 'negotiated', 'offer_type', 'no_credit', 'ready_agents', 'exchange'
  ])) {
    clearStepFieldsWithRetry({
      inputs: ['price'],
      radios: [],
      checkboxes: ['negotiated', 'no-credit', 'ready-agents', 'exchange'],
    });
    setChecked('private', false);
    setChecked('agent', false);
  }
  if (path === '/add-property-contact-info' && !hasAnyWizardValue([
    'fn', 'ln', 'email', 'phone', 'tour'
  ])) {
    clearStepFieldsWithRetry({
      inputs: ['fn', 'ln', 'email', 'phone'],
      radios: [],
      checkboxes: ['tour'],
    });
  }

  const buildPayloadForStep = () => {
    const path = window.location.pathname;
    if (path === '/add-property-location') {
      const normalizeStateCode = (rawValue, rawLabel) => {
        const value = String(rawValue || '').trim();
        const label = String(rawLabel || '').trim();
        const nameToCode = (raw) => {
          const key = String(raw || '')
            .replace(/\([A-Za-z]{2}\)\s*$/, '')
            .trim()
            .toLowerCase();
          if (!key) return '';
          const map = {
            'alabama': 'AL',
            'alaska': 'AK',
            'arizona': 'AZ',
            'arkansas': 'AR',
            'california': 'CA',
            'colorado': 'CO',
            'connecticut': 'CT',
            'delaware': 'DE',
            'district of columbia': 'DC',
            'florida': 'FL',
            'georgia': 'GA',
            'hawaii': 'HI',
            'idaho': 'ID',
            'illinois': 'IL',
            'indiana': 'IN',
            'iowa': 'IA',
            'kansas': 'KS',
            'kentucky': 'KY',
            'louisiana': 'LA',
            'maine': 'ME',
            'maryland': 'MD',
            'massachusetts': 'MA',
            'michigan': 'MI',
            'minnesota': 'MN',
            'mississippi': 'MS',
            'missouri': 'MO',
            'montana': 'MT',
            'nebraska': 'NE',
            'nevada': 'NV',
            'new hampshire': 'NH',
            'new jersey': 'NJ',
            'new mexico': 'NM',
            'new york': 'NY',
            'north carolina': 'NC',
            'north dakota': 'ND',
            'ohio': 'OH',
            'oklahoma': 'OK',
            'oregon': 'OR',
            'pennsylvania': 'PA',
            'rhode island': 'RI',
            'south carolina': 'SC',
            'south dakota': 'SD',
            'tennessee': 'TN',
            'texas': 'TX',
            'utah': 'UT',
            'vermont': 'VT',
            'virginia': 'VA',
            'washington': 'WA',
            'west virginia': 'WV',
            'wisconsin': 'WI',
            'wyoming': 'WY',
          };
          return map[key] || '';
        };
        const pick = (s) => {
          const v = String(s || '').trim();
          if (!v) return '';
          const m = v.match(/\(([A-Za-z]{2})\)\s*$/);
          if (m) return m[1].toUpperCase();
          if (/^[A-Za-z]{2}$/.test(v)) return v.toUpperCase();
          return '';
        };
        return pick(value) || pick(label) || nameToCode(value) || nameToCode(label) || '';
      };

      const stateSelect = document.getElementById('state') || document.querySelector('select[aria-label="State select"]');
      const selectedStateLabel = (stateSelect?.querySelector('option:checked')?.textContent || '').trim();
      const citySelect = document.getElementById('city') || document.querySelector('select[aria-label="City select"]');
      const selectedCityLabel = (citySelect?.querySelector('option:checked')?.textContent || '').trim();
      const city = (citySelect?.value || '').trim() || selectedCityLabel;
      return {
        ...readBasePayload(),
        wizard_session: ensureWizardSession(),
        state: normalizeStateCode(stateSelect?.value || '', selectedStateLabel),
        city,
        district: document.getElementById('district')?.value || '',
        zip: document.getElementById('zip')?.value || '',
        address: document.getElementById('address')?.value || '',
        'select:city-select': city,
      };
    }
    if (path === '/add-property-details') {
      const amenityIds = ['tv', 'washing', 'kitchen', 'ac', 'workspace', 'fridge', 'drying', 'closet', 'patio', 'fireplace', 'shower', 'whirlpool', 'cctv', 'balcony', 'bar'];
      const amenities = {};
      amenityIds.forEach((id) => {
        const el = document.getElementById(id);
        if (!el) return;
        amenities[id] = !!el.checked;
      });
      return {
        ...readBasePayload(),
        wizard_session: ensureWizardSession(),
        ownership: selectedId('ownership'),
        floors_total: document.getElementById('floor')?.value || '',
        floor: document.getElementById('floor')?.value || '',
        total_area: document.getElementById('total-area')?.value || '',
        living_area: document.getElementById('living-area')?.value || '',
        kitchen_area: document.getElementById('kitchen-area')?.value || '',
        'total-area': document.getElementById('total-area')?.value || '',
        'radio:bedrooms': selectedId('bedrooms').replace('bedrooms-', ''),
        'radio:bathrooms': selectedId('bathrooms').replace('bathrooms-', ''),
        parking: selectedId('parking').replace('parking-', ''),
        ...amenities,
      };
    }
    if (path === '/add-property-price') {
      return {
        ...readBasePayload(),
        wizard_session: ensureWizardSession(),
        price: document.getElementById('price')?.value || '',
        negotiated: !!document.getElementById('negotiated')?.checked,
        offer_type: document.getElementById('agent')?.checked ? 'agent' : 'private',
        no_credit: !!document.getElementById('no-credit')?.checked,
        ready_agents: !!document.getElementById('ready-agents')?.checked,
        exchange: !!document.getElementById('exchange')?.checked,
      };
    }
    if (path === '/add-property-contact-info') {
      return {
        ...readBasePayload(),
        wizard_session: ensureWizardSession(),
        fn: document.getElementById('fn')?.value || '',
        ln: document.getElementById('ln')?.value || '',
        email: document.getElementById('email')?.value || '',
        phone: document.getElementById('phone')?.value || '',
        tour: !!document.getElementById('tour')?.checked,
      };
    }
    if (path === '/add-property-photos') {
      return {
        ...readBasePayload(),
        wizard_session: ensureWizardSession(),
        video_link: document.getElementById('link')?.value || '',
      };
    }
    return {};
  };
  const buildCurrentWizardSnapshot = () => {
    const currentPath = window.location.pathname;
    if (currentPath === '/add-property') {
      return { ...readStoredWizardData(), ...readBasePayload() };
    }
    return { ...readStoredWizardData(), ...buildPayloadForStep() };
  };
  const persistCurrentStep = () => mergeStoredWizardData(buildCurrentWizardSnapshot());

  sidebarLinks.forEach((link) => {
    if (link.classList.contains('pe-none')) return;
    if (link.dataset.propertySidebarBound === '1') return;
    link.dataset.propertySidebarBound = '1';
    link.addEventListener('click', (event) => {
      if (window.location.pathname === '/add-property-photos' && window.__propertyPhotoInput?.files?.length) {
        event.preventDefault();
        event.stopImmediatePropagation();
        submitStep(link.getAttribute('href') || '', false, link);
        return;
      }
      persistCurrentStep();
    }, true);
  });

  const setActionsSubmitting = (activeBtn = null) => {
    const actions = document.querySelector('.pt-5.d-flex.flex-wrap.gap-3.align-items-center');
    if (actions) {
      actions.dataset.mcSubmitting = '1';
      // Prevent the "both buttons pressed" feel: block clicks without visually disabling every button.
      actions.style.pointerEvents = 'none';
    }
    if (activeBtn) {
      activeBtn.classList.add('disabled');
      activeBtn.setAttribute('aria-disabled', 'true');
      if (activeBtn.tagName === 'BUTTON') activeBtn.disabled = true;
    }
  };
  const clearActionsSubmitting = () => {
    const actions = document.querySelector('.pt-5.d-flex.flex-wrap.gap-3.align-items-center');
    if (actions) {
      delete actions.dataset.mcSubmitting;
      actions.style.pointerEvents = '';
    }
    document.querySelectorAll('.pt-5.d-flex.flex-wrap.gap-3.align-items-center .btn').forEach((btn) => {
      btn.classList.remove('disabled');
      btn.removeAttribute('aria-disabled');
      if (btn.tagName === 'BUTTON') btn.disabled = false;
    });
  };

  // Submit each step as a draft and move forward so required location data persists in DB (and editId exists).
  // The backend merges `wizard_data` across steps, keyed by `wizard_session`/editId.
  const enableStepSubmit = true;

  const submitStep = (nextPath = '', publishNow = false, activeBtn = null) => {
    if (isSubmitting) return;
    const payload = persistCurrentStep();

    // Client-side guard: don't submit location step without required state/city, otherwise backend redirects back
    // with `error=missing-state` and it looks like the button "did nothing".
    if (window.location.pathname === '/add-property-location') {
      const state = String(payload.state || '').trim();
      const city = String(payload.city || payload['select:city-select'] || '').trim();
      if (!state) {
        const stateSelect = document.getElementById('state') || document.querySelector('select[aria-label="State select"]');
        if (stateSelect) {
          stateSelect.classList.add('is-invalid');
          stateSelect.focus();
        }
        if (typeof window.__MC_HIDE_PAGE_LOADER__ === 'function') window.__MC_HIDE_PAGE_LOADER__();
        alert('Please select a state.');
        isSubmitting = false;
        clearActionsSubmitting();
        return;
      }
      if (!city) {
        const citySelect = document.getElementById('city') || document.querySelector('select[aria-label="City select"]');
        if (citySelect) {
          citySelect.classList.add('is-invalid');
          citySelect.focus();
        }
        if (typeof window.__MC_HIDE_PAGE_LOADER__ === 'function') window.__MC_HIDE_PAGE_LOADER__();
        alert('Please select a city.');
        isSubmitting = false;
        clearActionsSubmitting();
        return;
      }
    }

    isSubmitting = true;
    setActionsSubmitting(activeBtn);
    const form = document.createElement('form');
    form.method = 'post';
    form.action = '/submit/property';
    form.enctype = 'multipart/form-data';
    form.innerHTML = `
      <input type="hidden" name="_token" value="__SCRIPT_CSRF__">
      ${publishNow ? '' : '<input type="hidden" name="draft" value="1">'}
      <input type="hidden" name="payload" value="${btoa(unescape(encodeURIComponent(JSON.stringify(payload))))}">
      ${editId ? `<input type="hidden" name="listing_id" value="${editId}">` : ''}
      ${nextPath ? `<input type="hidden" name="next" value="${nextPath}">` : ''}
    `;
    const photoInput = window.__propertyPhotoInput;
    if (photoInput && photoInput.files && photoInput.files.length) {
      photoInput.name = 'photos[]';
      photoInput.multiple = true;
      form.appendChild(photoInput);
    }
    document.body.appendChild(form);
    form.submit();
  };

  // Contact info should only move to ad promotion step.
  if (enableStepSubmit && window.location.pathname === '/add-property-contact-info') {
    const actions = document.querySelector('.pt-5.d-flex.flex-wrap.gap-3.align-items-center');
    if (actions) actions.classList.add('justify-content-start');
    const nextBtn = actions?.querySelector('a.btn.btn-lg.btn-dark');
    const draftBtn = actions?.querySelector('button.btn.btn-lg.btn-outline-secondary');
    const publishBtn = actions?.querySelector('button[data-property-publish]');
    const actionParamKey = 'mc_action';
    const resolveAuthState = async () => {
      try {
        const response = await fetch('/auth/status', {
          credentials: 'same-origin',
          headers: { Accept: 'application/json' },
        });
        if (response.ok) {
          const data = await response.json();
          return !!data?.authenticated;
        }
      } catch (_) {}
      return Boolean(window.__MC_AUTH__);
    };
    const queueFinalActionThenSignIn = (action) => {
      persistCurrentStep();
      try {
        localStorage.setItem(pendingFinalActionKey, String(action));
      } catch (_) {}
      const nextUrl = new URL(window.location.pathname + window.location.search, window.location.origin);
      nextUrl.searchParams.set(actionParamKey, String(action));
      const next = encodeURIComponent(nextUrl.pathname + nextUrl.search);
      window.location.href = `/signin?next=${next}`;
    };
    const runFinalAction = async (action, activeBtn = null) => {
      if (!validateCurrentStepForNext()) {
        return;
      }
      const isAuthenticated = await resolveAuthState();
      if (!isAuthenticated) {
        queueFinalActionThenSignIn(action);
        return;
      }
      if (action === 'draft') {
        submitStep('', false, activeBtn);
        return;
      }
      if (action === 'promotion') {
        submitStep('/add-property-promotion', false, activeBtn);
        return;
      }
      submitStep('', true, activeBtn);
    };
    if (draftBtn && !draftBtn.dataset.propertyDraftBound) {
      draftBtn.dataset.propertyDraftBound = '1';
      draftBtn.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopImmediatePropagation();
        void runFinalAction('draft', event.currentTarget);
      }, true);
    }
    if (publishBtn && !publishBtn.dataset.propertyPublishBound) {
      publishBtn.dataset.propertyPublishBound = '1';
      publishBtn.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopImmediatePropagation();
        void runFinalAction('publish', event.currentTarget);
      }, true);
    }
    if (nextBtn) {
      nextBtn.textContent = 'Go to ad promotion';
      nextBtn.setAttribute('href', '#');
      nextBtn.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopImmediatePropagation();
        void runFinalAction('promotion', event.currentTarget);
      }, true);
    }
    resolveAuthState().then((isAuthenticated) => {
      if (!isAuthenticated) return;
      let pendingAction = '';
      try {
        pendingAction = String(localStorage.getItem(pendingFinalActionKey) || '').trim().toLowerCase();
      } catch (_) {}
      if (!pendingAction) {
        try {
          pendingAction = String(new URLSearchParams(window.location.search).get(actionParamKey) || '').trim().toLowerCase();
        } catch (_) {}
      }
      if (pendingAction === 'draft' || pendingAction === 'promotion' || pendingAction === 'publish') {
        try {
          localStorage.removeItem(pendingFinalActionKey);
        } catch (_) {}
        try {
          const url = new URL(window.location.href);
          url.searchParams.delete(actionParamKey);
          window.history.replaceState({}, '', url.pathname + (url.search ? url.search : ''));
        } catch (_) {}
        window.setTimeout(() => { void runFinalAction(pendingAction); }, 120);
      }
    });
  }

  const nextMap = {
    '/add-property-location': '/add-property-photos',
    '/add-property-photos': '/add-property-details',
    '/add-property-details': '/add-property-price',
    '/add-property-price': '/add-property-contact-info',
  };
  const validateCurrentStepForNext = () => {
    const hidePageLoader = () => {
      if (typeof window.__MC_HIDE_PAGE_LOADER__ === 'function') {
        window.__MC_HIDE_PAGE_LOADER__();
      }
    };
    const reportField = (field) => {
      if (!(field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement)) {
        return true;
      }
      return typeof field.reportValidity === 'function' ? field.reportValidity() : !!String(field.value || '').trim();
    };
    const focusField = (field) => {
      if (!field) return;
      if (typeof field.focus === 'function') field.focus();
      if (typeof field.scrollIntoView === 'function') {
        field.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    };
    const requireChecked = (name, message) => {
      const checked = document.querySelector(`input[name="${name}"]:checked`);
      if (checked) return true;
      const first = document.querySelector(`input[name="${name}"]`);
      if (first instanceof HTMLInputElement) {
        hidePageLoader();
        alert(message);
        focusField(first);
      }
      return false;
    };

    const requiredFields = Array.from(document.querySelectorAll('main input[required], main select[required], main textarea[required]'));
    for (const field of requiredFields) {
      if (!reportField(field)) {
        hidePageLoader();
        focusField(field);
        return false;
      }
    }

    if (path === '/add-property-location') {
      const stateField = document.getElementById('state') || document.querySelector('select[aria-label="State select"]');
      const cityField = document.getElementById('city') || document.querySelector('select[aria-label="City select"]');
      if (!reportField(stateField)) {
        hidePageLoader();
        focusField(stateField);
        return false;
      }
      if (!reportField(cityField)) {
        hidePageLoader();
        focusField(cityField);
        return false;
      }
      return true;
    }

    if (path === '/add-property-price') {
      if (!requireChecked('type', 'Please select whether the listing is private or agent.')) return false;
      return true;
    }

    return true;
  };
  if (path === '/add-property-location') {
    const stateSel = document.getElementById('state') || document.querySelector('select[aria-label="State select"]');
    const citySel = document.getElementById('city') || document.querySelector('select[aria-label="City select"]');
    stateSel?.addEventListener('change', () => stateSel.classList.remove('is-invalid'));
    citySel?.addEventListener('change', () => citySel.classList.remove('is-invalid'));
  }
  if (enableStepSubmit && nextMap[path]) {
    const actions = document.querySelector('.pt-5.d-flex.flex-wrap.gap-3.align-items-center');
    if (actions) actions.classList.add('justify-content-start');
    const replaceWithClone = (el) => {
      if (!el || !el.parentNode) return el;
      const clone = el.cloneNode(true);
      el.parentNode.replaceChild(clone, el);
      return clone;
    };
    const draftBtn = replaceWithClone(actions?.querySelector('button.btn.btn-lg.btn-outline-secondary'));
    const nextBtn = replaceWithClone(actions?.querySelector('a.btn.btn-lg.btn-dark'));
    if (draftBtn) {
      if (!draftBtn.dataset.propertyBound) {
        draftBtn.dataset.propertyBound = '1';
        draftBtn.addEventListener('click', (event) => {
          event.preventDefault();
          event.stopPropagation();
          event.stopImmediatePropagation();
          submitStep('', false, event.currentTarget);
        }, true);
      }
    }
    if (nextBtn) {
      nextBtn.setAttribute('href', withEdit(nextMap[path]));
      if (!nextBtn.dataset.propertyBound) {
        nextBtn.dataset.propertyBound = '1';
        nextBtn.addEventListener('click', (event) => {
          event.preventDefault();
          event.stopPropagation();
          event.stopImmediatePropagation();
          if (!validateCurrentStepForNext()) return;
          if (path === '/add-property-photos' && window.__propertyPhotoInput?.files?.length) {
            submitStep(nextBtn.getAttribute('href') || withEdit(nextMap[path]), false, event.currentTarget);
            return;
          }
          persistCurrentStep();
          window.location.href = nextBtn.getAttribute('href') || withEdit(nextMap[path]);
        }, true);
      }
    }
  }
})();
</script>
HTML;
        $propertyNavScript = str_replace('__PROPERTY_EDIT_DATA__', $propertyEditJson ?: 'null', $propertyNavScript);
        $propertyNavScript = str_replace('__SCRIPT_CSRF__', csrf_token(), $propertyNavScript);
        $html = str_replace('</body>', $propertyNavScript . '</body>', $html);
    }

    if (str_starts_with($file, 'add-contractor-')) {
        $contractorCsrfScript = <<<'HTML'
<script>
window.__mcCsrf = '__SCRIPT_CSRF__';
(function () {
  const disableCustomizerTheme = () => {
    const customizerStyles = document.getElementById('customizer-styles');
    if (customizerStyles) customizerStyles.remove();

    const root = document.documentElement;
    [
      '--fn-primary',
      '--fn-primary-rgb',
      '--fn-primary-text-emphasis',
      '--fn-primary-bg-subtle',
      '--fn-primary-border-subtle',
      '--fn-success',
      '--fn-success-rgb',
      '--fn-success-text-emphasis',
      '--fn-success-bg-subtle',
      '--fn-success-border-subtle',
      '--fn-warning',
      '--fn-warning-rgb',
      '--fn-warning-text-emphasis',
      '--fn-warning-bg-subtle',
      '--fn-warning-border-subtle',
      '--fn-danger',
      '--fn-danger-rgb',
      '--fn-danger-text-emphasis',
      '--fn-danger-bg-subtle',
      '--fn-danger-border-subtle',
      '--fn-info',
      '--fn-info-rgb',
      '--fn-info-text-emphasis',
      '--fn-info-bg-subtle',
      '--fn-info-border-subtle',
      '--fn-border-width',
      '--fn-border-radius',
      '--fn-btn-bg',
      '--fn-btn-border-color',
      '--fn-btn-hover-bg',
      '--fn-btn-hover-border-color',
      '--fn-btn-active-bg',
      '--fn-btn-active-border-color',
      '--fn-btn-disabled-bg',
      '--fn-btn-disabled-border-color',
      '--fn-btn-color',
      '--fn-btn-disabled-color'
    ].forEach((name) => root.style.removeProperty(name));
  };

  const isEdit = new URLSearchParams(window.location.search).get('edit');
  disableCustomizerTheme();
  if (isEdit) return;

  const page = window.location.pathname.replace(/\/+$/, '');

  const clearField = (field) => {
    if (!field) return;
    const tag = field.tagName.toLowerCase();
    const type = String(field.type || '').toLowerCase();
    if (tag === 'select') {
      field.selectedIndex = 0;
      return;
    }
    if (type === 'checkbox' || type === 'radio') {
      field.checked = false;
      return;
    }
    if (type === 'file') {
      try { field.value = ''; } catch (_) {}
      return;
    }
    field.value = '';
  };

  const clearSelectors = (selectors) => {
    selectors.forEach((selector) => {
      document.querySelectorAll(selector).forEach(clearField);
    });
  };

  const clearHours = () => {
    const dayIds = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    dayIds.forEach((dayId) => {
      const toggle = document.getElementById(dayId);
      if (toggle) toggle.checked = false;

      const panel = document.getElementById(dayId + 'Hours');
      if (panel) panel.classList.remove('show');

      document.querySelectorAll('#' + dayId + 'Hours input').forEach(clearField);
    });
  };

  const bootPageDefaults = () => {
    if (page.endsWith('/add-contractor') || page.endsWith('/add-contractor-location')) {
      clearSelectors([
        '#address',
        '#zip',
        '#area-search',
      ]);
      return;
    }

    if (page.endsWith('/add-contractor-services')) {
      clearSelectors([
        'select[name="project-type"]',
        '#rendering',
        '#architectural-design',
        '#bathroom-design',
        '#home-renovations',
        '#floor-leveling',
        '#custom-home-building',
        '#kitchen-remodeling',
      ]);
      return;
    }

    if (page.endsWith('/add-contractor-profile')) {
      clearSelectors([
        '#about',
        '#website',
        'textarea[name="user-info"]',
        'input[name="license-number"]',
        'input[name="first-name"]',
        'input[name="last-name"]',
        'input[name="email"]',
        'input[name="phone"]',
        'input[name="business-name"]',
      ]);
      return;
    }

    if (page.endsWith('/add-contractor-price-hours')) {
      clearSelectors([
        '#price',
        'select[aria-label="Select per period"]',
      ]);
      clearHours();
      return;
    }

    if (page.endsWith('/add-contractor-project')) {
      clearSelectors([
        '#project-name',
        '#project-description',
        '#price',
        '#link',
        'select[aria-label="Select per period"]',
      ]);
    }
  };

  const boot = () => {
    document.querySelectorAll('form').forEach((form) => {
      form.setAttribute('autocomplete', 'off');
    });
    bootPageDefaults();
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
  } else {
    boot();
  }
})();
</script>
HTML;
        $contractorCsrfScript = str_replace('__SCRIPT_CSRF__', csrf_token(), $contractorCsrfScript);
        $html = str_replace('</body>', $contractorCsrfScript . '</body>', $html);
    }

    if ($file === 'add-property-photos.html') {
        $propertyPhotosScript = <<<'HTML'
<script>
(() => {
  const grid = document.querySelector('.row.row-cols-2.row-cols-sm-3');
  if (!grid) return;
  const MIN_IMAGE_WIDTH = 1024;
  const MIN_IMAGE_HEIGHT = 714;
  const editData = window.__propertyEditData || null;
  const existingImages = Array.isArray(editData?.images) ? editData.images : [];
  let nextFileId = 1;
  const selectedFiles = [];
  const syncInputFiles = () => {
    const dt = new DataTransfer();
    selectedFiles.forEach((row) => {
      if (row?.file) dt.items.add(row.file);
    });
    input.files = dt.files;
  };
  const validateImageDimensions = (file) => new Promise((resolve) => {
    if (!(file instanceof File) || !String(file.type || '').startsWith('image/')) {
      resolve({ ok: true });
      return;
    }

    const objectUrl = URL.createObjectURL(file);
    const img = new Image();
    img.onload = () => {
      const width = Number(img.naturalWidth || 0);
      const height = Number(img.naturalHeight || 0);
      URL.revokeObjectURL(objectUrl);
      resolve({
        ok: width >= MIN_IMAGE_WIDTH && height >= MIN_IMAGE_HEIGHT,
        width,
        height,
      });
    };
    img.onerror = () => {
      URL.revokeObjectURL(objectUrl);
      resolve({ ok: false, width: 0, height: 0 });
    };
    img.src = objectUrl;
  });
  const ensureInlineUploadError = () => {
    let errorEl = grid.parentElement?.querySelector('[data-mc-upload-error="property-gallery"]');
    if (errorEl) return errorEl;
    errorEl = document.createElement('div');
    errorEl.className = 'text-danger fs-sm mt-2';
    errorEl.dataset.mcUploadError = 'property-gallery';
    errorEl.style.display = 'none';
    if (grid.parentElement) {
      grid.parentElement.appendChild(errorEl);
    }
    return errorEl;
  };
  const setInlineUploadError = (message) => {
    const errorEl = ensureInlineUploadError();
    if (!errorEl) return;
    errorEl.textContent = String(message || '').trim();
    errorEl.style.display = errorEl.textContent ? '' : 'none';
  };

  let uploadCol = Array.from(grid.children).find((col) =>
    (col.textContent || '').toLowerCase().includes('upload photos / videos')
  );
  if (!uploadCol) return;

  // Hard reset upload tile to remove any previously bound listeners from static assets.
  const freshUploadCol = uploadCol.cloneNode(true);
  uploadCol.parentNode?.replaceChild(freshUploadCol, uploadCol);
  uploadCol = freshUploadCol;
  uploadCol.querySelectorAll('input[type="file"]').forEach((el) => el.remove());

  // Remove template default photos; keep only upload tile.
  Array.from(grid.children).forEach((col) => {
    if (col !== uploadCol) col.remove();
  });

  const input = document.createElement('input');
  input.type = 'file';
  input.accept = 'image/*,video/*';
  input.multiple = true;
  input.className = 'position-absolute top-0 start-0 w-100 h-100 opacity-0';
  input.style.zIndex = '5';
  input.style.cursor = 'pointer';
  input.name = 'photos[]';
  const uploadTile = uploadCol.querySelector('.d-flex.align-items-center.justify-content-center.position-relative.h-100.cursor-pointer') || uploadCol;
  uploadTile.appendChild(input);
  window.__propertyPhotoInput = input;

  const linkInput = document.getElementById('link');
  const linkWrap = linkInput ? (linkInput.closest('.pt-3.mt-3') || linkInput.closest('div')) : null;
  if (linkWrap) linkWrap.remove();

  input.addEventListener('click', () => {
    input.value = '';
  });

  const makeCard = (src, isVideo, fileId = null) => {
    const col = document.createElement('div');
    col.className = 'col';
    col.innerHTML = `
      <div class="hover-effect-opacity position-relative overflow-hidden rounded">
        <div class="ratio" style="--fn-aspect-ratio: calc(180 / 268 * 100%)">
          ${isVideo
            ? `<video src="${src}" class="w-100 h-100 object-fit-cover" muted controls></video>`
            : `<img src="${src}" alt="Uploaded image" class="w-100 h-100 object-fit-cover">`}
        </div>
        <div class="hover-effect-target position-absolute top-0 start-0 d-flex align-items-center justify-content-center w-100 h-100 opacity-0">
          <button type="button" class="btn btn-icon btn-sm btn-light position-relative z-2" aria-label="Remove">
            <i class="fi-trash fs-base"></i>
          </button>
          <span class="position-absolute top-0 start-0 w-100 h-100 bg-black bg-opacity-25 z-1"></span>
        </div>
      </div>
    `;
    const removeBtn = col.querySelector('button[aria-label="Remove"]');
    removeBtn?.addEventListener('click', () => {
      if (fileId !== null) {
        const idx = selectedFiles.findIndex((row) => row.id === fileId);
        if (idx >= 0) selectedFiles.splice(idx, 1);
        syncInputFiles();
      }
      col.remove();
    });
    return col;
  };

  // Existing static delete buttons.
  grid.querySelectorAll('button[aria-label="Remove"]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const col = btn.closest('.col');
      if (col && col !== uploadCol) col.remove();
    });
  });

  input.addEventListener('change', async () => {
    const files = Array.from(input.files || []);
    let hasDimensionError = false;
    for (const file of files) {
      const dimensionCheck = await validateImageDimensions(file);
      if (!dimensionCheck.ok) {
        hasDimensionError = true;
        const current = dimensionCheck.width > 0 && dimensionCheck.height > 0
          ? ` Selected image is ${dimensionCheck.width}x${dimensionCheck.height}.`
          : '';
        setInlineUploadError(`Image must be at least ${MIN_IMAGE_WIDTH}x${MIN_IMAGE_HEIGHT}.${current}`);
        continue;
      }
      setInlineUploadError('');
      const id = nextFileId++;
      selectedFiles.push({ id, file });
      const src = URL.createObjectURL(file);
      const isVideo = file.type.startsWith('video/');
      const card = makeCard(src, isVideo, id);
      grid.insertBefore(card, uploadCol);
    }
    if (!hasDimensionError && files.length) {
      setInlineUploadError('');
    }
    syncInputFiles();
  });

  // Editing: show existing saved images first.
  existingImages.forEach((src, idx) => {
    const isVideo = /\.(mp4|mov|webm)$/i.test(String(src || ''));
    const card = makeCard(String(src || ''), isVideo);
    const badge = card.querySelector('.badge');
    if (idx === 0 && !badge) {
      const span = document.createElement('span');
      span.className = 'badge text-bg-light position-absolute top-0 start-0 z-3 mt-2 ms-2';
      span.textContent = 'Cover';
      card.querySelector('.hover-effect-opacity')?.prepend(span);
    }
    grid.insertBefore(card, uploadCol);
  });
})();
</script>
HTML;
        $html = str_replace('</body>', $propertyPhotosScript . '</body>', $html);
    }

    if ($file === 'add-property-promotion.html' && $currentRequestPath === '/add-property-promotion') {
        $propertyPromotionScript = <<<'HTML'
<script>
(() => {
  const search = new URLSearchParams(window.location.search);
  const editData = window.__propertyEditData || null;
  const editId = (search.get('edit') || (editData?.id ? String(editData.id) : '')).trim();
  const serverWizardData = (editData && editData.wizard_data && typeof editData.wizard_data === 'object')
    ? editData.wizard_data
    : {};
  const queryPackage = (search.get('package') || '').trim().toLowerCase();
  const wizardKey = 'propertyWizardSession';
  const wizardDataKey = 'propertyWizardData';
  const pendingFinalActionKey = 'propertyPendingFinalAction';
  const packageConfigs = [
    {
      slug: 'easy-start',
      name: 'Easy Start',
      price: 25,
      period: '/ month',
      description: "Ideal if you're testing the waters and want to start with basic exposure.",
      buttonLabel: 'Select Easy Start',
      includesLabel: '',
      features: [
        '7-Day Run for your ad active for one week',
        'Keep your ad live and active for one week',
        'Track views and basic engagement metrics',
      ],
    },
    {
      slug: 'fast-sale',
      name: 'Fast Sale',
      price: 49,
      period: '/ month',
      description: 'Perfect for serious sellers who want more exposure and detailed insights.',
      buttonLabel: 'Select Fast Sale',
      includesLabel: 'Includes everything in Easy Start +',
      features: [
        '14-Day Run for your ad active for two weeks',
        'Detailed user engagement analytics',
        'Dedicated assistance from our support team',
      ],
    },
    {
      slug: 'turbo-boost',
      name: 'Turbo Boost',
      price: 70,
      period: '/ month',
      description: 'Best for ambitious sellers who want maximum exposure and advanced insights.',
      buttonLabel: 'Select Turbo Boost',
      includesLabel: 'Includes everything in Fast Sale +',
      features: [
        '28-Day Run for your ad active for three weeks',
        'Advanced comprehensive data analysis',
        'Personalized assistance from our manager',
      ],
    },
  ];
  const packageSlugs = packageConfigs.map((pkg) => pkg.slug);
  const serviceConfig = {
    certify: 'Check and certify my ad by Monaclick experts',
    lifts: '10 lifts to the top of the list (daily, 7 days)',
    analytics: 'Detailed user engagement analytics',
  };
  const readStoredWizardData = () => {
    try {
      const raw = localStorage.getItem(wizardDataKey);
      const parsed = raw ? JSON.parse(raw) : null;
      return parsed && typeof parsed === 'object' ? parsed : {};
    } catch (_) {
      return {};
    }
  };
  const writeStoredWizardData = (payload) => {
    try {
      localStorage.setItem(wizardDataKey, JSON.stringify(payload || {}));
    } catch (_) {}
  };
  const getWizardData = () => ({ ...readStoredWizardData(), ...serverWizardData });
  let currentPackage = queryPackage;
  if (!packageSlugs.includes(currentPackage)) {
    const wizardData = getWizardData();
    currentPackage = String(wizardData.promotion_package || wizardData.package || '').trim().toLowerCase();
  }
  if (!packageSlugs.includes(currentPackage)) {
    currentPackage = 'fast-sale';
  }
  const ensureWizardSession = () => {
    try {
      let value = localStorage.getItem(wizardKey);
      if (!value) {
        value = `${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
        localStorage.setItem(wizardKey, value);
      }
      return value;
    } catch (_) {
      return `${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
    }
  };

  const setSubmitting = () => {
    document.querySelectorAll('.pt-5.d-flex.flex-wrap.gap-3.align-items-center .btn').forEach((btn) => {
      btn.classList.add('disabled');
      btn.setAttribute('aria-disabled', 'true');
      if (btn.tagName === 'BUTTON') btn.disabled = true;
    });
  };

  const syncServiceSelectionsFromWizard = () => {
    const wizardData = getWizardData();
    Object.keys(serviceConfig).forEach((key) => {
      const input = document.getElementById(key);
      if (!(input instanceof HTMLInputElement)) return;
      input.checked = !!wizardData[`service_${key}`];
    });
  };

  const selectedServicesPayload = () => {
    const payload = {};
    Object.keys(serviceConfig).forEach((key) => {
      const input = document.getElementById(key);
      payload[`service_${key}`] = !!(input instanceof HTMLInputElement && input.checked);
    });
    return payload;
  };

  const updateSelectedPackageUrl = () => {
    const url = new URL(window.location.href);
    url.searchParams.set('package', currentPackage);
    window.history.replaceState({}, '', url.toString());
  };
  const persistPromotionSelection = () => {
    const wizardData = getWizardData();
    writeStoredWizardData({
      ...wizardData,
      wizard_session: ensureWizardSession(),
      promotion_package: currentPackage,
      package: currentPackage,
      ...selectedServicesPayload(),
    });
  };
  const requireWizardValues = (keys, message, redirectTo = '/add-property-contact-info') => {
    const wizardData = getWizardData();
    const missing = keys.some((key) => String(wizardData[key] || '').trim() === '');
    if (!missing) return true;
    alert(message);
    window.location.href = editId
      ? `${redirectTo}?edit=${encodeURIComponent(editId)}`
      : redirectTo;
    return false;
  };

  const updatePlans = () => {
    const planCards = Array.from(document.querySelectorAll('.overflow-x-auto .card .card-body'))
      .filter((card) => card.querySelector('h3.fs-lg.fw-normal'));
    const buttonThemeClasses = [
      'btn-primary', 'btn-outline-primary',
      'btn-secondary', 'btn-outline-secondary',
      'btn-success', 'btn-outline-success',
      'btn-danger', 'btn-outline-danger',
      'btn-warning', 'btn-outline-warning',
      'btn-info', 'btn-outline-info',
      'btn-light', 'btn-outline-light',
      'btn-dark', 'btn-outline-dark',
    ];
    const backgroundThemeClasses = [
      'bg-primary', 'bg-secondary', 'bg-success', 'bg-danger',
      'bg-warning', 'bg-info', 'bg-light', 'bg-dark',
    ];

    packageConfigs.forEach((pkg, index) => {
      const card = planCards[index];
      if (!card) return;
      const cardShell = card.closest('.card');
      const featuredWrap = card.closest('.w-100')?.querySelector('.position-absolute.top-0.start-0.rounded-5.ms-n1');

      const title = card.querySelector('h3.fs-lg.fw-normal');
      const price = card.querySelector('.h1.mb-0');
      const period = card.querySelector('.fs-sm.ms-2');
      const description = card.querySelector('p.fs-sm');
      const cta = card.querySelector('.btn.btn-lg.w-100');
      const includeLabel = card.querySelector('.h6.fs-sm');
      const featureList = card.querySelector('ul.list-unstyled');

      if (title) title.textContent = pkg.name;
      if (price) price.textContent = `$${pkg.price}`;
      if (period) period.textContent = pkg.period;
      if (description) description.textContent = pkg.description;

      if (cta) {
        cta.textContent = pkg.buttonLabel;
        cta.dataset.packageSlug = pkg.slug;
        cta.setAttribute('type', 'button');
        const isSelected = currentPackage === pkg.slug;
        const baseCtaClasses = String(
          cta.dataset.baseClasses
          || Array.from(cta.classList).filter((className) => !buttonThemeClasses.includes(className)).join(' ')
        ).trim();
        cta.dataset.baseClasses = baseCtaClasses;
        cta.className = `${baseCtaClasses} ${isSelected ? 'btn-info' : 'btn-outline-info'}`.trim();
        cta.textContent = isSelected ? `${pkg.name} selected` : pkg.buttonLabel;
        if (!cta.dataset.packageBound) {
          cta.dataset.packageBound = '1';
          cta.addEventListener('click', () => {
            currentPackage = pkg.slug;
            updateSelectedPackageUrl();
            persistPromotionSelection();
            updatePlans();
          });
        }
      }

      if (cardShell) {
        cardShell.classList.toggle('shadow', currentPackage === pkg.slug);
        cardShell.style.boxShadow = currentPackage === pkg.slug
          ? 'inset 0 0 0 3px rgba(var(--fn-info-rgb), .85)'
          : '';
      }

      if (featuredWrap) {
        featuredWrap.classList.remove(...backgroundThemeClasses);
        featuredWrap.classList.add('bg-info');
        featuredWrap.style.opacity = currentPackage === pkg.slug ? '1' : '.18';
      }

      if (includeLabel) {
        if (pkg.includesLabel) {
          includeLabel.textContent = pkg.includesLabel;
          includeLabel.classList.remove('d-none');
        } else {
          includeLabel.textContent = '';
          includeLabel.classList.add('d-none');
        }
      }

      if (featureList) {
        featureList.innerHTML = pkg.features.map((feature) => `
          <li class="d-flex">
            <i class="fi-check fs-base text-body-secondary me-2" style="margin-top: 3px"></i>
            ${feature}
          </li>
        `).join('');
      }
    });
  };

  const submitProperty = (publishNow) => {
    if (!requireWizardValues(['state', 'city', 'address', 'zip'], 'Please complete the location step before publishing.', '/add-property-location')) return;
    if (!requireWizardValues(['price', 'offer_type'], 'Please complete the price step before publishing.', '/add-property-price')) return;
    if (!requireWizardValues(['fn', 'ln', 'email', 'phone'], 'Please complete the contact info step before publishing.', '/add-property-contact-info')) return;
    setSubmitting();
    persistPromotionSelection();
    const wizardData = getWizardData();
    const payload = {
      ...wizardData,
      wizard_session: ensureWizardSession(),
      promotion_package: currentPackage,
      package: currentPackage,
      ...selectedServicesPayload(),
    };
    const form = document.createElement('form');
    form.method = 'post';
    form.action = '/submit/property';
    form.innerHTML = `
      <input type="hidden" name="_token" value="__SCRIPT_CSRF__">
      ${publishNow ? '' : '<input type="hidden" name="draft" value="1">'}
      <input type="hidden" name="payload" value="${btoa(unescape(encodeURIComponent(JSON.stringify(payload))))}">
      ${editId ? `<input type="hidden" name="listing_id" value="${editId}">` : ''}
    `;
    document.body.appendChild(form);
    form.submit();
  };

  const draftBtn = Array.from(document.querySelectorAll('button.btn.btn-lg.btn-outline-secondary'))
    .find((btn) => (btn.textContent || '').trim().toLowerCase() === 'save draft');
  const actionParamKey = 'mc_action';
  const resolveAuthState = async () => {
    try {
      const response = await fetch('/auth/status', {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      });
      if (response.ok) {
        const data = await response.json();
        return !!data?.authenticated;
      }
    } catch (_) {}
    return Boolean(window.__MC_AUTH__);
  };
  const queueFinalActionThenSignIn = (action) => {
    persistPromotionSelection();
    try {
      localStorage.setItem(pendingFinalActionKey, String(action));
    } catch (_) {}
    const nextUrl = new URL(window.location.pathname + window.location.search, window.location.origin);
    nextUrl.searchParams.set(actionParamKey, String(action));
    const next = encodeURIComponent(nextUrl.pathname + nextUrl.search);
    window.location.href = `/signin?next=${next}`;
  };
  const runFinalAction = async (action) => {
    const isAuthenticated = await resolveAuthState();
    if (!isAuthenticated) {
      queueFinalActionThenSignIn(action);
      return;
    }
    submitProperty(action === 'publish');
  };
  if (draftBtn) {
    draftBtn.addEventListener('click', (event) => {
      event.preventDefault();
      void runFinalAction('draft');
    });
  }

  const publishBtn = Array.from(document.querySelectorAll('a.btn.btn-lg.btn-primary[href]'))
    .find((btn) => (btn.textContent || '').trim().toLowerCase().includes('publish property listing'));
  if (publishBtn) {
    publishBtn.setAttribute('href', '#');
    publishBtn.addEventListener('click', (event) => {
      event.preventDefault();
      void runFinalAction('publish');
    });
  }

  Object.keys(serviceConfig).forEach((key) => {
    const input = document.getElementById(key);
    if (!(input instanceof HTMLInputElement)) return;
    input.addEventListener('change', persistPromotionSelection);
  });

  updateSelectedPackageUrl();
  syncServiceSelectionsFromWizard();
  persistPromotionSelection();
  updatePlans();

  resolveAuthState().then((isAuthenticated) => {
    if (!isAuthenticated) return;
    let pendingAction = '';
    try {
      pendingAction = String(localStorage.getItem(pendingFinalActionKey) || '').trim().toLowerCase();
    } catch (_) {}
    if (!pendingAction) {
      try {
        pendingAction = String(new URLSearchParams(window.location.search).get(actionParamKey) || '').trim().toLowerCase();
      } catch (_) {}
    }
    if (pendingAction === 'draft' || pendingAction === 'publish') {
      try {
        localStorage.removeItem(pendingFinalActionKey);
      } catch (_) {}
      try {
        const url = new URL(window.location.href);
        url.searchParams.delete(actionParamKey);
        window.history.replaceState({}, '', url.pathname + (url.search ? url.search : ''));
      } catch (_) {}
      window.setTimeout(() => { void runFinalAction(pendingAction); }, 120);
    }
  });
})();
</script>
HTML;
        $propertyPromotionScript = str_replace('__SCRIPT_CSRF__', csrf_token(), $propertyPromotionScript);
        $html = str_replace('</body>', $propertyPromotionScript . '</body>', $html);
    }

    if ($file === 'add-property-promotion.html' && $currentRequestPath === '/add-contractor-promotion') {
        $contractorPromotionPayload = null;
        $editId = (int) request()->query('edit', 0);
        if ($editId > 0 && Auth::check()) {
            $editListing = Listing::query()
                ->where('id', $editId)
                ->where('module', 'contractors')
                ->where('user_id', Auth::id())
                ->first();

            if ($editListing) {
                $featureTokens = collect(is_array($editListing->features) ? $editListing->features : [])
                    ->map(fn ($value) => trim((string) $value))
                    ->filter();

                $savedPackage = strtolower((string) ($featureTokens->first(fn ($token) => str_starts_with($token, 'promo-package:')) ?? ''));
                if ($savedPackage !== '') {
                    $savedPackage = substr($savedPackage, strlen('promo-package:'));
                }

                $contractorPromotionPayload = [
                    'promotion_package' => $savedPackage,
                    'service_certify' => $featureTokens->contains('promo-service:certify'),
                    'service_lifts' => $featureTokens->contains('promo-service:lifts'),
                    'service_analytics' => $featureTokens->contains('promo-service:analytics'),
                ];
            }
        }
        $contractorPromotionJson = json_encode($contractorPromotionPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        $contractorPromotionScript = <<<'HTML'
<script>
(() => {
  const search = new URLSearchParams(window.location.search);
  const editId = (search.get('edit') || '').trim();
  const savedSelection = __CONTRACTOR_PROMOTION_DATA__ || null;
  const packageConfigs = [
    {
      slug: 'easy-start',
      name: 'Easy Start',
      price: 25,
      period: '/ month',
      description: "Ideal if you're testing the waters and want to start with basic exposure.",
      buttonLabel: 'Select Easy Start',
      includesLabel: '',
      features: [
        '7-Day Run for your contractor listing',
        'Keep your project visible for one week',
        'Track basic views and engagement',
      ],
    },
    {
      slug: 'fast-sale',
      name: 'Fast Sale',
      price: 49,
      period: '/ month',
      description: 'Perfect for contractors who want stronger exposure and better lead quality.',
      buttonLabel: 'Select Fast Sale',
      includesLabel: 'Includes everything in Easy Start +',
      features: [
        '14-Day Run for your contractor listing',
        'Detailed user engagement analytics',
        'Priority support for your campaign',
      ],
    },
    {
      slug: 'turbo-boost',
      name: 'Turbo Boost',
      price: 70,
      period: '/ month',
      description: 'Best for maximum exposure and premium visibility across the marketplace.',
      buttonLabel: 'Select Turbo Boost',
      includesLabel: 'Includes everything in Fast Sale +',
      features: [
        '28-Day Run for your contractor listing',
        'Advanced analytics and reporting',
        'Dedicated manager assistance',
      ],
    },
  ];
  const serviceConfig = {
    certify: 'Check and certify my business by Monaclick experts',
    lifts: '10 lifts to the top of the list (daily, 7 days)',
    analytics: 'Detailed user engagement analytics',
  };
  let currentPackage = String(savedSelection?.promotion_package || '').trim().toLowerCase();
  if (!packageConfigs.some((pkg) => pkg.slug === currentPackage)) {
    currentPackage = (search.get('package') || '').trim().toLowerCase();
  }
  if (!packageConfigs.some((pkg) => pkg.slug === currentPackage)) {
    currentPackage = 'fast-sale';
  }

  const applySavedSelection = (data) => {
    const savedPackage = String(data?.promotion_package || '').trim().toLowerCase();
    if (packageConfigs.some((pkg) => pkg.slug === savedPackage)) {
      currentPackage = savedPackage;
    }

    Object.keys(serviceConfig).forEach((key) => {
      const input = document.getElementById(key);
      if (!(input instanceof HTMLInputElement)) return;
      input.checked = !!data?.[`service_${key}`];
    });
  };

  const setSubmitting = () => {
    document.querySelectorAll('.pt-5.d-flex.flex-wrap.gap-3.align-items-center .btn').forEach((btn) => {
      btn.classList.add('disabled');
      btn.setAttribute('aria-disabled', 'true');
      if (btn.tagName === 'BUTTON') btn.disabled = true;
    });
  };

  const updateSelectedPackageUrl = () => {
    const url = new URL(window.location.href);
    url.searchParams.set('package', currentPackage);
    window.history.replaceState({}, '', url.toString());
  };

  const selectedServicesPayload = () => {
    const payload = {};
    Object.keys(serviceConfig).forEach((key) => {
      const input = document.getElementById(key);
      payload[`service_${key}`] = !!(input instanceof HTMLInputElement && input.checked);
    });
    return payload;
  };

  const updatePlans = () => {
    const planCards = Array.from(document.querySelectorAll('.overflow-x-auto .card .card-body'))
      .filter((card) => card.querySelector('h3.fs-lg.fw-normal'));
    const buttonThemeClasses = [
      'btn-primary', 'btn-outline-primary',
      'btn-secondary', 'btn-outline-secondary',
      'btn-success', 'btn-outline-success',
      'btn-danger', 'btn-outline-danger',
      'btn-warning', 'btn-outline-warning',
      'btn-info', 'btn-outline-info',
      'btn-light', 'btn-outline-light',
      'btn-dark', 'btn-outline-dark',
    ];
    const backgroundThemeClasses = [
      'bg-primary', 'bg-secondary', 'bg-success', 'bg-danger',
      'bg-warning', 'bg-info', 'bg-light', 'bg-dark',
    ];

    packageConfigs.forEach((pkg, index) => {
      const card = planCards[index];
      if (!card) return;
      const cardShell = card.closest('.card');
      const featuredWrap = card.closest('.w-100')?.querySelector('.position-absolute.top-0.start-0.rounded-5.ms-n1');
      const title = card.querySelector('h3.fs-lg.fw-normal');
      const price = card.querySelector('.h1.mb-0');
      const period = card.querySelector('.fs-sm.ms-2');
      const description = card.querySelector('p.fs-sm');
      const cta = card.querySelector('.btn.btn-lg.w-100');
      const includeLabel = card.querySelector('.h6.fs-sm');
      const featureList = card.querySelector('ul.list-unstyled');

      if (title) title.textContent = pkg.name;
      if (price) price.textContent = `$${pkg.price}`;
      if (period) period.textContent = pkg.period;
      if (description) description.textContent = pkg.description;

      if (cta) {
        cta.textContent = pkg.buttonLabel;
        cta.setAttribute('type', 'button');
        const isSelected = currentPackage === pkg.slug;
        const baseCtaClasses = String(
          cta.dataset.baseClasses
          || Array.from(cta.classList).filter((className) => !buttonThemeClasses.includes(className)).join(' ')
        ).trim();
        cta.dataset.baseClasses = baseCtaClasses;
        cta.className = `${baseCtaClasses} ${isSelected ? 'btn-info' : 'btn-outline-info'}`.trim();
        cta.textContent = isSelected ? `${pkg.name} selected` : pkg.buttonLabel;
        if (!cta.dataset.packageBound) {
          cta.dataset.packageBound = '1';
          cta.addEventListener('click', () => {
            currentPackage = pkg.slug;
            updateSelectedPackageUrl();
            updatePlans();
          });
        }
      }

      if (cardShell) {
        cardShell.classList.toggle('shadow', currentPackage === pkg.slug);
        cardShell.style.boxShadow = currentPackage === pkg.slug
          ? 'inset 0 0 0 3px rgba(var(--fn-info-rgb), .85)'
          : '';
      }

      if (featuredWrap) {
        featuredWrap.classList.remove(...backgroundThemeClasses);
        featuredWrap.classList.add('bg-info');
        featuredWrap.style.opacity = currentPackage === pkg.slug ? '1' : '.18';
      }

      if (includeLabel) {
        if (pkg.includesLabel) {
          includeLabel.textContent = pkg.includesLabel;
          includeLabel.classList.remove('d-none');
        } else {
          includeLabel.textContent = '';
          includeLabel.classList.add('d-none');
        }
      }

      if (featureList) {
        featureList.innerHTML = pkg.features.map((feature) => `
          <li class="d-flex">
            <i class="fi-check fs-base text-body-secondary me-2" style="margin-top: 3px"></i>
            ${feature}
          </li>
        `).join('');
      }
    });
  };

  const submitContractor = (publishNow) => {
    if (!editId) {
      window.location.href = '/add-contractor-project';
      return;
    }
    setSubmitting();
    const payload = {
      package: currentPackage,
      promotion_package: currentPackage,
      ...selectedServicesPayload(),
    };
    const form = document.createElement('form');
    form.method = 'post';
    form.action = '/submit/contractor';
    form.innerHTML = `
      <input type="hidden" name="_token" value="__SCRIPT_CSRF__">
      ${publishNow ? '' : '<input type="hidden" name="draft" value="1">'}
      <input type="hidden" name="payload" value="${btoa(unescape(encodeURIComponent(JSON.stringify(payload))))}">
      <input type="hidden" name="listing_id" value="${editId}">
    `;
    document.body.appendChild(form);
    form.submit();
  };

  const heading = document.querySelector('h1');
  if (heading) heading.textContent = 'Choose your plan';

  const lead = Array.from(document.querySelectorAll('p'))
    .find((p) => (p.textContent || '').toLowerCase().includes('choose the package that best matches your promotion goals'));
  if (lead) lead.textContent = 'Choose a package for your contractor listing, then publish when you are ready.';

  const publishBtn = Array.from(document.querySelectorAll('a.btn.btn-lg.btn-primary[href]'))
    .find((btn) => (btn.textContent || '').trim().toLowerCase().includes('publish property listing'));
  if (publishBtn) {
    publishBtn.textContent = 'Publish listing';
    publishBtn.setAttribute('href', '#');
    publishBtn.addEventListener('click', (event) => {
      event.preventDefault();
      submitContractor(true);
    });
  }

  const draftBtn = Array.from(document.querySelectorAll('button.btn.btn-lg.btn-outline-secondary'))
    .find((btn) => (btn.textContent || '').trim().toLowerCase() === 'save draft');
  if (draftBtn) {
    draftBtn.addEventListener('click', (event) => {
      event.preventDefault();
      submitContractor(false);
    });
  }

  const initSelection = () => {
    if (savedSelection) applySavedSelection(savedSelection);
    updateSelectedPackageUrl();
    updatePlans();
  };

  initSelection();
})();
</script>
HTML;
        $contractorPromotionScript = str_replace('__SCRIPT_CSRF__', csrf_token(), $contractorPromotionScript);
        $contractorPromotionScript = str_replace('__CONTRACTOR_PROMOTION_DATA__', $contractorPromotionJson ?: 'null', $contractorPromotionScript);
        $html = str_replace('</body>', $contractorPromotionScript . '</body>', $html);
    }

    if ($file === 'add-property-promotion.html' && $currentRequestPath === '/add-car-promotion') {
        $carPromotionPayload = null;
        $editId = (int) request()->query('edit', 0);
        if ($editId > 0 && Auth::check()) {
            $editListing = Listing::query()
                ->where('id', $editId)
                ->where('module', 'cars')
                ->where('user_id', Auth::id())
                ->first();

            if ($editListing) {
                $featureTokens = collect(is_array($editListing->features) ? $editListing->features : [])
                    ->map(fn ($value) => trim((string) $value))
                    ->filter();

                $savedPackage = strtolower((string) ($featureTokens->first(fn ($token) => str_starts_with($token, 'promo-package:')) ?? ''));
                if ($savedPackage !== '') {
                    $savedPackage = substr($savedPackage, strlen('promo-package:'));
                }

                $carPromotionPayload = [
                    'promotion_package' => $savedPackage,
                    'service_certify' => $featureTokens->contains('promo-service:certify'),
                    'service_lifts' => $featureTokens->contains('promo-service:lifts'),
                    'service_analytics' => $featureTokens->contains('promo-service:analytics'),
                ];
            }
        }
        $carPromotionJson = json_encode($carPromotionPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        $carPromotionScript = <<<'HTML'
<script>
(() => {
  const search = new URLSearchParams(window.location.search);
  const editId = (search.get('edit') || '').trim();
  const savedSelection = __CAR_PROMOTION_DATA__ || null;
  const packageConfigs = [
    {
      slug: 'easy-start',
      name: 'Easy Start',
      price: 25,
      period: '/ month',
      description: "Ideal if you're testing the waters and want to start with basic exposure.",
      buttonLabel: 'Select Easy Start',
      includesLabel: '',
      features: [
        '7-Day Run for your car listing',
        'Keep your car visible for one week',
        'Track basic views and engagement',
      ],
    },
    {
      slug: 'fast-sale',
      name: 'Fast Sale',
      price: 49,
      period: '/ month',
      description: 'Perfect for sellers who want stronger exposure and better buyer interest.',
      buttonLabel: 'Select Fast Sale',
      includesLabel: 'Includes everything in Easy Start +',
      features: [
        '14-Day Run for your car listing',
        'Detailed user engagement analytics',
        'Priority visibility for your listing',
      ],
    },
    {
      slug: 'turbo-boost',
      name: 'Turbo Boost',
      price: 70,
      period: '/ month',
      description: 'Best for maximum visibility and premium promotion across the marketplace.',
      buttonLabel: 'Select Turbo Boost',
      includesLabel: 'Includes everything in Fast Sale +',
      features: [
        '28-Day Run for your car listing',
        'Advanced analytics and reporting',
        'Premium spotlight placement',
      ],
    },
  ];
  const serviceConfig = {
    certify: 'Check and certify my ad by Monaclick experts',
    lifts: '10 lifts to the top of the list (daily, 7 days)',
    analytics: 'Detailed user engagement analytics',
  };
  let currentPackage = String(savedSelection?.promotion_package || '').trim().toLowerCase();
  if (!packageConfigs.some((pkg) => pkg.slug === currentPackage)) {
    currentPackage = (search.get('package') || '').trim().toLowerCase();
  }
  if (!packageConfigs.some((pkg) => pkg.slug === currentPackage)) {
    currentPackage = 'fast-sale';
  }

  const applySavedSelection = (data) => {
    const savedPackage = String(data?.promotion_package || '').trim().toLowerCase();
    if (packageConfigs.some((pkg) => pkg.slug === savedPackage)) {
      currentPackage = savedPackage;
    }

    Object.keys(serviceConfig).forEach((key) => {
      const input = document.getElementById(key);
      if (!(input instanceof HTMLInputElement)) return;
      input.checked = !!data?.[`service_${key}`];
    });
  };

  const setSubmitting = () => {
    document.querySelectorAll('.pt-5.d-flex.flex-wrap.gap-3.align-items-center .btn').forEach((btn) => {
      btn.classList.add('disabled');
      btn.setAttribute('aria-disabled', 'true');
      if (btn.tagName === 'BUTTON') btn.disabled = true;
    });
  };

  const updateSelectedPackageUrl = () => {
    const url = new URL(window.location.href);
    url.searchParams.set('package', currentPackage);
    window.history.replaceState({}, '', url.toString());
  };

  const selectedServicesPayload = () => {
    const payload = {};
    Object.keys(serviceConfig).forEach((key) => {
      const input = document.getElementById(key);
      payload[`service_${key}`] = !!(input instanceof HTMLInputElement && input.checked);
    });
    return payload;
  };

  const updatePlans = () => {
    const planCards = Array.from(document.querySelectorAll('.overflow-x-auto .card .card-body'))
      .filter((card) => card.querySelector('h3.fs-lg.fw-normal'));
    const buttonThemeClasses = [
      'btn-primary', 'btn-outline-primary',
      'btn-secondary', 'btn-outline-secondary',
      'btn-success', 'btn-outline-success',
      'btn-danger', 'btn-outline-danger',
      'btn-warning', 'btn-outline-warning',
      'btn-info', 'btn-outline-info',
      'btn-light', 'btn-outline-light',
      'btn-dark', 'btn-outline-dark',
    ];
    const backgroundThemeClasses = [
      'bg-primary', 'bg-secondary', 'bg-success', 'bg-danger',
      'bg-warning', 'bg-info', 'bg-light', 'bg-dark',
    ];

    packageConfigs.forEach((pkg, index) => {
      const card = planCards[index];
      if (!card) return;
      const cardShell = card.closest('.card');
      const featuredWrap = card.closest('.w-100')?.querySelector('.position-absolute.top-0.start-0.rounded-5.ms-n1');
      const title = card.querySelector('h3.fs-lg.fw-normal');
      const price = card.querySelector('.h1.mb-0');
      const period = card.querySelector('.fs-sm.ms-2');
      const description = card.querySelector('p.fs-sm');
      const cta = card.querySelector('.btn.btn-lg.w-100');
      const includeLabel = card.querySelector('.h6.fs-sm');
      const featureList = card.querySelector('ul.list-unstyled');

      if (title) title.textContent = pkg.name;
      if (price) price.textContent = `$${pkg.price}`;
      if (period) period.textContent = pkg.period;
      if (description) description.textContent = pkg.description;

      if (cta) {
        cta.textContent = pkg.buttonLabel;
        cta.setAttribute('type', 'button');
        const isSelected = currentPackage === pkg.slug;
        const baseCtaClasses = String(
          cta.dataset.baseClasses
          || Array.from(cta.classList).filter((className) => !buttonThemeClasses.includes(className)).join(' ')
        ).trim();
        cta.dataset.baseClasses = baseCtaClasses;
        cta.className = `${baseCtaClasses} ${isSelected ? 'btn-info' : 'btn-outline-info'}`.trim();
        cta.textContent = isSelected ? `${pkg.name} selected` : pkg.buttonLabel;
        if (!cta.dataset.packageBound) {
          cta.dataset.packageBound = '1';
          cta.addEventListener('click', () => {
            currentPackage = pkg.slug;
            updateSelectedPackageUrl();
            updatePlans();
          });
        }
      }

      if (cardShell) {
        cardShell.classList.toggle('shadow', currentPackage === pkg.slug);
        cardShell.style.boxShadow = currentPackage === pkg.slug
          ? 'inset 0 0 0 3px rgba(var(--fn-info-rgb), .85)'
          : '';
      }

      if (featuredWrap) {
        featuredWrap.classList.remove(...backgroundThemeClasses);
        featuredWrap.classList.add('bg-info');
        featuredWrap.style.opacity = currentPackage === pkg.slug ? '1' : '.18';
      }

      if (includeLabel) {
        if (pkg.includesLabel) {
          includeLabel.textContent = pkg.includesLabel;
          includeLabel.classList.remove('d-none');
        } else {
          includeLabel.textContent = '';
          includeLabel.classList.add('d-none');
        }
      }

      if (featureList) {
        featureList.innerHTML = pkg.features.map((feature) => `
          <li class="d-flex">
            <i class="fi-check fs-base text-body-secondary me-2" style="margin-top: 3px"></i>
            ${feature}
          </li>
        `).join('');
      }
    });
  };

  const submitCarPromotion = (publishNow) => {
    if (!editId) {
      window.location.href = '/sell-car';
      return;
    }
    setSubmitting();
    const selectedServices = selectedServicesPayload();
    const form = document.createElement('form');
    form.method = 'post';
    form.action = '/submit/car';
    form.innerHTML = `
      <input type="hidden" name="_token" value="__SCRIPT_CSRF__">
      ${publishNow ? '' : '<input type="hidden" name="draft" value="1">'}
      <input type="hidden" name="listing_id" value="${editId}">
      <input type="hidden" name="package" value="${currentPackage}">
      <input type="hidden" name="promotion_package" value="${currentPackage}">
      <input type="hidden" name="service_certify" value="${selectedServices.service_certify ? '1' : '0'}">
      <input type="hidden" name="service_lifts" value="${selectedServices.service_lifts ? '1' : '0'}">
      <input type="hidden" name="service_analytics" value="${selectedServices.service_analytics ? '1' : '0'}">
    `;
    document.body.appendChild(form);
    form.submit();
  };

  const heading = document.querySelector('h1');
  if (heading) heading.textContent = 'Choose your plan';

  const lead = Array.from(document.querySelectorAll('p'))
    .find((p) => (p.textContent || '').toLowerCase().includes('choose the package that best matches your promotion goals'));
  if (lead) lead.textContent = 'Choose a package for your car listing, then publish when you are ready.';

  const publishBtn = Array.from(document.querySelectorAll('a.btn.btn-lg.btn-primary[href]'))
    .find((btn) => (btn.textContent || '').trim().toLowerCase().includes('publish property listing'));
  if (publishBtn) {
    publishBtn.textContent = 'Publish listing';
    publishBtn.setAttribute('href', '#');
    publishBtn.addEventListener('click', (event) => {
      event.preventDefault();
      submitCarPromotion(true);
    });
  }

  const draftBtn = Array.from(document.querySelectorAll('button.btn.btn-lg.btn-outline-secondary'))
    .find((btn) => (btn.textContent || '').trim().toLowerCase() === 'save draft');
  if (draftBtn) {
    draftBtn.addEventListener('click', (event) => {
      event.preventDefault();
      submitCarPromotion(false);
    });
  }

  if (savedSelection) applySavedSelection(savedSelection);
  updateSelectedPackageUrl();
  updatePlans();
})();
</script>
HTML;
        $carPromotionScript = str_replace('__SCRIPT_CSRF__', csrf_token(), $carPromotionScript);
        $carPromotionScript = str_replace('__CAR_PROMOTION_DATA__', $carPromotionJson ?: 'null', $carPromotionScript);
        $html = str_replace('</body>', $carPromotionScript . '</body>', $html);
    }

    if ($file === 'add-property-promotion.html' && $currentRequestPath === '/add-restaurant-promotion') {
        $restaurantPromotionPayload = null;
        $editId = (int) request()->query('edit', 0);
        if ($editId > 0 && Auth::check()) {
            $editListing = Listing::query()
                ->where('id', $editId)
                ->where('module', 'restaurants')
                ->where('user_id', Auth::id())
                ->first();

            if ($editListing) {
                $featureTokens = collect(is_array($editListing->features) ? $editListing->features : [])
                    ->map(fn ($value) => trim((string) $value))
                    ->filter();

                $savedPackage = strtolower((string) ($featureTokens->first(fn ($token) => str_starts_with($token, 'promo-package:')) ?? ''));
                if ($savedPackage !== '') {
                    $savedPackage = substr($savedPackage, strlen('promo-package:'));
                }

                $restaurantPromotionPayload = [
                    'promotion_package' => $savedPackage,
                    'service_certify' => $featureTokens->contains('promo-service:certify'),
                    'service_lifts' => $featureTokens->contains('promo-service:lifts'),
                    'service_analytics' => $featureTokens->contains('promo-service:analytics'),
                ];
            }
        }
        $restaurantPromotionJson = json_encode($restaurantPromotionPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        $restaurantPromotionScript = <<<'HTML'
<script>
(() => {
  const search = new URLSearchParams(window.location.search);
  const editId = (search.get('edit') || '').trim();
  const savedSelection = __RESTAURANT_PROMOTION_DATA__ || null;
  const packageConfigs = [
    {
      slug: 'easy-start',
      name: 'Easy Start',
      price: 25,
      period: '/ month',
      description: "Ideal if you're testing the waters and want to start with basic exposure.",
      buttonLabel: 'Select Easy Start',
      includesLabel: '',
      features: [
        '7-Day Run for your restaurant listing',
        'Keep your restaurant visible for one week',
        'Track basic views and engagement',
      ],
    },
    {
      slug: 'fast-sale',
      name: 'Fast Sale',
      price: 49,
      period: '/ month',
      description: 'Perfect for restaurants that want stronger exposure and more customer interest.',
      buttonLabel: 'Select Fast Sale',
      includesLabel: 'Includes everything in Easy Start +',
      features: [
        '14-Day Run for your restaurant listing',
        'Detailed user engagement analytics',
        'Priority visibility for your restaurant',
      ],
    },
    {
      slug: 'turbo-boost',
      name: 'Turbo Boost',
      price: 70,
      period: '/ month',
      description: 'Best for maximum visibility and premium promotion across the marketplace.',
      buttonLabel: 'Select Turbo Boost',
      includesLabel: 'Includes everything in Fast Sale +',
      features: [
        '28-Day Run for your restaurant listing',
        'Advanced analytics and reporting',
        'Premium spotlight placement',
      ],
    },
  ];
  const serviceConfig = {
    certify: 'Check and certify my restaurant by Monaclick experts',
    lifts: '10 lifts to the top of the list (daily, 7 days)',
    analytics: 'Detailed user engagement analytics',
  };
  let currentPackage = String(savedSelection?.promotion_package || '').trim().toLowerCase();
  if (!packageConfigs.some((pkg) => pkg.slug === currentPackage)) {
    currentPackage = (search.get('package') || '').trim().toLowerCase();
  }
  if (!packageConfigs.some((pkg) => pkg.slug === currentPackage)) {
    currentPackage = 'fast-sale';
  }

  const applySavedSelection = (data) => {
    const savedPackage = String(data?.promotion_package || '').trim().toLowerCase();
    if (packageConfigs.some((pkg) => pkg.slug === savedPackage)) {
      currentPackage = savedPackage;
    }
    Object.keys(serviceConfig).forEach((key) => {
      const input = document.getElementById(key);
      if (!(input instanceof HTMLInputElement)) return;
      input.checked = !!data?.[`service_${key}`];
    });
  };

  const setSubmitting = () => {
    document.querySelectorAll('.pt-5.d-flex.flex-wrap.gap-3.align-items-center .btn').forEach((btn) => {
      btn.classList.add('disabled');
      btn.setAttribute('aria-disabled', 'true');
      if (btn.tagName === 'BUTTON') btn.disabled = true;
    });
  };

  const updateSelectedPackageUrl = () => {
    const url = new URL(window.location.href);
    url.searchParams.set('package', currentPackage);
    window.history.replaceState({}, '', url.toString());
  };

  const selectedServicesPayload = () => {
    const payload = {};
    Object.keys(serviceConfig).forEach((key) => {
      const input = document.getElementById(key);
      payload[`service_${key}`] = !!(input instanceof HTMLInputElement && input.checked);
    });
    return payload;
  };

  const updatePlans = () => {
    const planCards = Array.from(document.querySelectorAll('.overflow-x-auto .card .card-body'))
      .filter((card) => card.querySelector('h3.fs-lg.fw-normal'));
    const buttonThemeClasses = [
      'btn-primary', 'btn-outline-primary',
      'btn-secondary', 'btn-outline-secondary',
      'btn-success', 'btn-outline-success',
      'btn-danger', 'btn-outline-danger',
      'btn-warning', 'btn-outline-warning',
      'btn-info', 'btn-outline-info',
      'btn-light', 'btn-outline-light',
      'btn-dark', 'btn-outline-dark',
    ];
    const backgroundThemeClasses = [
      'bg-primary', 'bg-secondary', 'bg-success', 'bg-danger',
      'bg-warning', 'bg-info', 'bg-light', 'bg-dark',
    ];

    packageConfigs.forEach((pkg, index) => {
      const card = planCards[index];
      if (!card) return;
      const cardShell = card.closest('.card');
      const featuredWrap = card.closest('.w-100')?.querySelector('.position-absolute.top-0.start-0.rounded-5.ms-n1');
      const title = card.querySelector('h3.fs-lg.fw-normal');
      const price = card.querySelector('.h1.mb-0');
      const period = card.querySelector('.fs-sm.ms-2');
      const description = card.querySelector('p.fs-sm');
      const cta = card.querySelector('.btn.btn-lg.w-100');
      const includeLabel = card.querySelector('.h6.fs-sm');
      const featureList = card.querySelector('ul.list-unstyled');

      if (title) title.textContent = pkg.name;
      if (price) price.textContent = `$${pkg.price}`;
      if (period) period.textContent = pkg.period;
      if (description) description.textContent = pkg.description;

      if (cta) {
        cta.textContent = pkg.buttonLabel;
        cta.setAttribute('type', 'button');
        const isSelected = currentPackage === pkg.slug;
        const baseCtaClasses = String(
          cta.dataset.baseClasses
          || Array.from(cta.classList).filter((className) => !buttonThemeClasses.includes(className)).join(' ')
        ).trim();
        cta.dataset.baseClasses = baseCtaClasses;
        cta.className = `${baseCtaClasses} ${isSelected ? 'btn-info' : 'btn-outline-info'}`.trim();
        cta.textContent = isSelected ? `${pkg.name} selected` : pkg.buttonLabel;
        if (!cta.dataset.packageBound) {
          cta.dataset.packageBound = '1';
          cta.addEventListener('click', () => {
            currentPackage = pkg.slug;
            updateSelectedPackageUrl();
            updatePlans();
          });
        }
      }

      if (cardShell) {
        cardShell.classList.toggle('shadow', currentPackage === pkg.slug);
        cardShell.style.boxShadow = currentPackage === pkg.slug
          ? 'inset 0 0 0 3px rgba(var(--fn-info-rgb), .85)'
          : '';
      }

      if (featuredWrap) {
        featuredWrap.classList.remove(...backgroundThemeClasses);
        featuredWrap.classList.add('bg-info');
        featuredWrap.style.opacity = currentPackage === pkg.slug ? '1' : '.18';
      }

      if (includeLabel) {
        if (pkg.includesLabel) {
          includeLabel.textContent = pkg.includesLabel;
          includeLabel.classList.remove('d-none');
        } else {
          includeLabel.textContent = '';
          includeLabel.classList.add('d-none');
        }
      }

      if (featureList) {
        featureList.innerHTML = pkg.features.map((feature) => `
          <li class="d-flex">
            <i class="fi-check fs-base text-body-secondary me-2" style="margin-top: 3px"></i>
            ${feature}
          </li>
        `).join('');
      }
    });
  };

  const submitRestaurantPromotion = (publishNow) => {
    if (!editId) {
      window.location.href = '/add-restaurant';
      return;
    }
    setSubmitting();
    const selectedServices = selectedServicesPayload();
    const form = document.createElement('form');
    form.method = 'post';
    form.action = '/submit/restaurant';
    form.innerHTML = `
      <input type="hidden" name="_token" value="__SCRIPT_CSRF__">
      ${publishNow ? '' : '<input type="hidden" name="draft" value="1">'}
      <input type="hidden" name="listing_id" value="${editId}">
      <input type="hidden" name="package" value="${currentPackage}">
      <input type="hidden" name="promotion_package" value="${currentPackage}">
      <input type="hidden" name="service_certify" value="${selectedServices.service_certify ? '1' : '0'}">
      <input type="hidden" name="service_lifts" value="${selectedServices.service_lifts ? '1' : '0'}">
      <input type="hidden" name="service_analytics" value="${selectedServices.service_analytics ? '1' : '0'}">
    `;
    document.body.appendChild(form);
    form.submit();
  };

  const heading = document.querySelector('h1');
  if (heading) heading.textContent = 'Choose your plan';

  const lead = Array.from(document.querySelectorAll('p'))
    .find((p) => (p.textContent || '').toLowerCase().includes('choose the package that best matches your promotion goals'));
  if (lead) lead.textContent = 'Choose a package for your restaurant listing, then publish when you are ready.';

  const publishBtn = Array.from(document.querySelectorAll('a.btn.btn-lg.btn-primary[href]'))
    .find((btn) => (btn.textContent || '').trim().toLowerCase().includes('publish property listing'));
  if (publishBtn) {
    publishBtn.textContent = 'Publish listing';
    publishBtn.setAttribute('href', '#');
    publishBtn.addEventListener('click', (event) => {
      event.preventDefault();
      submitRestaurantPromotion(true);
    });
  }

  const draftBtn = Array.from(document.querySelectorAll('button.btn.btn-lg.btn-outline-secondary'))
    .find((btn) => (btn.textContent || '').trim().toLowerCase() === 'save draft');
  if (draftBtn) {
    draftBtn.addEventListener('click', (event) => {
      event.preventDefault();
      submitRestaurantPromotion(false);
    });
  }

  if (savedSelection) applySavedSelection(savedSelection);
  updateSelectedPackageUrl();
  updatePlans();
})();
</script>
HTML;
        $restaurantPromotionScript = str_replace('__SCRIPT_CSRF__', csrf_token(), $restaurantPromotionScript);
        $restaurantPromotionScript = str_replace('__RESTAURANT_PROMOTION_DATA__', $restaurantPromotionJson ?: 'null', $restaurantPromotionScript);
        $html = str_replace('</body>', $restaurantPromotionScript . '</body>', $html);
    }

    if ($file === 'account-payment.html') {
        $accountPaymentScript = <<<'HTML'
<script>
(() => {
  const search = new URLSearchParams(window.location.search);
  const listings = __USER_LISTINGS__;
  const editId = (search.get('edit') || '').trim();
  const packageSlug = (search.get('package') || '').trim().toLowerCase();
  const paymentStorageKey = 'monaclickPaymentMethods';
  const packageConfigs = {
    'easy-start': {
      name: 'Easy Start',
      price: '$25',
      note: '7-day starter visibility for your property listing.',
    },
    'fast-sale': {
      name: 'Fast Sale',
      price: '$49',
      note: 'Recommended package with stronger exposure and insights.',
    },
    'turbo-boost': {
      name: 'Turbo Boost',
      price: '$70',
      note: 'Maximum promotion reach with advanced analytics support.',
    },
  };

  const selected = packageConfigs[packageSlug];
  const changePackageUrl = new URL('/add-property-promotion', window.location.origin);
  changePackageUrl.searchParams.set('package', packageSlug);
  if (editId) changePackageUrl.searchParams.set('edit', editId);

  const contentCol = document.querySelector('.col-lg-9');
  const intro = contentCol?.querySelector('p.pb-2.pb-lg-3');
  const cardsWrap = document.getElementById('account-payment-cards');
  const addPaymentModal = document.getElementById('addPayment');
  const cardForm = addPaymentModal?.querySelector('#add-card')?.querySelector('form');
  const paypalForm = addPaymentModal?.querySelector('#add-paypal')?.querySelector('form');
  const cardNumberInput = document.getElementById('card-number');
  const cardNameInput = document.getElementById('card-name');
  const cardExpiryInput = document.getElementById('card-expiration');
  const cardCvcInput = document.getElementById('card-cvc');
  const paypalEmailInput = document.getElementById('paypal-email');
  const addPaymentBtn = document.querySelector('[data-bs-target="#addPayment"]');
  const bootstrapModal = window.bootstrap?.Modal && addPaymentModal
    ? window.bootstrap.Modal.getOrCreateInstance(addPaymentModal)
    : null;
  const addCardTabBtn = document.getElementById('add-card-tab');
  const addPaypalTabBtn = document.getElementById('add-paypal-tab');
  const addCardPane = document.getElementById('add-card');
  const addPaypalPane = document.getElementById('add-paypal');
  let editingMethodId = null;

  [cardForm, paypalForm].forEach((form) => {
    if (form) form.setAttribute('data-mc-no-loader', '1');
  });

  if (contentCol && intro && selected) {
    intro.textContent = `Complete payment details for the ${selected.name} promotion package.`;

    const summary = document.createElement('div');
    summary.className = 'alert alert-info d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4';
    summary.innerHTML = `
      <div>
        <div class="h5 mb-1">Selected package: ${selected.name}</div>
        <p class="mb-0">${selected.note}</p>
      </div>
      <div class="text-sm-end">
        <div class="h4 mb-1">${selected.price}<span class="fs-sm fw-normal"> / month</span></div>
        <a class="btn btn-sm btn-outline-info" href="${changePackageUrl.toString()}">Change package</a>
      </div>
    `;
    intro.insertAdjacentElement('afterend', summary);
  }

  // Payment methods are now rendered from the backend bridge below.
  // Stop here so the legacy localStorage renderer doesn't flash duplicate cards on load.
  return;

  const readStoredMethods = () => {
    try {
      const parsed = JSON.parse(localStorage.getItem(paymentStorageKey) || '[]');
      return Array.isArray(parsed) ? parsed : [];
    } catch (_) {
      return [];
    }
  };

  const writeStoredMethods = (methods) => {
    try {
      localStorage.setItem(paymentStorageKey, JSON.stringify(methods));
    } catch (_) {}
  };

  const findMethodIndex = (methods, id) => methods.findIndex((method) => String(method?.id || '') === String(id || ''));

  const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
  }[char]));

  const maskCardNumber = (digits) => {
    const clean = String(digits || '').replace(/\D/g, '');
    const last4 = clean.slice(-4).padStart(4, '*');
    return `${clean.slice(0, 4).padEnd(4, '*')} **** **** ${last4}`;
  };

  const detectBrand = (digits) => {
    const clean = String(digits || '').replace(/\D/g, '');
    if (/^4/.test(clean)) return 'visa';
    if (/^(5[1-5]|2[2-7])/.test(clean)) return 'mastercard';
    return 'card';
  };

  const cardGradient = (brand) => {
    if (brand === 'mastercard') return 'background: linear-gradient(90deg, #fcb69f 0%, #ffe8c9 100%)';
    return 'background: linear-gradient(90deg, #accbee 0%, #dbeafe 100%)';
  };

  const cardLogo = (brand) => {
    if (brand === 'mastercard') {
      return '<svg class="flex-shrink-0" xmlns="http://www.w3.org/2000/svg" width="52" height="32" fill="none"><path d="M31.411 25.616H20.594V5.707h10.817v19.909z" fill="#ff5f00"/><path d="M21.28 15.662c0-4.038 1.846-7.636 4.722-9.954C23.825 3.95 21.133 2.996 18.362 3 11.534 3 6 8.669 6 15.662s5.534 12.662 12.362 12.662c2.772.004 5.464-.95 7.64-2.707-2.875-2.318-4.722-5.916-4.722-9.955z" fill="#eb001b"/><path d="M46.003 15.662c0 6.993-5.534 12.662-12.362 12.662A12.13 12.13 0 0 1 26 25.616c2.876-2.318 4.722-5.916 4.722-9.955S28.876 8.026 26 5.707A12.13 12.13 0 0 1 33.641 3c6.827 0 12.362 5.669 12.362 12.662" fill="#f79e1b"/></svg>';
    }
    return '<svg class="flex-shrink-0 text-dark-emphasis" xmlns="http://www.w3.org/2000/svg" width="52" height="32" fill="currentColor"><path d="M20.224 8.524L13.94 23.516h-4.1L6.748 11.55c-.188-.736-.35-1.006-.922-1.316-.932-.506-2.472-.98-3.826-1.276l.092-.434h6.6a1.81 1.81 0 0 1 1.788 1.528l1.634 8.676L16.15 8.524h4.074zM36.29 18.622c.016-3.958-5.472-4.176-5.434-5.944.012-.538.524-1.11 1.644-1.256a7.32 7.32 0 0 1 3.826.672l.68-3.18c-1.16-.436-2.389-.662-3.628-.666-3.834 0-6.532 2.04-6.556 4.958-.024 2.158 1.926 3.36 3.396 4.08 1.512.734 2.02 1.206 2.012 1.862-.01 1.008-1.204 1.45-2.32 1.468-1.95.03-3.08-.526-3.984-.946l-.702 3.284c.906.416 2.578.78 4.312.796 4.074 0 6.74-2.012 6.754-5.128zm10.122 4.894H50L46.87 8.524h-3.312c-.354-.003-.701.1-.995.296s-.523.476-.657.804l-5.818 13.892h4.072l.81-2.24h4.976l.466 2.24zm-4.326-5.312l2.04-5.63 1.176 5.63h-3.216zm-16.32-9.68L22.56 23.516h-3.88l3.21-14.992h3.876z"/></svg>';
  };

  const renderStoredMethod = (method) => {
    if (!cardsWrap || !method || method.type !== 'card') return;

    const card = document.createElement('div');
    card.className = 'card border-0';
    card.style.width = '100%';
    card.style.maxWidth = '400px';
    card.setAttribute('data-user-payment-card', '1');
    card.setAttribute('data-method-id', String(method.id || ''));
    card.innerHTML = `
      <div class="card-body position-relative z-2">
        <div class="d-flex align-items-center pb-4 mb-2 mb-md-3">
          ${cardLogo(method.brand)}
          <span class="badge text-bg-light ms-3">Added now</span>
          <div class="dropdown ms-auto">
            <button type="button" class="btn btn-icon btn-sm fs-xl text-dark-emphasis border-0" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions">
              <i class="fi-more-vertical"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" style="--fn-dropdown-min-width: 8rem">
              <li>
                <button type="button" class="dropdown-item" data-payment-action="edit" data-method-id="${escapeHtml(String(method.id || ''))}">
                  <i class="fi-edit opacity-75 me-2"></i>
                  Edit
                </button>
              </li>
              <li>
                <button type="button" class="dropdown-item text-danger" data-payment-action="delete" data-method-id="${escapeHtml(String(method.id || ''))}">
                  <i class="fi-trash opacity-75 me-2"></i>
                  Delete
                </button>
              </li>
            </ul>
          </div>
        </div>
        <div class="h5 pt-md-1 pb-2 pb-md-3" style="letter-spacing: 1.25px">${escapeHtml(maskCardNumber(method.number))}</div>
        <div class="d-flex justify-content-between">
          <div class="me-3">
            <div class="fs-xs text-body mb-1">Name</div>
            <div class="h6 fs-sm mb-0">${escapeHtml(method.name)}</div>
          </div>
          <div>
            <div class="fs-xs text-body mb-1">Expiry date</div>
            <div class="h6 fs-sm mb-0">${escapeHtml(method.expiry)}</div>
          </div>
        </div>
      </div>
      <span class="position-absolute top-0 start-0 w-100 h-100 rounded-4 d-none-dark" style="${cardGradient(method.brand)}"></span>
      <span class="position-absolute top-0 start-0 w-100 h-100 rounded-4 d-none d-block-dark" style="background: linear-gradient(90deg, #1b273a 0%, #1f2632 100%)"></span>
    `;
    cardsWrap.appendChild(card);
  };

  const rerenderStoredMethods = () => {
    if (!cardsWrap) return;
    cardsWrap.querySelectorAll('[data-user-payment-card="1"]').forEach((node) => node.remove());
    readStoredMethods().forEach(renderStoredMethod);
  };

  const resetCardFormState = () => {
    editingMethodId = null;
    cardForm?.reset();
    [cardNumberInput, cardNameInput, cardExpiryInput, cardCvcInput].forEach((input) => input?.classList.remove('is-invalid'));
    const submitBtn = cardForm?.querySelector('button[type="submit"]');
    if (submitBtn) submitBtn.textContent = 'Add card';
    if (addCardTabBtn) addCardTabBtn.textContent = 'Add card';
  };

  const openCardEditor = (methodId) => {
    const methods = readStoredMethods();
    const method = methods.find((entry) => String(entry?.id || '') === String(methodId || ''));
    if (!method || method.type !== 'card') return;

    editingMethodId = String(method.id || '');
    if (cardNumberInput) cardNumberInput.value = String(method.number || '');
    if (cardNameInput) cardNameInput.value = String(method.name || '');
    if (cardExpiryInput) cardExpiryInput.value = normalizeExpiry(method.expiry || '');
    if (cardCvcInput) cardCvcInput.value = '';
    [cardNumberInput, cardNameInput, cardExpiryInput, cardCvcInput].forEach((input) => input?.classList.remove('is-invalid'));

    if (addPaypalTabBtn && addPaypalPane) {
      addPaypalTabBtn.classList.remove('active');
      addPaypalTabBtn.setAttribute('aria-selected', 'false');
      addPaypalPane.classList.remove('show', 'active');
    }
    if (addCardTabBtn && addCardPane) {
      addCardTabBtn.classList.add('active');
      addCardTabBtn.setAttribute('aria-selected', 'true');
      addCardPane.classList.add('show', 'active');
      addCardTabBtn.textContent = 'Edit card';
    }

    const submitBtn = cardForm?.querySelector('button[type="submit"]');
    if (submitBtn) submitBtn.textContent = 'Save changes';
    bootstrapModal?.show();
  };

  const deleteStoredMethod = (methodId) => {
    const methods = readStoredMethods().filter((entry) => String(entry?.id || '') !== String(methodId || ''));
    writeStoredMethods(methods);
    rerenderStoredMethods();
  };

  const normalizeExpiry = (value) => {
    const clean = String(value || '').replace(/\D/g, '').slice(0, 4);
    if (clean.length < 4) return '';
    return `${clean.slice(0, 2)}/${clean.slice(2, 4)}`;
  };

  const isValidExpiry = (value) => /^(0[1-9]|1[0-2])\/\d{2}$/.test(value);

  const showFieldError = (input, isInvalid) => {
    if (!input) return;
    input.classList.toggle('is-invalid', isInvalid);
  };

  if (addPaymentBtn) {
    addPaymentBtn.addEventListener('click', (event) => {
      event.preventDefault();
    });
  }

  if (cardExpiryInput) {
    cardExpiryInput.addEventListener('blur', () => {
      cardExpiryInput.value = normalizeExpiry(cardExpiryInput.value);
    });
  }

  if (cardForm) {
    cardForm.addEventListener('submit', (event) => {
      event.preventDefault();
      event.stopPropagation();

      const digits = String(cardNumberInput?.value || '').replace(/\D/g, '');
      const name = String(cardNameInput?.value || '').trim();
      const expiry = normalizeExpiry(cardExpiryInput?.value || '');
      const cvc = String(cardCvcInput?.value || '').replace(/\D/g, '');

      if (cardExpiryInput) cardExpiryInput.value = expiry;

      const invalidNumber = digits.length < 13;
      const invalidName = name.length < 2;
      const invalidExpiry = !isValidExpiry(expiry);
      const invalidCvc = cvc.length < 3;

      showFieldError(cardNumberInput, invalidNumber);
      showFieldError(cardNameInput, invalidName);
      showFieldError(cardExpiryInput, invalidExpiry);
      showFieldError(cardCvcInput, invalidCvc);

      if (invalidNumber || invalidName || invalidExpiry || invalidCvc) return;

      const methods = readStoredMethods();
      const payload = {
        id: editingMethodId || `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
        type: 'card',
        brand: detectBrand(digits),
        number: digits,
        name,
        expiry,
        createdAt: Date.now(),
      };
      const existingIndex = findMethodIndex(methods, editingMethodId);
      if (existingIndex >= 0) {
        methods[existingIndex] = {
          ...methods[existingIndex],
          ...payload,
        };
      } else {
        methods.push(payload);
      }
      writeStoredMethods(methods);
      rerenderStoredMethods();
      resetCardFormState();
      bootstrapModal?.hide();
    });
  }

  if (paypalForm) {
    paypalForm.addEventListener('submit', (event) => {
      event.preventDefault();
      event.stopPropagation();
      const email = String(paypalEmailInput?.value || '').trim();
      const valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
      showFieldError(paypalEmailInput, !valid);
      if (!valid) return;
      paypalForm.reset();
      paypalEmailInput?.classList.remove('is-invalid');
      bootstrapModal?.hide();
    });
  }

  addPaymentModal?.addEventListener('hidden.bs.modal', () => {
    resetCardFormState();
    paypalForm?.reset();
    paypalEmailInput?.classList.remove('is-invalid');
  });

  cardsWrap?.addEventListener('click', (event) => {
    const actionBtn = event.target instanceof Element
      ? event.target.closest('[data-payment-action]')
      : null;
    if (!actionBtn) return;

    const methodId = actionBtn.getAttribute('data-method-id') || '';
    const action = actionBtn.getAttribute('data-payment-action') || '';
    event.preventDefault();

    if (action === 'edit') {
      openCardEditor(methodId);
      return;
    }

    if (action === 'delete') {
      deleteStoredMethod(methodId);
    }
  });

  rerenderStoredMethods();
})();
</script>
HTML;
        $accountPaymentScript = str_replace('__USER_LISTINGS__', $listingsJson ?: '[]', $accountPaymentScript);
        $html = str_replace('</body>', $accountPaymentScript . '</body>', $html);

        $accountPaymentBackendBridge = <<<'HTML'
<script>
(() => {
  const csrfToken = '__SCRIPT_CSRF__';
  const apiBase = '/account/api/payment-methods';
  const cardsWrap = document.getElementById('account-payment-cards');
  const addPaymentModal = document.getElementById('addPayment');
  const cardForm = addPaymentModal?.querySelector('#add-card form');
  const paypalForm = addPaymentModal?.querySelector('#add-paypal form');
  const cardNumberInput = document.getElementById('card-number');
  const cardNameInput = document.getElementById('card-name');
  const cardExpiryInput = document.getElementById('card-expiration');
  const cardCvcInput = document.getElementById('card-cvc');
  const paypalEmailInput = document.getElementById('paypal-email');
  const addCardTabBtn = document.getElementById('add-card-tab');
  const addPaypalTabBtn = document.getElementById('add-paypal-tab');
  const addCardPane = document.getElementById('add-card');
  const addPaypalPane = document.getElementById('add-paypal');
  const bootstrapModal = window.bootstrap?.Modal && addPaymentModal
    ? window.bootstrap.Modal.getOrCreateInstance(addPaymentModal)
    : null;
  let methods = [];
  let editingMethodId = null;

  if (!cardsWrap) return;

  const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  }[char]));
  const normalizeExpiry = (value) => {
    const clean = String(value || '').replace(/\D/g, '').slice(0, 4);
    return clean.length < 4 ? '' : `${clean.slice(0, 2)}/${clean.slice(2, 4)}`;
  };
  const isValidExpiry = (value) => /^(0[1-9]|1[0-2])\/\d{2}$/.test(value);
  const showFieldError = (input, invalid) => input?.classList.toggle('is-invalid', invalid);
  const maskCardNumber = (digits) => {
    const clean = String(digits || '').replace(/\D/g, '');
    const last4 = clean.slice(-4).padStart(4, '*');
    return `${clean.slice(0, 4).padEnd(4, '*')} **** **** ${last4}`;
  };
  const cardGradient = (brand) => brand === 'mastercard'
    ? 'background: linear-gradient(90deg, #fcb69f 0%, #ffe8c9 100%)'
    : 'background: linear-gradient(90deg, #accbee 0%, #dbeafe 100%)';
  const cardLogo = (brand) => brand === 'mastercard'
    ? '<svg class="flex-shrink-0" xmlns="http://www.w3.org/2000/svg" width="52" height="32" fill="none"><path d="M31.411 25.616H20.594V5.707h10.817v19.909z" fill="#ff5f00"/><path d="M21.28 15.662c0-4.038 1.846-7.636 4.722-9.954C23.825 3.95 21.133 2.996 18.362 3 11.534 3 6 8.669 6 15.662s5.534 12.662 12.362 12.662c2.772.004 5.464-.95 7.64-2.707-2.875-2.318-4.722-5.916-4.722-9.955z" fill="#eb001b"/><path d="M46.003 15.662c0 6.993-5.534 12.662-12.362 12.662A12.13 12.13 0 0 1 26 25.616c2.876-2.318 4.722-5.916 4.722-9.955S28.876 8.026 26 5.707A12.13 12.13 0 0 1 33.641 3c6.827 0 12.362 5.669 12.362 12.662" fill="#f79e1b"/></svg>'
    : '<svg class="flex-shrink-0 text-dark-emphasis" xmlns="http://www.w3.org/2000/svg" width="52" height="32" fill="currentColor"><path d="M20.224 8.524L13.94 23.516h-4.1L6.748 11.55c-.188-.736-.35-1.006-.922-1.316-.932-.506-2.472-.98-3.826-1.276l.092-.434h6.6a1.81 1.81 0 0 1 1.788 1.528l1.634 8.676L16.15 8.524h4.074zM36.29 18.622c.016-3.958-5.472-4.176-5.434-5.944.012-.538.524-1.11 1.644-1.256a7.32 7.32 0 0 1 3.826.672l.68-3.18c-1.16-.436-2.389-.662-3.628-.666-3.834 0-6.532 2.04-6.556 4.958-.024 2.158 1.926 3.36 3.396 4.08 1.512.734 2.02 1.206 2.012 1.862-.01 1.008-1.204 1.45-2.32 1.468-1.95.03-3.08-.526-3.984-.946l-.702 3.284c.906.416 2.578.78 4.312.796 4.074 0 6.74-2.012 6.754-5.128zm10.122 4.894H50L46.87 8.524h-3.312c-.354-.003-.701.1-.995.296s-.523.476-.657.804l-5.818 13.892h4.072l.81-2.24h4.976l.466 2.24zm-4.326-5.312l2.04-5.63 1.176 5.63h-3.216zm-16.32-9.68L22.56 23.516h-3.88l3.21-14.992h3.876z"/></svg>';

  const requestJson = async (url, options = {}) => {
    const headers = {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': csrfToken,
      ...(options.headers || {}),
    };
    if (options.body && !headers['Content-Type']) headers['Content-Type'] = 'application/json';
    const response = await fetch(url, { credentials: 'same-origin', ...options, headers });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.message || 'Request failed.');
    return payload;
  };

  const resetCardForm = () => {
    editingMethodId = null;
    cardForm?.reset();
    [cardNumberInput, cardNameInput, cardExpiryInput, cardCvcInput].forEach((input) => input?.classList.remove('is-invalid'));
    const submitBtn = cardForm?.querySelector('button[type="submit"]');
    if (submitBtn) submitBtn.textContent = 'Add card';
    if (addCardTabBtn) addCardTabBtn.textContent = 'Add card';
  };

  const renderMethods = () => {
    cardsWrap.querySelectorAll('[data-user-payment-card]').forEach((node) => node.remove());
    methods.forEach((method) => {
      const card = document.createElement('div');
      card.className = 'card border-0';
      card.style.width = '100%';
      card.style.maxWidth = '400px';
      card.setAttribute('data-user-payment-card', '1');
      card.setAttribute('data-method-id', String(method.id || ''));
      if (method.type === 'paypal') {
        card.innerHTML = `
          <div class="card-body position-relative z-2">
            <div class="d-flex align-items-center pb-4 mb-2 mb-md-3">
              <img src="/finder/assets/img/payment-methods/paypal-light-mode.svg" width="52" alt="PayPal">
              <span class="badge text-bg-light ms-3">${method.is_primary ? 'Primary' : 'Saved'}</span>
              <div class="dropdown ms-auto">
                <button type="button" class="btn btn-icon btn-sm fs-xl text-dark-emphasis border-0" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions"><i class="fi-more-vertical"></i></button>
                <ul class="dropdown-menu dropdown-menu-end" style="--fn-dropdown-min-width: 8rem">
                  <li><button type="button" class="dropdown-item text-danger" data-payment-action="delete" data-method-id="${escapeHtml(method.id)}"><i class="fi-trash opacity-75 me-2"></i>Delete</button></li>
                </ul>
              </div>
            </div>
            <div class="h5 pt-md-1 pb-2 pb-md-3">${escapeHtml(method.paypal_email || '')}</div>
            <div class="fs-sm text-body-secondary">PayPal account</div>
          </div>
          <span class="position-absolute top-0 start-0 w-100 h-100 rounded-4 d-none-dark" style="background: linear-gradient(90deg, #d6e4ff 0%, #f2f5ff 100%)"></span>
        `;
      } else {
        card.innerHTML = `
          <div class="card-body position-relative z-2">
            <div class="d-flex align-items-center pb-4 mb-2 mb-md-3">
              ${cardLogo(method.brand)}
              <span class="badge text-bg-light ms-3">${method.is_primary ? 'Primary' : 'Saved'}</span>
              <div class="dropdown ms-auto">
                <button type="button" class="btn btn-icon btn-sm fs-xl text-dark-emphasis border-0" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions"><i class="fi-more-vertical"></i></button>
                <ul class="dropdown-menu dropdown-menu-end" style="--fn-dropdown-min-width: 8rem">
                  <li><button type="button" class="dropdown-item" data-payment-action="edit" data-method-id="${escapeHtml(method.id)}"><i class="fi-edit opacity-75 me-2"></i>Edit</button></li>
                  <li><button type="button" class="dropdown-item text-danger" data-payment-action="delete" data-method-id="${escapeHtml(method.id)}"><i class="fi-trash opacity-75 me-2"></i>Delete</button></li>
                </ul>
              </div>
            </div>
            <div class="h5 pt-md-1 pb-2 pb-md-3" style="letter-spacing:1.25px">${escapeHtml(maskCardNumber(method.number || method.last_four || ''))}</div>
            <div class="d-flex justify-content-between">
              <div class="me-3"><div class="fs-xs text-body mb-1">Name</div><div class="h6 fs-sm mb-0">${escapeHtml(method.name || '')}</div></div>
              <div><div class="fs-xs text-body mb-1">Expiry date</div><div class="h6 fs-sm mb-0">${escapeHtml(method.expiry || '')}</div></div>
            </div>
          </div>
          <span class="position-absolute top-0 start-0 w-100 h-100 rounded-4 d-none-dark" style="${cardGradient(method.brand)}"></span>
        `;
      }
      cardsWrap.appendChild(card);
    });
  };

  const loadMethods = async () => {
    const payload = await requestJson(apiBase, { method: 'GET' });
    methods = Array.isArray(payload.data) ? payload.data : [];
    renderMethods();
  };

  cardForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    event.stopImmediatePropagation();
    event.stopPropagation();
    const digits = String(cardNumberInput?.value || '').replace(/\D/g, '');
    const name = String(cardNameInput?.value || '').trim();
    const expiry = normalizeExpiry(cardExpiryInput?.value || '');
    const cvc = String(cardCvcInput?.value || '').replace(/\D/g, '');
    if (cardExpiryInput) cardExpiryInput.value = expiry;
    const invalidNumber = digits.length < 13;
    const invalidName = name.length < 2;
    const invalidExpiry = !isValidExpiry(expiry);
    const invalidCvc = cvc.length < 3;
    showFieldError(cardNumberInput, invalidNumber);
    showFieldError(cardNameInput, invalidName);
    showFieldError(cardExpiryInput, invalidExpiry);
    showFieldError(cardCvcInput, invalidCvc);
    if (invalidNumber || invalidName || invalidExpiry || invalidCvc) return;
    try {
      await requestJson(editingMethodId ? `${apiBase}/${editingMethodId}` : apiBase, {
        method: editingMethodId ? 'PUT' : 'POST',
        body: JSON.stringify({ type: 'card', number: digits, name, expiry }),
      });
      await loadMethods();
      resetCardForm();
      bootstrapModal?.hide();
    } catch (error) {
      window.alert(error instanceof Error ? error.message : 'Unable to save payment method.');
    }
  }, true);

  paypalForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    event.stopImmediatePropagation();
    event.stopPropagation();
    const email = String(paypalEmailInput?.value || '').trim();
    const valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    showFieldError(paypalEmailInput, !valid);
    if (!valid) return;
    try {
      await requestJson(apiBase, {
        method: 'POST',
        body: JSON.stringify({ type: 'paypal', paypal_email: email }),
      });
      await loadMethods();
      paypalForm.reset();
      paypalEmailInput?.classList.remove('is-invalid');
      bootstrapModal?.hide();
    } catch (error) {
      window.alert(error instanceof Error ? error.message : 'Unable to save PayPal account.');
    }
  }, true);

  cardsWrap.addEventListener('click', async (event) => {
    const actionBtn = event.target instanceof Element ? event.target.closest('[data-payment-action]') : null;
    if (!actionBtn) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    event.stopPropagation();
    const methodId = actionBtn.getAttribute('data-method-id') || '';
    const action = actionBtn.getAttribute('data-payment-action') || '';
    if (action === 'edit') {
      const method = methods.find((entry) => String(entry?.id || '') === methodId);
      if (!method || method.type !== 'card') return;
      editingMethodId = methodId;
      if (cardNumberInput) cardNumberInput.value = String(method.number || '');
      if (cardNameInput) cardNameInput.value = String(method.name || '');
      if (cardExpiryInput) cardExpiryInput.value = normalizeExpiry(method.expiry || '');
      if (cardCvcInput) cardCvcInput.value = '';
      if (addPaypalTabBtn && addPaypalPane) {
        addPaypalTabBtn.classList.remove('active');
        addPaypalTabBtn.setAttribute('aria-selected', 'false');
        addPaypalPane.classList.remove('show', 'active');
      }
      if (addCardTabBtn && addCardPane) {
        addCardTabBtn.classList.add('active');
        addCardTabBtn.setAttribute('aria-selected', 'true');
        addCardPane.classList.add('show', 'active');
        addCardTabBtn.textContent = 'Edit card';
      }
      const submitBtn = cardForm?.querySelector('button[type="submit"]');
      if (submitBtn) submitBtn.textContent = 'Save changes';
      bootstrapModal?.show();
      return;
    }
    if (action === 'delete') {
      try {
        await requestJson(`${apiBase}/${methodId}`, { method: 'DELETE' });
        await loadMethods();
      } catch (error) {
        window.alert(error instanceof Error ? error.message : 'Unable to delete payment method.');
      }
    }
  }, true);

  addPaymentModal?.addEventListener('hidden.bs.modal', () => {
    resetCardForm();
    paypalForm?.reset();
    paypalEmailInput?.classList.remove('is-invalid');
  });

  loadMethods().catch((error) => window.console?.error?.(error));
})();
</script>
HTML;
        $accountPaymentBackendBridge = str_replace('__SCRIPT_CSRF__', csrf_token(), $accountPaymentBackendBridge);
        $html = str_replace('</body>', $accountPaymentBackendBridge . '</body>', $html);
    }

    if ($file === 'account-subscriptions.html') {
        $accountSubscriptionsScript = <<<'HTML'
<script>
(() => {
  const listings = __USER_LISTINGS__;
  const root = document.getElementById('subscriptionsList');
  const summary = document.getElementById('subscriptionsSummary');
  if (!root) return;

  const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
  }[char]));

  const editPromotionHref = (item) => {
    if (!item.id) return '';
    if (item.module === 'contractors') return `/add-contractor-promotion?edit=${encodeURIComponent(item.id)}`;
    if (item.module === 'cars') return `/add-car-promotion?edit=${encodeURIComponent(item.id)}`;
    if (item.module === 'restaurants') return `/add-restaurant-promotion?edit=${encodeURIComponent(item.id)}`;
    return `/add-property-promotion?edit=${encodeURIComponent(item.id)}`;
  };

  const promotionListings = Array.isArray(listings)
    ? listings
      .filter((item) => item?.module === 'real-estate' || item?.module === 'contractors' || item?.module === 'cars' || item?.module === 'restaurants')
      .map((item) => ({
        id: String(item?.id || '').trim(),
        title: String(item?.title || '').trim(),
        module: String(item?.module || '').trim(),
        moduleLabel: String(item?.module_label || '').trim(),
        status: String(item?.status || '').trim(),
        packageLabel: String(item?.promotion_package_label || '').trim(),
        packagePrice: String(item?.promotion_package_price || '').trim(),
        services: Array.isArray(item?.selected_services_details)
          ? item.selected_services_details
            .map((service) => ({
              label: String(service?.label || '').trim(),
              price: String(service?.price || '').trim(),
            }))
            .filter((service) => service.label)
          : [],
      }))
      .filter((item) => item.packageLabel || item.services.length)
    : [];

  if (summary) {
    summary.textContent = promotionListings.length
      ? `${promotionListings.length} subscription${promotionListings.length === 1 ? '' : 's'} currently available to manage.`
      : 'No active or previous subscriptions found yet.';
  }

  if (!promotionListings.length) {
    root.innerHTML = `
      <div class="card border-0 bg-body-tertiary">
        <div class="card-body py-5 text-center">
          <h2 class="h4 mb-2">No subscriptions yet</h2>
          <p class="text-body-secondary mb-4">Packages and extra promotion services will appear here once they are attached to your listings.</p>
          <a class="btn btn-primary" href="/account/listings">View my listings</a>
        </div>
      </div>
    `;
    return;
  }

  root.innerHTML = promotionListings.map((item) => `
    <div class="card border-0 bg-body-tertiary shadow-sm">
      <div class="card-body p-4 p-lg-5">
        <div class="d-flex flex-column flex-md-row align-items-md-start justify-content-between gap-3">
          <div>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
              <h2 class="h4 mb-0">${item.title ? escapeHtml(item.title) : 'Property listing'}</h2>
              ${item.moduleLabel ? `<span class="badge text-bg-secondary">${escapeHtml(item.moduleLabel)}</span>` : ''}
              ${item.status ? `<span class="badge text-bg-light text-capitalize">${escapeHtml(item.status)}</span>` : ''}
            </div>
            ${item.packageLabel ? `
              <div class="small text-body-secondary mb-1">Selected package</div>
              <div class="fw-semibold">${escapeHtml(item.packageLabel)}${item.packagePrice ? ` <span class="text-body-secondary fw-normal">(${escapeHtml(item.packagePrice)})</span>` : ''}</div>
            ` : ''}
          </div>
          ${item.id ? `<a class="btn btn-sm btn-outline-primary" href="${editPromotionHref(item)}">Manage package</a>` : ''}
        </div>
        ${item.services.length ? `
          <div class="border-top mt-4 pt-4">
            <div class="small text-body-secondary mb-2">Other services</div>
            <div class="vstack gap-2">
              ${item.services.map((service) => `
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 small">
                  <span>${escapeHtml(service.label)}</span>
                  <span class="text-body-secondary">${escapeHtml(service.price)}</span>
                </div>
              `).join('')}
            </div>
          </div>
        ` : ''}
      </div>
    </div>
  `).join('');
})();
</script>
HTML;
        $accountSubscriptionsScript = str_replace('__USER_LISTINGS__', $listingsJson ?: '[]', $accountSubscriptionsScript);
        $html = str_replace('</body>', $accountSubscriptionsScript . '</body>', $html);

        $accountSubscriptionsBackendBridge = <<<'HTML'
<script>
(() => {
  const root = document.getElementById('subscriptionsList');
  const summary = document.getElementById('subscriptionsSummary');
  if (!root) return;

  const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  }[char]));

  const renderEmpty = () => {
    root.innerHTML = `
      <div class="card border-0 bg-body-tertiary">
        <div class="card-body py-5 text-center">
          <h2 class="h4 mb-2">No subscriptions yet</h2>
          <p class="text-body-secondary mb-4">Packages and extra promotion services will appear here once they are attached to your listings.</p>
          <a class="btn btn-primary" href="/account/listings">View my listings</a>
        </div>
      </div>
    `;
  };

  fetch('/account/api/subscriptions', {
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    credentials: 'same-origin',
  })
    .then(async (response) => {
      const payload = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(payload.message || 'Unable to load subscriptions.');
      return Array.isArray(payload.data) ? payload.data : [];
    })
    .then((subscriptions) => {
      if (summary) {
        summary.textContent = subscriptions.length
          ? `${subscriptions.length} subscription${subscriptions.length === 1 ? '' : 's'} available across current and previous orders.`
          : 'No active or previous subscriptions found yet.';
      }
      if (!subscriptions.length) {
        renderEmpty();
        return;
      }
      root.innerHTML = subscriptions.map((item) => `
        <div class="card border-0 bg-body-tertiary shadow-sm">
          <div class="card-body p-4 p-lg-5">
            <div class="d-flex flex-column flex-md-row align-items-md-start justify-content-between gap-3">
              <div>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                  <h2 class="h4 mb-0">${escapeHtml(item.title || 'Listing subscription')}</h2>
                  ${item.module_label ? `<span class="badge text-bg-secondary">${escapeHtml(item.module_label)}</span>` : ''}
                  ${item.listing_status ? `<span class="badge text-bg-light text-capitalize">${escapeHtml(item.listing_status)}</span>` : ''}
                  ${item.status ? `<span class="badge ${item.status === 'active' ? 'text-bg-success' : 'text-bg-secondary'} text-capitalize">${escapeHtml(item.status)}</span>` : ''}
                </div>
                <div class="small text-body-secondary mb-1">Order #${escapeHtml(item.order_number || '')}</div>
                ${item.package_label ? `<div class="fw-semibold">${escapeHtml(item.package_label)}${item.package_price ? ` <span class="text-body-secondary fw-normal">(${escapeHtml(item.package_price)})</span>` : ''}</div>` : '<div class="fw-semibold">Promotion services only</div>'}
                ${item.admin_status ? `<div class="small text-body-secondary mt-2">Admin status: <span class="text-capitalize">${escapeHtml(item.admin_status)}</span></div>` : ''}
              </div>
              ${item.manage_url ? `<a class="btn btn-sm btn-outline-primary" href="${escapeHtml(item.manage_url)}">Manage package</a>` : ''}
            </div>
            ${Array.isArray(item.selected_services_details) && item.selected_services_details.length ? `
              <div class="border-top mt-4 pt-4">
                <div class="small text-body-secondary mb-2">Other services</div>
                <div class="vstack gap-2">
                  ${item.selected_services_details.map((service) => `
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 small">
                      <span>${escapeHtml(service.label || '')}</span>
                      <span class="text-body-secondary">${escapeHtml(service.price || '')}</span>
                    </div>
                  `).join('')}
                </div>
              </div>
            ` : ''}
          </div>
        </div>
      `).join('');
    })
    .catch((error) => {
      if (summary) summary.textContent = error instanceof Error ? error.message : 'Unable to load subscriptions.';
      renderEmpty();
    });
})();
</script>
HTML;
        $html = str_replace('</body>', $accountSubscriptionsBackendBridge . '</body>', $html);
    }

    if ($file === 'add-contractor-location.html') {
        $contractorEditPayload = null;
        $editId = (int) request()->query('edit', 0);
        if ($editId > 0) {
            $editQuery = Listing::query()
                ->with(['contractorDetail', 'city', 'category'])
                ->where('id', $editId)
                ->where('module', 'contractors');
            if (Auth::check()) {
                $editQuery->where('user_id', Auth::id());
            }
            $editListing = $editQuery->first();
            if ($editListing) {
                $contractorEditPayload = [
                    'id' => $editListing->id,
                    'title' => (string) $editListing->title,
                    'city' => (string) ($editListing->city?->name ?? ''),
                    'service_area' => (string) ($editListing->contractorDetail?->service_area ?? ''),
                    'address' => (string) (
                        Schema::hasColumn('contractor_details', 'address_line')
                            ? ($editListing->contractorDetail?->address_line ?? '')
                            : ''
                    ),
                    'zip' => (string) (
                        Schema::hasColumn('contractor_details', 'zip_code')
                            ? ($editListing->contractorDetail?->zip_code ?? '')
                            : ''
                    ),
                    'state' => (string) (
                        Schema::hasColumn('contractor_details', 'state_code')
                            ? ($editListing->contractorDetail?->state_code ?? '')
                            : ((string) ($editListing->city?->state_code ?? ''))
                    ),
                ];
            }
        }
        $contractorEditJson = json_encode($contractorEditPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        $contractorScript = <<<'HTML'
<script>
(() => {
  const editData = __CONTRACTOR_EDIT_DATA__;
  const isEdit = !!(editData && editData.id);
  const heading = document.querySelector('h1.h3');
  if (isEdit && heading) heading.textContent = 'Edit contractor location';

  const selectedText = (selector) => {
    const select = document.querySelector(selector);
    if (!select) return '';
    const option = select.options?.[select.selectedIndex];
    return (option?.textContent || '').trim();
  };
  const getSelectedAreas = () => {
    const chipsWrap = Array.from(document.querySelectorAll('div.d-flex.flex-wrap.gap-2'))
      .find((el) => el.querySelector('button.btn.btn-sm.btn-outline-secondary'));
    if (!chipsWrap) return [];
    return Array.from(chipsWrap.querySelectorAll('button.btn.btn-sm.btn-outline-secondary'))
      .map((btn) => (btn.textContent || '').replace(/\s+/g, ' ').trim().replace(/^x\s*/i, ''))
      .filter(Boolean);
  };
  const bindAreaChips = () => {
    const input = document.getElementById('area-search');
    if (!input) return;
    input.setAttribute('type', 'text');
    const chipsWrap = Array.from(document.querySelectorAll('div.d-flex.flex-wrap.gap-2'))
      .find((el) => el.querySelector('button.btn.btn-sm.btn-outline-secondary'));
    if (!chipsWrap) return;

    const chipValues = () => getSelectedAreas().map((v) => v.toLowerCase());
    const addChip = (label) => {
      const clean = String(label || '').trim();
      if (!clean) return;
      if (chipValues().includes(clean.toLowerCase())) return;
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-sm btn-outline-secondary rounded-pill';
      btn.innerHTML = `<i class="fi-close fs-sm ms-n1 me-1"></i>${clean}`;
      chipsWrap.appendChild(btn);
    };
    const removeChip = (btn) => {
      if (btn && btn.parentElement === chipsWrap) btn.remove();
    };

    chipsWrap.addEventListener('click', (event) => {
      const btn = event.target && event.target.closest('button.btn.btn-sm.btn-outline-secondary');
      if (!btn) return;
      event.preventDefault();
      removeChip(btn);
    });

    const consumeInput = () => {
      const raw = input.value || '';
      raw.split(',').map((v) => v.trim()).filter(Boolean).forEach(addChip);
      input.value = '';
    };
    input.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' || event.key === ',') {
        event.preventDefault();
        consumeInput();
      }
    });
    input.addEventListener('blur', consumeInput);
  };
  const setSelectByText = (selector, value) => {
    const normalized = String(value || '').trim().toLowerCase();
    if (!normalized) return;
    const select = document.querySelector(selector);
    if (!select) return;
    let option = Array.from(select.options).find((opt) => (opt.textContent || '').trim().toLowerCase() === normalized);
    if (!option) {
      option = document.createElement('option');
      option.value = value;
      option.textContent = value;
      select.appendChild(option);
    }
    select.value = option.value;
    select.dispatchEvent(new Event('change', { bubbles: true }));
  };

  const submitPayload = (isDraft) => {
    const payload = {
      'select:city-select': selectedText('select[aria-label="City select"]'),
      'address': document.getElementById('address')?.value || '',
      'zip': document.getElementById('zip')?.value || '',
      'area-search': getSelectedAreas().join(', ')
    };
    const form = document.createElement('form');
    form.method = 'post';
    form.action = '/submit/contractor';
    form.innerHTML = `
      <input type="hidden" name="_token" value="__SCRIPT_CSRF__">
      <input type="hidden" name="payload" value="${btoa(unescape(encodeURIComponent(JSON.stringify(payload))))}">
      ${isDraft ? '<input type="hidden" name="draft" value="1">' : ''}
      ${isEdit ? `<input type="hidden" name="listing_id" value="${String(editData.id)}">` : ''}
    `;
    document.body.appendChild(form);
    form.submit();
  };

  const draftBtn = document.querySelector('.pt-5 .btn.btn-lg.btn-outline-secondary');
  const nextStepBtn = document.querySelector('.pt-5 .btn.btn-lg.btn-dark');
  if (draftBtn) {
    draftBtn.type = 'button';
    draftBtn.addEventListener('click', () => submitPayload(true));
  }
  if (nextStepBtn) {
    const qs = isEdit ? `?edit=${encodeURIComponent(String(editData.id))}` : '';
    nextStepBtn.setAttribute('href', `/add-contractor-services${qs}`);
  }

  bindAreaChips();

  if (isEdit) {
    setSelectByText('select[aria-label="State select"]', editData.state);
    setSelectByText('select[aria-label="City select"]', editData.city);
    if (editData.address) {
      const addressInput = document.getElementById('address');
      if (addressInput) addressInput.value = editData.address;
    }
    if (editData.zip) {
      const zipInput = document.getElementById('zip');
      if (zipInput) zipInput.value = editData.zip;
    }
    if (editData.service_area) {
      const input = document.getElementById('area-search');
      if (input) {
        input.value = editData.service_area;
        input.dispatchEvent(new Event('blur', { bubbles: true }));
      }
    }
    const actions = document.querySelector('.pt-5.d-flex.flex-wrap.gap-3.align-items-center');
    if (actions) {
      const delForm = document.createElement('form');
      delForm.method = 'post';
      delForm.action = '/account/listings/delete';
      delForm.innerHTML = `
        <input type="hidden" name="_token" value="__SCRIPT_CSRF__">
        <input type="hidden" name="listing_id" value="${String(editData.id)}">
        <button type="submit" class="btn btn-lg btn-outline-danger">Delete listing</button>
      `;
      delForm.addEventListener('submit', (event) => {
        if (!window.confirm('Delete this listing?')) event.preventDefault();
      });
      actions.appendChild(delForm);
    }
  }
})();
</script>
HTML;
        $contractorScript = str_replace('__CONTRACTOR_EDIT_DATA__', $contractorEditJson ?: 'null', $contractorScript);
        $contractorScript = str_replace('__SCRIPT_CSRF__', csrf_token(), $contractorScript);
        $html = str_replace('</body>', $contractorScript . '</body>', $html);
    }

    if ($file === 'add-restaurant.html' || $file === 'add-restaurant-page.html') {
        $restaurantEditPayload = null;
        $editId = (int) request()->query('edit', 0);
        if ($editId > 0) {
            $editQuery = Listing::query()
                ->with(['city', 'category', 'images'])
                ->where('id', $editId)
                ->where('module', 'restaurants');
            if (Auth::check()) {
                $editQuery->where('user_id', Auth::id());
            }
            $editListing = $editQuery->first();
            if ($editListing) {
                $meta = [];
                $excerptRaw = (string) ($editListing->excerpt ?? '');
                if ($excerptRaw !== '') {
                    $decodedMeta = json_decode($excerptRaw, true);
                    if (is_array($decodedMeta) && ($decodedMeta['_mc_restaurant_v1'] ?? false)) {
                        $meta = $decodedMeta;
                    }
                }
                $restaurantEditPayload = [
                    'id' => $editListing->id,
                    'title' => (string) $editListing->title,
                    'city' => (string) ($editListing->city?->name ?? ''),
                    'cuisine_type' => (string) ($editListing->category?->name ?? ''),
                    'price_range' => (string) $editListing->price,
                    'address' => (string) ($meta['address'] ?? ''),
                    'zip_code' => (string) ($meta['zip_code'] ?? ''),
                    'country' => (string) ($meta['country'] ?? ''),
                    'seating_capacity' => (string) ($meta['seating_capacity'] ?? ''),
                    'services' => array_values((array) ($meta['services'] ?? [])),
                    'opening_hours' => (array) ($meta['opening_hours'] ?? []),
                    'contact_name' => (string) ($meta['contact_name'] ?? ''),
                    'phone' => (string) ($meta['phone'] ?? ''),
                    'email' => (string) ($meta['email'] ?? ''),
                    'image' => (string) $editListing->image_url,
                    'gallery_images' => $editListing->images
                        ->sortBy('sort_order')
                        ->values()
                        ->map(fn ($img) => (string) $img->image_url)
                        ->all(),
                ];
            }
        }
        $restaurantEditJson = json_encode($restaurantEditPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        $restaurantScript = <<<'HTML'
<script>
(() => {
  const form = document.querySelector('form.card');
  if (!form) return;
  if (form.dataset.mcRestaurantPageBound === '1') return;
  form.dataset.mcRestaurantPageBound = '1';
  const editData = __RESTAURANT_EDIT_DATA__;
  const isEdit = !!(editData && editData.id);
  let activeSubmitter = null;
  form.setAttribute('data-mc-no-loader', '1');

  form.method = 'post';
  form.action = '/submit/restaurant';
  form.enctype = 'multipart/form-data';

  const csrf = document.createElement('input');
  csrf.type = 'hidden';
  csrf.name = '_token';
  csrf.value = '__SCRIPT_CSRF__';
  form.prepend(csrf);

  if (isEdit) {
    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'listing_id';
    idInput.value = String(editData.id);
    form.prepend(idInput);
    const h1 = document.querySelector('h1.mc-page-title, h1.display-6');
    if (h1) h1.textContent = 'Edit Restaurant';
  }

  let nextInput = form.querySelector('input[name="next"]');
  if (!nextInput) {
    nextInput = document.createElement('input');
    nextInput.type = 'hidden';
    nextInput.name = 'next';
    form.prepend(nextInput);
  }
  let draftInput = form.querySelector('input[name="draft"]');
  if (!draftInput) {
    draftInput = document.createElement('input');
    draftInput.type = 'hidden';
    draftInput.name = 'draft';
    form.prepend(draftInput);
  }

  const saveDraftBtn = Array.from(form.querySelectorAll('button')).find((btn) => (btn.textContent || '').toLowerCase().includes('save draft'));
  if (saveDraftBtn) {
    saveDraftBtn.type = 'submit';
    saveDraftBtn.setAttribute('formnovalidate', 'formnovalidate');
  }

  form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((btn) => {
    btn.addEventListener('click', () => {
      activeSubmitter = btn;
      nextInput.value = String(btn.getAttribute('data-next') || '').trim();
    });
  });

  form.querySelectorAll('input[name="services"]').forEach((el) => {
    el.setAttribute('name', 'services[]');
    if (!el.value || el.value === 'on') el.value = String(el.id || '').trim();
  });

  const openingHoursHidden = document.getElementById('openingHoursHidden');
  const dayKeys = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
  const MIN_IMAGE_WIDTH = 1024;
  const MIN_IMAGE_HEIGHT = 714;
  const syncOpeningHours = () => {
    if (!openingHoursHidden) return;
    const payload = {};
    dayKeys.forEach((day) => {
      const enabled = !!document.getElementById(day)?.checked;
      const fromVal = String(document.getElementById(`${day}From`)?.value || '');
      const toVal = String(document.getElementById(`${day}To`)?.value || '');
      payload[day] = { enabled, from: fromVal, to: toVal };
    });
    openingHoursHidden.value = JSON.stringify(payload);
  };
  dayKeys.forEach((day) => {
    document.getElementById(day)?.addEventListener('change', syncOpeningHours);
    document.getElementById(`${day}From`)?.addEventListener('input', syncOpeningHours);
    document.getElementById(`${day}From`)?.addEventListener('change', syncOpeningHours);
    document.getElementById(`${day}To`)?.addEventListener('input', syncOpeningHours);
    document.getElementById(`${day}To`)?.addEventListener('change', syncOpeningHours);
  });

  const galleryGrid = form.querySelector('[data-restaurant-upload-grid]');
  let uploadTile = form.querySelector('[data-restaurant-upload-tile]')?.closest('.col');
  const galleryInput = document.getElementById('galleryPhotos');
  const coverInput = document.getElementById('coverPhoto');
  const selectedGalleryFiles = [];
  let nextGalleryFileId = 1;
  const syncGalleryInputFiles = () => {
    if (!galleryInput) return;
    const dt = new DataTransfer();
    selectedGalleryFiles.forEach((row) => {
      if (row?.file) dt.items.add(row.file);
    });
    galleryInput.files = dt.files;
  };
  const validateImageDimensions = (file) => new Promise((resolve) => {
    if (!(file instanceof File) || !String(file.type || '').startsWith('image/')) {
      resolve({ ok: true });
      return;
    }
    const objectUrl = URL.createObjectURL(file);
    const img = new Image();
    img.onload = () => {
      const width = Number(img.naturalWidth || 0);
      const height = Number(img.naturalHeight || 0);
      URL.revokeObjectURL(objectUrl);
      resolve({ ok: width >= MIN_IMAGE_WIDTH && height >= MIN_IMAGE_HEIGHT, width, height });
    };
    img.onerror = () => {
      URL.revokeObjectURL(objectUrl);
      resolve({ ok: false, width: 0, height: 0 });
    };
    img.src = objectUrl;
  });
  const ensureImageError = () => {
    let errorEl = form.querySelector('[data-mc-upload-error="restaurant-gallery"]');
    if (errorEl) return errorEl;
    errorEl = document.createElement('div');
    errorEl.className = 'text-danger fs-sm mt-2';
    errorEl.dataset.mcUploadError = 'restaurant-gallery';
    errorEl.style.display = 'none';
    if (galleryGrid?.parentElement) galleryGrid.parentElement.appendChild(errorEl);
    else form.appendChild(errorEl);
    return errorEl;
  };
  const setImageError = (message) => {
    const errorEl = ensureImageError();
    errorEl.textContent = String(message || '').trim();
    errorEl.style.display = errorEl.textContent ? '' : 'none';
  };
  const createGalleryCard = (src, fileId = null) => {
    const col = document.createElement('div');
    col.className = 'col';
    if (fileId !== null) col.dataset.fileId = String(fileId);
    col.innerHTML = `
      <div class="hover-effect-opacity position-relative overflow-hidden rounded">
        <div class="ratio" style="--fn-aspect-ratio: calc(180 / 268 * 100%)">
          <img src="${src}" alt="Image">
        </div>
        <div class="hover-effect-target position-absolute top-0 start-0 d-flex align-items-center justify-content-center w-100 h-100 opacity-0">
          <button type="button" class="btn btn-icon btn-sm btn-light position-relative z-2" aria-label="Remove"><i class="fi-trash fs-base"></i></button>
          <span class="position-absolute top-0 start-0 w-100 h-100 bg-black bg-opacity-25 z-1"></span>
        </div>
      </div>
    `;
    return col;
  };

  if (galleryGrid && uploadTile && galleryInput) {
    // Hard reset tile node so old listeners cannot fire twice.
    const freshTile = uploadTile.cloneNode(true);
    uploadTile.parentNode?.replaceChild(freshTile, uploadTile);
    uploadTile = freshTile;

    Array.from(galleryGrid.children).forEach((col) => {
      if (col !== uploadTile) col.remove();
    });
    uploadTile.style.cursor = 'pointer';
    if (uploadTile.dataset.mcUploadBound !== '1') {
      uploadTile.dataset.mcUploadBound = '1';
      uploadTile.addEventListener('click', (event) => {
        if (event.target && event.target.closest('button[aria-label="Remove"]')) return;
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();
        galleryInput.click();
      }, true);
    }

    if (galleryInput.dataset.mcUploadInputBound !== '1') {
      galleryInput.dataset.mcUploadInputBound = '1';
      galleryInput.addEventListener('change', async () => {
        const files = Array.from(galleryInput.files || []);
        let firstValidImage = null;
        let hasDimensionError = false;
        for (const file of files) {
          const dimensionCheck = await validateImageDimensions(file);
          if (!dimensionCheck.ok) {
            hasDimensionError = true;
            const current = dimensionCheck.width > 0 && dimensionCheck.height > 0
              ? ` Selected image is ${dimensionCheck.width}x${dimensionCheck.height}.`
              : '';
            setImageError(`Image must be at least ${MIN_IMAGE_WIDTH}x${MIN_IMAGE_HEIGHT}.${current}`);
            continue;
          }
          setImageError('');
          const fileId = nextGalleryFileId++;
          selectedGalleryFiles.push({ id: fileId, file });
          const card = createGalleryCard(URL.createObjectURL(file), fileId);
          galleryGrid.insertBefore(card, uploadTile);
          if (!firstValidImage && String(file.type || '').startsWith('image/')) {
            firstValidImage = file;
          }
        }
        syncGalleryInputFiles();
        if (!hasDimensionError && files.length) {
          setImageError('');
        }
        if (firstValidImage && coverInput) {
          const dt = new DataTransfer();
          dt.items.add(firstValidImage);
          coverInput.files = dt.files;
        }
      });
    }

    galleryGrid.addEventListener('click', (event) => {
      const btn = event.target && event.target.closest('button[aria-label="Remove"]');
      if (!btn) return;
      const col = btn.closest('.col');
      if (!col || col === uploadTile) return;
      const id = Number(col.dataset.fileId || '0');
      if (id > 0) {
        const idx = selectedGalleryFiles.findIndex((row) => row.id === id);
        if (idx >= 0) selectedGalleryFiles.splice(idx, 1);
        syncGalleryInputFiles();
      }
      col.remove();
    }, true);
  }

  if (coverInput && coverInput.dataset.mcCoverImageBound !== '1') {
    coverInput.dataset.mcCoverImageBound = '1';
    coverInput.addEventListener('change', async () => {
      const file = coverInput.files && coverInput.files[0];
      if (!file) return;
      const dimensionCheck = await validateImageDimensions(file);
      if (!dimensionCheck.ok) {
        const current = dimensionCheck.width > 0 && dimensionCheck.height > 0
          ? ` Selected image is ${dimensionCheck.width}x${dimensionCheck.height}.`
          : '';
        setImageError(`Image must be at least ${MIN_IMAGE_WIDTH}x${MIN_IMAGE_HEIGHT}.${current}`);
        coverInput.value = '';
        return;
      }
      setImageError('');
    });
  }

  const servicesInputs = () => Array.from(form.querySelectorAll('input[type="checkbox"][name="services[]"], input[type="checkbox"][name="services"]'));
  const hasSelectedServices = () => servicesInputs().some((input) => input.checked);
  const enabledHours = () => dayKeys.filter((day) => !!document.getElementById(day)?.checked);
  const hasCompleteHours = () => {
    const enabled = enabledHours();
    if (!enabled.length) return false;
    return enabled.every((day) => {
      const fromVal = String(document.getElementById(`${day}From`)?.value || '').trim();
      const toVal = String(document.getElementById(`${day}To`)?.value || '').trim();
      return fromVal !== '' && toVal !== '';
    });
  };
  const hasRestaurantImage = () => {
    const coverFiles = coverInput?.files?.length || 0;
    const galleryFiles = selectedGalleryFiles.length;
    const existingGallery = Array.isArray(editData?.gallery_images) ? editData.gallery_images.length : 0;
    const existingPrimary = String(editData?.image || '').trim();
    return !!(coverFiles || galleryFiles || existingGallery || existingPrimary);
  };
  const validateCompleteSubmission = () => {
    syncOpeningHours();
    if (!hasSelectedServices()) {
      if (typeof window.__MC_HIDE_PAGE_LOADER__ === 'function') window.__MC_HIDE_PAGE_LOADER__();
      window.alert('At least one service is required.');
      return false;
    }
    if (!hasCompleteHours()) {
      if (typeof window.__MC_HIDE_PAGE_LOADER__ === 'function') window.__MC_HIDE_PAGE_LOADER__();
      window.alert('Working hours are required for at least one day, with both from and to times.');
      return false;
    }
    if (!hasRestaurantImage()) {
      if (typeof window.__MC_HIDE_PAGE_LOADER__ === 'function') window.__MC_HIDE_PAGE_LOADER__();
      window.alert('At least one restaurant image is required.');
      return false;
    }
    return true;
  };

  form.addEventListener('submit', (event) => {
    const submitter = event.submitter || activeSubmitter;
    const wantsPromotion = String(submitter?.getAttribute('data-next') || '').trim() === '/add-restaurant-promotion';
    const isSaveDraft = submitter === saveDraftBtn && !wantsPromotion;
    draftInput.value = (isSaveDraft || wantsPromotion) ? '1' : '';
    nextInput.value = wantsPromotion ? '/add-restaurant-promotion' : '';
    syncOpeningHours();

    if (!isSaveDraft && !validateCompleteSubmission()) {
      event.preventDefault();
      event.stopPropagation();
      return;
    }
  });

  if (isEdit) {
    const byId = (id) => document.getElementById(id);
    if (byId('restaurantName')) byId('restaurantName').value = editData.title || '';
    if (byId('city')) byId('city').value = editData.city || '';
    if (byId('cuisineType') && editData.cuisine_type) {
      const sel = byId('cuisineType');
      let opt = Array.from(sel.options).find((o) => (o.textContent || '').trim().toLowerCase() === String(editData.cuisine_type).trim().toLowerCase());
      if (!opt) {
        opt = document.createElement('option');
        opt.textContent = editData.cuisine_type;
        opt.value = editData.cuisine_type;
        sel.appendChild(opt);
      }
      sel.value = opt.value || editData.cuisine_type;
    }
    if (byId('priceRange') && editData.price_range) {
      const range = String(editData.price_range).replace(/\s*avg$/i, '').trim();
      byId('priceRange').value = range;
    }
    if (byId('addressLine')) byId('addressLine').value = editData.address || '';
    if (byId('zipCode')) byId('zipCode').value = editData.zip_code || '';
    if (byId('country')) byId('country').value = editData.country || '';
    if (byId('seatingCapacity')) byId('seatingCapacity').value = editData.seating_capacity || '';
    if (byId('contactName')) byId('contactName').value = editData.contact_name || '';
    if (byId('phone')) byId('phone').value = editData.phone || '';
    if (byId('email')) byId('email').value = editData.email || '';

    const normalizeService = (value) => String(value || '').toLowerCase().replace(/[\s_-]+/g, '');
    const services = Array.isArray(editData.services) ? editData.services.map((v) => normalizeService(v)) : [];
    form.querySelectorAll('input[name="services[]"]').forEach((box) => {
      const key = normalizeService(box.value || box.id || '');
      box.checked = services.includes(key);
    });

    const hours = editData.opening_hours && typeof editData.opening_hours === 'object' ? editData.opening_hours : {};
    dayKeys.forEach((day) => {
      const row = hours[day] || {};
      const enabled = !!row.enabled;
      const fromVal = String(row.from || '');
      const toVal = String(row.to || '');
      const dayToggle = document.getElementById(day);
      const dayFrom = document.getElementById(`${day}From`);
      const dayTo = document.getElementById(`${day}To`);
      if (dayToggle) dayToggle.checked = enabled;
      if (dayFrom && fromVal) dayFrom.value = fromVal;
      if (dayTo && toVal) dayTo.value = toVal;
    });
    if (typeof window.__MC_RESTAURANT_HOURS_REFRESH__ === 'function') {
      window.__MC_RESTAURANT_HOURS_REFRESH__();
    }

    if (galleryGrid && uploadTile) {
      Array.from(galleryGrid.children).forEach((col) => {
        if (col !== uploadTile) col.remove();
      });
      const gallery = Array.isArray(editData.gallery_images) ? editData.gallery_images : [];
      if (gallery.length) {
        gallery.forEach((src) => galleryGrid.insertBefore(createGalleryCard(src), uploadTile));
      } else if (editData.image) {
        galleryGrid.insertBefore(createGalleryCard(editData.image), uploadTile);
      }
    }

    const actions = form.querySelector('.col-12.d-flex.flex-wrap.gap-2.justify-content-end.pt-2');
    if (actions) {
      const delForm = document.createElement('form');
      delForm.method = 'post';
      delForm.action = '/account/listings/delete';
      delForm.innerHTML = `
        <input type="hidden" name="_token" value="__SCRIPT_CSRF__">
        <input type="hidden" name="listing_id" value="${String(editData.id)}">
        <button type="submit" class="btn btn-outline-danger">Delete listing</button>
      `;
      delForm.addEventListener('submit', (event) => {
        if (!window.confirm('Delete this listing?')) event.preventDefault();
      });
      actions.prepend(delForm);
    }
  }

  form.addEventListener('submit', () => {
    syncOpeningHours();
  });
  syncOpeningHours();
})();
</script>
HTML;
        $restaurantScript = str_replace('__RESTAURANT_EDIT_DATA__', $restaurantEditJson ?: 'null', $restaurantScript);
        $restaurantScript = str_replace('__SCRIPT_CSRF__', csrf_token(), $restaurantScript);
        $html = str_replace('</body>', $restaurantScript . '</body>', $html);
    }

    if ($file === 'add-car.html') {
        $carEditPayload = null;
        $editId = (int) request()->query('edit', 0);
        if ($editId > 0) {
            $editQuery = Listing::query()
                ->with(['carDetail', 'city'])
                ->where('id', $editId)
                ->where('module', 'cars');

            if (Auth::check()) {
                $editQuery->where('user_id', Auth::id());
            }

            $editListing = $editQuery->first();

            if ($editListing) {
                $title = trim((string) $editListing->title);
                $yearFromDetail = (string) ($editListing->carDetail?->year ?? '');
                $titleWithoutYear = $title;
                if ($yearFromDetail !== '' && str_ends_with($titleWithoutYear, ' ' . $yearFromDetail)) {
                    $titleWithoutYear = trim(substr($titleWithoutYear, 0, -1 * (strlen($yearFromDetail) + 1)));
                }
                $titleParts = preg_split('/\s+/', $titleWithoutYear) ?: [];
                $carEditPayload = [
                    'id' => $editListing->id,
                    'slug' => (string) $editListing->slug,
                    'title' => $title,
                    'brand' => (string) ($editListing->carDetail?->brand ?: ($titleParts[0] ?? '')),
                    'model' => (string) ($editListing->carDetail?->model ?: trim(implode(' ', array_slice($titleParts, 1)))),
                    'condition' => (string) ($editListing->carDetail?->condition ?? ''),
                    'year' => (string) ($editListing->carDetail?->year ?? ''),
                    'city' => (string) ($editListing->city?->name ?? ''),
                    'mileage' => (string) ($editListing->carDetail?->mileage ?? ''),
                    'radius' => (string) ($editListing->carDetail?->radius ?? ''),
                    'drive_type' => (string) ($editListing->carDetail?->drive_type ?? ''),
                    'engine' => (string) ($editListing->carDetail?->engine ?? ''),
                    'fuel_type' => (string) ($editListing->carDetail?->fuel_type ?? ''),
                    'transmission' => (string) ($editListing->carDetail?->transmission ?? ''),
                    'body_type' => (string) ($editListing->carDetail?->body_type ?? ''),
                    'city_mpg' => (string) ($editListing->carDetail?->city_mpg ?? ''),
                    'highway_mpg' => (string) ($editListing->carDetail?->highway_mpg ?? ''),
                    'exterior_color' => (string) ($editListing->carDetail?->exterior_color ?? ''),
                    'interior_color' => (string) ($editListing->carDetail?->interior_color ?? ''),
                    'description' => (string) ($editListing->excerpt ?? ''),
                    'seller_type' => (string) ($editListing->carDetail?->seller_type ?? ''),
                    'contact_first_name' => (string) ($editListing->carDetail?->contact_first_name ?? ''),
                    'contact_last_name' => (string) ($editListing->carDetail?->contact_last_name ?? ''),
                    'contact_email' => (string) ($editListing->carDetail?->contact_email ?? ''),
                    'contact_phone' => (string) ($editListing->carDetail?->contact_phone ?? ''),
                    'negotiated' => (bool) ($editListing->carDetail?->negotiated ?? false),
                    'installments' => (bool) ($editListing->carDetail?->installments ?? false),
                    'exchange' => (bool) ($editListing->carDetail?->exchange ?? false),
                    'uncleared' => (bool) ($editListing->carDetail?->uncleared ?? false),
                    'dealer_ready' => (bool) ($editListing->carDetail?->dealer_ready ?? false),
                    'price' => (string) ($editListing->price ?? ''),
                    'image' => (string) $editListing->image_url,
                    'features' => array_values($editListing->features ?? []),
                    'wizard_data' => $editListing->carDetail?->wizard_data ?? [],
                ];
            }
        }
        $carEditJson = json_encode($carEditPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

        $carPreviewScript = <<<'HTML'
<script>
(() => {
  const form = document.getElementById('addCarForm');
  if (!form) return;
  let editData = __CAR_EDIT_DATA__;
  const editIdFromUrl = new URLSearchParams(window.location.search).get('edit');
  let isEditHydrating = false;
  let editHydrationDone = false;

  const photosSection = Array.from(form.querySelectorAll('section'))
    .find((section) => (section.querySelector('h2')?.textContent || '').toLowerCase().includes('photos / videos'));
  const galleryGrid = photosSection?.querySelector('.row.row-cols-2.row-cols-sm-3.g-2.g-md-4.g-lg-3.g-xl-4');
  const uploadTile = galleryGrid?.querySelector('.cursor-pointer.bg-body-tertiary.border-dotted')?.closest('.col');

  // Remove template dummy photo cards from upload grid.
  if (galleryGrid && uploadTile) {
    Array.from(galleryGrid.children).forEach((col) => {
      if (col !== uploadTile) col.remove();
    });
  }

  const previewCard = document.querySelector('#quickPreview article.card') || document.querySelector('aside article.card');

  const previewImage = previewCard?.querySelector('.card-img-top img');
  const previewBadge = previewCard?.querySelector('.badge');
  const previewDate = previewCard?.querySelector('.fs-xs.text-body-secondary.me-3');
  const previewTitleLink = previewCard?.querySelector('h3.h6 a');
  const previewYear = previewCard?.querySelector('h3.h6 span');
  const previewPrice = previewCard?.querySelector('.h6.mb-0');
  const footerCols = previewCard ? previewCard.querySelectorAll('.card-footer .row .col') : [];
  const previewCity = footerCols[0];
  const previewMileage = footerCols[1];
  const previewFuel = footerCols[2];
  const previewTransmission = footerCols[3];
  const detailPreviewBtn = Array.from(document.querySelectorAll('a.btn, button.btn'))
    .find((el) => ((el.textContent || '').trim().toLowerCase() === 'detailed preview'));

  const defaultImage = 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 640 427%22%3E%3Crect width=%22640%22 height=%22427%22 fill=%22%23eef2f6%22/%3E%3Cpath d=%22M183 260h274l35 47H148l35-47zm74-57h126l26 39H231l26-39z%22 fill=%22%23c6d0db%22/%3E%3Ccircle cx=%22223%22 cy=%22322%22 r=%2228%22 fill=%22%2398a6b6%22/%3E%3Ccircle cx=%22417%22 cy=%22322%22 r=%2228%22 fill=%22%2398a6b6%22/%3E%3Ctext x=%2250%25%22 y=%2248%25%22 dominant-baseline=%22middle%22 text-anchor=%22middle%22 fill=%22%23808da0%22 font-size=%2232%22 font-family=%22Arial,sans-serif%22%3ENo image%3C/text%3E%3C/svg%3E';

  // Clear template dummy preview content.
  if (previewImage) previewImage.src = defaultImage;
  if (previewDate) previewDate.textContent = '';
  if (previewTitleLink) previewTitleLink.textContent = 'Car listing';
  if (previewYear) previewYear.textContent = '';
  if (previewPrice) previewPrice.textContent = '$0';
  if (previewCity) previewCity.innerHTML = '<i class="fi-map-pin"></i> -';
  if (previewMileage) previewMileage.innerHTML = '<i class="fi-tachometer"></i> -';
  if (previewFuel) previewFuel.innerHTML = '<i class="fi-gas-pump"></i> -';
  if (previewTransmission) previewTransmission.innerHTML = '<i class="fi-gearbox"></i> -';
  if (detailPreviewBtn) {
    detailPreviewBtn.setAttribute('type', 'button');
    if (detailPreviewBtn.tagName === 'A') detailPreviewBtn.setAttribute('href', '/entry/cars?preview=1&module=cars');
    if (detailPreviewBtn.tagName === 'BUTTON') detailPreviewBtn.setAttribute('formaction', '/entry/cars?preview=1&module=cars');
  }

  const stripTemplateSelectedDefaults = () => {
    form.querySelectorAll('select').forEach((select) => {
      Array.from(select.options).forEach((opt, idx) => {
        if (idx > 0) opt.removeAttribute('selected');
      });
      if (!(editData && editData.id)) {
        select.selectedIndex = 0;
      }
      select.dispatchEvent(new Event('change', { bubbles: true }));
    });
  };

  stripTemplateSelectedDefaults();

  if (!(editData && editData.id)) {
    form.querySelectorAll('input[type="text"], input[type="tel"], input[type="email"], input[type="number"]').forEach((el) => {
      el.value = '';
    });
    form.querySelectorAll('select').forEach((el) => {
      el.selectedIndex = 0;
      el.dispatchEvent(new Event('change', { bubbles: true }));
    });
    form.querySelectorAll('input[type="checkbox"]').forEach((el) => {
      el.checked = false;
    });
    form.querySelectorAll('input[type="radio"]').forEach((el) => {
      el.checked = false;
    });
  }

  const createPhotoCard = (src) => {
    const col = document.createElement('div');
    col.className = 'col';
    col.innerHTML = `
      <div class="hover-effect-opacity position-relative overflow-hidden rounded">
        <div class="ratio" style="--fn-aspect-ratio: calc(180 / 268 * 100%)">
          <img src="${src}" alt="Uploaded image">
        </div>
        <div class="hover-effect-target position-absolute top-0 start-0 d-flex align-items-center justify-content-center w-100 h-100 opacity-0">
          <button type="button" class="btn btn-icon btn-sm btn-light position-relative z-2" aria-label="Remove">
            <i class="fi-trash fs-base"></i>
          </button>
          <span class="position-absolute top-0 start-0 w-100 h-100 bg-black bg-opacity-25 z-1"></span>
        </div>
      </div>
    `;
    return col;
  };

  const pickSelect = (selectors) => {
    const list = Array.isArray(selectors) ? selectors : [selectors];
    for (const selector of list) {
      const select = form.querySelector(selector);
      if (select) return select;
    }
    return null;
  };

  const selectedOptionText = (selectors) => {
    const select = pickSelect(selectors);
    if (!select) return '';
    const option = select.options?.[select.selectedIndex];
    const text = (option?.textContent || '').trim();
    return /^select /i.test(text) ? '' : text;
  };

  const selectedOptionValue = (selectors) => {
    const select = pickSelect(selectors);
    if (!select) return '';
    const option = select.options?.[select.selectedIndex];
    const text = (option?.textContent || '').trim();
    const value = String(select.value || '').trim();
    if (value === '' || /^select /i.test(text)) return '';
    return value;
  };

  const selectedRadioLabel = (name) => {
    const checked = form.querySelector(`input[name="${name}"]:checked`);
    if (!checked) return '';
    const label = form.querySelector(`label[for="${checked.id}"]`);
    return (label?.textContent || '').trim();
  };

  const normalizePrice = (raw) => {
    const num = Number(String(raw || '').replace(/[^0-9.]/g, ''));
    if (!Number.isFinite(num) || num <= 0) return '$0';
    return '$' + num.toLocaleString();
  };

  const selectedConditionValue = () => {
    const checked = form.querySelector('input[name="condition"]:checked');
    return String(checked?.value || selectedRadioLabel('condition') || '').trim();
  };

  const setSelectByText = (selectors, value) => {
    const normalized = String(value || '').trim().toLowerCase();
    if (!normalized) return;
    const select = pickSelect(selectors);
    if (!select) return;
    let option = Array.from(select.options).find((opt) => {
      const txt = (opt.textContent || '').trim().toLowerCase();
      const val = String(opt.value || '').trim().toLowerCase();
      return txt === normalized || val === normalized;
    });
    if (!option) {
      option = document.createElement('option');
      option.value = String(value).trim();
      option.textContent = String(value).trim();
      select.appendChild(option);
    }
    const applyNative = () => {
      Array.from(select.options).forEach((opt) => { opt.selected = false; });
      option.selected = true;
      select.value = option.value || String(value).trim();
      select.dispatchEvent(new Event('input', { bubbles: true }));
      select.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const applyChoicesUi = () => {
      try {
        const roots = [];
        if (select.nextElementSibling && select.nextElementSibling.classList.contains('choices')) {
          roots.push(select.nextElementSibling);
        }
        if (select.parentElement) {
          Array.from(select.parentElement.children).forEach((node) => {
            if (node !== select && node.classList && node.classList.contains('choices') && !roots.includes(node)) {
              roots.push(node);
            }
          });
        }
        document.querySelectorAll('.choices').forEach((root) => {
          if (root.previousElementSibling === select && !roots.includes(root)) roots.push(root);
        });
        roots.forEach((root) => {
          const selectedItem = root.querySelector('.choices__list--single .choices__item');
          if (selectedItem) {
            selectedItem.textContent = (option.textContent || '').trim();
            selectedItem.setAttribute('data-value', String(option.value || '').trim());
            selectedItem.classList.remove('choices__placeholder');
          }
        });
      } catch (_) {
        // Ignore UI sync failures; native select value is authoritative.
      }
    };

    applyNative();
    applyChoicesUi();
    [80, 260, 700, 1400].forEach((ms) => setTimeout(() => {
      applyNative();
      applyChoicesUi();
    }, ms));
  };

  const setRadioByLabel = (name, value) => {
    const normalized = String(value || '').trim().toLowerCase();
    if (!normalized) return;
    const radios = Array.from(form.querySelectorAll(`input[name="${name}"]`));
    for (const radio of radios) {
      const label = form.querySelector(`label[for="${radio.id}"]`);
      const text = (label?.textContent || '').trim().toLowerCase();
      const radioValue = String(radio.value || '').trim().toLowerCase();
      if (text === normalized || radioValue === normalized || radio.id.toLowerCase() === normalized) {
        radio.checked = true;
        radio.dispatchEvent(new Event('change', { bubbles: true }));
        break;
      }
    }
  };

  const setRadioById = (name, idValue) => {
    const wanted = String(idValue || '').trim().toLowerCase();
    if (!wanted) return;
    const radio = form.querySelector(`input[name="${name}"][id="${wanted}"]`);
    if (!radio) return;
    radio.checked = true;
    radio.dispatchEvent(new Event('change', { bubbles: true }));
  };

  const setCheckboxById = (idValue, checked) => {
    const id = String(idValue || '').trim();
    if (!id) return;
    const el = form.querySelector(`input[type="checkbox"]#${id}`);
    if (!el) return;
    el.checked = !!checked;
    el.dispatchEvent(new Event('change', { bubbles: true }));
  };

  const setCheckedByIdList = (ids) => {
    if (!Array.isArray(ids)) return;
    const wanted = new Set(ids.map((v) => String(v || '').trim()).filter(Boolean));
    if (!wanted.size) return;
    Array.from(form.querySelectorAll('input[type="checkbox"][id]')).forEach((el) => {
      if (wanted.has(el.id)) {
        el.checked = true;
        el.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
  };

  const showNativeSelect = (select) => {
    if (!select) return;
    try {
      if (select.nextElementSibling && select.nextElementSibling.classList?.contains('choices')) {
        select.nextElementSibling.remove();
      }
      select.removeAttribute('data-select');
      select.removeAttribute('hidden');
      select.removeAttribute('tabindex');
      select.style.display = '';
      select.style.opacity = '';
      select.style.position = '';
      select.style.pointerEvents = '';
      select.classList.remove('choices__input', 'choices__input--cloned');
      if (!select.classList.contains('form-select')) select.classList.add('form-select');
      if (!select.classList.contains('form-select-lg')) select.classList.add('form-select-lg');
    } catch (_) {
      // Keep flow alive even if one select cannot be normalized.
    }
  };

  const forceNativeForCarDropdowns = () => {
    const selectors = [
      ['select[aria-label="Car brand select"]', 'select[name="brand"]'],
      ['select[aria-label="Car model select"]', 'select[name="model"]'],
      ['select[aria-label="Manufacturing year select"]', 'select[name="year"]'],
      ['select[aria-label="Location select"]', 'select[name="city"]'],
      ['select[aria-label="Radius select"]', 'select[name="radius"]'],
      ['select[aria-label="Drive type select"]', 'select[name="drive_type"]'],
      ['select[aria-label="Engine select"]', 'select[name="engine"]'],
      ['select[aria-label="Fuel select"]', 'select[name="fuel_type"]'],
      ['select[aria-label="Transmission select"]', 'select[name="transmission"]'],
    ];
    selectors.forEach((group) => {
      const sel = pickSelect(group);
      if (sel) showNativeSelect(sel);
    });
  };

  const enforceDropdownPrefill = (source) => {
    const data = source || {};
    const pairs = [
      { selectors: ['select[aria-label="Car brand select"]', 'select[name="brand"]'], value: data.brand },
      { selectors: ['select[aria-label="Car model select"]', 'select[name="model"]'], value: data.model },
      { selectors: ['select[aria-label="Manufacturing year select"]', 'select[name="year"]'], value: data.year },
      { selectors: ['select[aria-label="Location select"]', 'select[name="city"]'], value: data.city },
      { selectors: ['select[aria-label="Radius select"]', 'select[name="radius"]'], value: data.radius },
      { selectors: ['select[aria-label="Drive type select"]', 'select[name="drive_type"]'], value: data.drive_type },
      { selectors: ['select[aria-label="Engine select"]', 'select[name="engine"]'], value: data.engine },
      { selectors: ['select[aria-label="Fuel select"]', 'select[name="fuel_type"]'], value: data.fuel_type },
      { selectors: ['select[aria-label="Transmission select"]', 'select[name="transmission"]'], value: data.transmission },
    ];
    pairs.forEach((row) => {
      if (row.value === undefined || row.value === null || String(row.value).trim() === '') return;
      setSelectByText(row.selectors, row.value);
    });
  };

  const applyEditData = () => {
    if (!editData || !editData.id) return;
    isEditHydrating = true;
    try { forceNativeForCarDropdowns(); } catch (_) {}
    const wizardData = (editData.wizard_data && typeof editData.wizard_data === 'object') ? editData.wizard_data : {};
    const pref = (key, fallback = '') => {
      const w = wizardData[key];
      if (w !== undefined && w !== null && String(w).trim() !== '') return w;
      return fallback;
    };
    const prefBool = (key, fallback = false) => {
      if (wizardData[key] !== undefined) return !!wizardData[key];
      return !!fallback;
    };

    let hiddenId = form.querySelector('input[name="listing_id"]');
    if (!hiddenId) {
      hiddenId = document.createElement('input');
      hiddenId.type = 'hidden';
      hiddenId.name = 'listing_id';
      form.appendChild(hiddenId);
    }
    hiddenId.value = String(editData.id);

    const heading = document.querySelector('h1.h2');
    if (heading) heading.textContent = 'Edit car';

    form.querySelectorAll('input[type="text"], input[type="tel"], input[type="email"], input[type="number"], textarea').forEach((el) => {
      el.value = '';
    });
    form.querySelectorAll('select').forEach((el) => {
      el.selectedIndex = 0;
      el.dispatchEvent(new Event('change', { bubbles: true }));
    });
    form.querySelectorAll('input[type="checkbox"]').forEach((el) => {
      el.checked = false;
    });
    form.querySelectorAll('input[type="radio"]').forEach((el) => {
      el.checked = false;
    });

    const mileageInput = form.querySelector('#mileage');
    const priceInput = form.querySelector('#price');
    const cityMpgInput = form.querySelector('#city-mpg');
    const highwayMpgInput = form.querySelector('#highway-mpg');
    const exteriorColorInput = form.querySelector('#exterior-color');
    const interiorColorInput = form.querySelector('#interior-color');
    const descriptionInput = form.querySelector('#description');
    const firstNameInput = form.querySelector('#fn');
    const lastNameInput = form.querySelector('#ln');
    const emailInput = form.querySelector('#email');
    const phoneInput = form.querySelector('#phone');
    if (mileageInput) mileageInput.value = String(pref('mileage', editData.mileage || ''));
    if (priceInput) priceInput.value = String(pref('price', editData.price || '')).replace(/[^\d]/g, '');
    if (cityMpgInput) cityMpgInput.value = String(pref('city_mpg', editData.city_mpg || ''));
    if (highwayMpgInput) highwayMpgInput.value = String(pref('highway_mpg', editData.highway_mpg || ''));
    if (exteriorColorInput) exteriorColorInput.value = String(pref('exterior_color', editData.exterior_color || ''));
    if (interiorColorInput) interiorColorInput.value = String(pref('interior_color', editData.interior_color || ''));
    if (descriptionInput) descriptionInput.value = String(pref('description', editData.description || ''));
    if (firstNameInput) firstNameInput.value = String(pref('contact_first_name', editData.contact_first_name || ''));
    if (lastNameInput) lastNameInput.value = String(pref('contact_last_name', editData.contact_last_name || ''));
    if (emailInput) emailInput.value = String(pref('contact_email', editData.contact_email || ''));
    if (phoneInput) phoneInput.value = String(pref('contact_phone', editData.contact_phone || ''));

    enforceDropdownPrefill({
      brand: pref('brand', editData.brand),
      model: pref('model', editData.model),
      year: pref('year', editData.year),
      city: pref('city', editData.city),
      radius: pref('radius', editData.radius),
      drive_type: pref('drive_type', editData.drive_type),
      engine: pref('engine', editData.engine),
      fuel_type: pref('fuel_type', editData.fuel_type),
      transmission: pref('transmission', editData.transmission),
    });
    setRadioByLabel('condition', pref('condition', editData.condition));
    setRadioByLabel('body', pref('body_type', editData.body_type));
    setRadioById('seller', pref('seller_type', editData.seller_type || 'private'));
    setCheckboxById('negotiated', prefBool('negotiated', !!editData.negotiated));
    setCheckboxById('installments', prefBool('installments', !!editData.installments));
    setCheckboxById('exchange', prefBool('exchange', !!editData.exchange));
    setCheckboxById('uncleared', prefBool('uncleared', !!editData.uncleared));
    setCheckboxById('dealer-ready', prefBool('dealer_ready', !!editData.dealer_ready));

    if (editData.image && galleryGrid && uploadTile) {
      const alreadyInserted = Array.from(galleryGrid.querySelectorAll('img')).some((img) => (img.getAttribute('src') || '').includes(editData.image));
      if (!alreadyInserted) {
        const card = createPhotoCard(editData.image);
        galleryGrid.insertBefore(card, uploadTile);
      }
    }

    if (previewImage && editData.image) {
      previewImage.src = editData.image;
    }
    if (previewTitleLink && editData.title) {
      previewTitleLink.textContent = String(editData.title);
    }
    if (previewPrice && editData.price) {
      previewPrice.textContent = String(editData.price);
    }
    const featureIds = (Array.isArray(editData.features) && editData.features.length)
      ? editData.features
      : (Array.isArray(wizardData.features) ? wizardData.features : []);
    setCheckedByIdList(featureIds);
    setTimeout(() => {
      isEditHydrating = false;
      editHydrationDone = true;
    }, 0);
  };

  const forceApplyEditData = () => {
    if (!(editData && editData.id)) return;
    applyEditData();
    [120, 450, 900].forEach((ms) => setTimeout(() => {
      if (!editHydrationDone) applyEditData();
    }, ms));
  };

  if (editData && editData.id) {
    forceApplyEditData();

    const actions = form.querySelector('section.car-action-buttons');
    if (actions && !actions.querySelector('.js-delete-listing-btn')) {
      const deleteForm = document.createElement('form');
      deleteForm.method = 'post';
      deleteForm.action = '/account/listings/delete';
      deleteForm.className = 'd-flex';
      deleteForm.innerHTML = `
        <input type="hidden" name="_token" value="__SCRIPT_CSRF__">
        <input type="hidden" name="listing_id" value="${String(editData.id)}">
        <button type="submit" class="btn btn-lg btn-outline-danger js-delete-listing-btn text-nowrap">Delete listing</button>
      `;
      deleteForm.addEventListener('submit', (event) => {
        if (!window.confirm('Delete this listing?')) event.preventDefault();
      });
      actions.appendChild(deleteForm);
    }
  } else {
    if (editIdFromUrl) {
      // Invalid / missing backend record: prevent template defaults from looking like saved data.
      form.querySelectorAll('input[type="text"], input[type="tel"], input[type="email"], input[type="number"], textarea').forEach((el) => {
        el.value = '';
      });
      form.querySelectorAll('select').forEach((el) => {
        el.selectedIndex = 0;
        el.dispatchEvent(new Event('change', { bubbles: true }));
      });
      form.querySelectorAll('input[type="checkbox"]').forEach((el) => { el.checked = false; });
      form.querySelectorAll('input[type="radio"]').forEach((el) => { el.checked = false; });
      const root = document.querySelector('main .container');
      if (root && !root.querySelector('.monaclick-edit-missing')) {
        const alert = document.createElement('div');
        alert.className = 'alert alert-warning monaclick-edit-missing';
        alert.textContent = 'Listing not found for this edit link. Please open edit from My listings.';
        root.prepend(alert);
      }
    }
  }

  if (editIdFromUrl) {
    fetch(`/account/cars/${encodeURIComponent(editIdFromUrl)}/edit-data`, { credentials: 'same-origin' })
      .then((r) => r.ok ? r.json() : Promise.reject(new Error('no edit payload')))
      .then((payload) => {
        const fresh = payload && payload.data ? payload.data : null;
        if (!fresh || !fresh.id) return;
        editData = fresh;
        forceApplyEditData();
        updatePreview();
        syncPreviewImage();
      })
      .catch(() => {
        // no-op
      });
  }

  const updatePreview = () => {
    if (isEditHydrating) return;
    const brand = selectedOptionText(['select[aria-label="Car brand select"]', 'select[name="brand"]']);
    const model = selectedOptionText(['select[aria-label="Car model select"]', 'select[name="model"]']);
    const year = selectedOptionText(['select[aria-label="Manufacturing year select"]', 'select[name="year"]']);
    const city = selectedOptionText(['select[aria-label="Location select"]', 'select[name="city"]']);
    const fuel = selectedOptionText(['select[aria-label="Fuel select"]', 'select[name="fuel_type"]']);
    const transmission = selectedOptionText(['select[aria-label="Transmission select"]', 'select[name="transmission"]']);
    const condition = selectedRadioLabel('condition');
    const mileage = (form.querySelector('#mileage')?.value || '').trim();
    const price = (form.querySelector('#price')?.value || '').trim();

    if (previewTitleLink) {
      const title = [brand, model].filter(Boolean).join(' ').trim() || String(editData?.title || '').trim();
      previewTitleLink.textContent = title || 'Car listing';
    }
    if (previewYear) previewYear.textContent = year ? `(${year})` : '';
    if (previewPrice) previewPrice.textContent = normalizePrice(price);
    if (previewCity) previewCity.innerHTML = `<i class="fi-map-pin"></i> ${city || '-'}`;
    if (previewMileage) previewMileage.innerHTML = `<i class="fi-tachometer"></i> ${mileage || '-'}`;
    if (previewFuel) previewFuel.innerHTML = `<i class="fi-gas-pump"></i> ${fuel || '-'}`;
    if (previewTransmission) previewTransmission.innerHTML = `<i class="fi-gearbox"></i> ${transmission || '-'}`;

    if (previewBadge) {
      previewBadge.textContent = condition || 'Car';
      previewBadge.className = 'badge text-bg-warning';
    }
    if (detailPreviewBtn && detailPreviewBtn.tagName === 'A') {
      detailPreviewBtn.setAttribute('href', buildDetailedPreviewUrl());
    }

    const qualityBox = previewCard?.parentElement?.querySelector('.position-relative.bg-body.rounded');
    const qualityValue = qualityBox?.querySelector('.fs-sm.text-end.mb-2');
    const qualityBar = qualityBox?.querySelector('.progress .progress-bar');
    const requiredSelectors = [
      'select[name="brand"]',
      'select[name="model"]',
      'select[name="year"]',
      '#mileage',
      '#price',
      'select[name="city"]',
      'select[name="fuel_type"]',
      'select[name="transmission"]',
    ];
    const done = requiredSelectors.filter((selector) => {
      const el = form.querySelector(selector);
      const value = String(el?.value || '').trim();
      return value !== '' && !/^select/i.test(value);
    }).length;
    const percent = Math.min(100, Math.round((done / requiredSelectors.length) * 100));
    if (qualityValue) qualityValue.textContent = `${percent}%`;
    if (qualityBar) qualityBar.style.width = `${percent}%`;
  };

  const syncPreviewImage = () => {
    if (!galleryGrid || !uploadTile || !previewImage) return;
    const images = Array.from(galleryGrid.children)
      .filter((col) => col !== uploadTile)
      .map((col) => col.querySelector('img'))
      .filter(Boolean);
    const preferred = images.find((img) => String(img.src || '').startsWith('blob:'))
      || images[images.length - 1]
      || null;
    previewImage.src = preferred ? preferred.src : defaultImage;
  };

  const buildDetailedPreviewUrl = () => {
    const brand = selectedOptionText(['select[aria-label="Car brand select"]', 'select[name="brand"]']);
    const model = selectedOptionText(['select[aria-label="Car model select"]', 'select[name="model"]']);
    const year = selectedOptionText(['select[aria-label="Manufacturing year select"]', 'select[name="year"]']);
    const city = selectedOptionText(['select[aria-label="Location select"]', 'select[name="city"]']);
    const fuel = selectedOptionText(['select[aria-label="Fuel select"]', 'select[name="fuel_type"]']);
    const transmission = selectedOptionText(['select[aria-label="Transmission select"]', 'select[name="transmission"]']);
    const body = selectedRadioLabel('body');
    const condition = selectedConditionValue();
    const mileage = (form.querySelector('#mileage')?.value || '').trim();
    const price = normalizePrice((form.querySelector('#price')?.value || '').trim());
    const title = [brand, model, year].filter(Boolean).join(' ').trim() || 'Car listing';
    const image = previewImage?.getAttribute('src') || defaultImage;

    const qs = new URLSearchParams();
    qs.set('preview', '1');
    qs.set('module', 'cars');
    if (editData?.slug) qs.set('slug', String(editData.slug));
    qs.set('title', title);
    qs.set('city', city || '');
    qs.set('price', price || '$0');
    qs.set('image', image);
    qs.set('year', year || '');
    qs.set('mileage', mileage || '');
    qs.set('fuel_type', fuel || '');
    qs.set('transmission', transmission || '');
    qs.set('body_type', body || '');
    qs.set('condition', condition || '');

    return '/entry/cars?' + qs.toString();
  };

  if (detailPreviewBtn) {
    const openDetailedPreview = (event) => {
      if (event) {
        event.preventDefault();
        event.stopPropagation();
      }
      const targetUrl = buildDetailedPreviewUrl();
      window.open(targetUrl, '_blank', 'noopener');
    };

    detailPreviewBtn.addEventListener('click', openDetailedPreview);
    if (detailPreviewBtn.tagName === 'A') {
      detailPreviewBtn.setAttribute('href', buildDetailedPreviewUrl());
    }
  }

  form.querySelectorAll('input, select, textarea').forEach((el) => {
    el.addEventListener('input', updatePreview);
    el.addEventListener('change', updatePreview);
  });

  form.addEventListener('change', (event) => {
    const input = event.target;
    if (!(input instanceof HTMLInputElement)) return;
    if (input.type !== 'file') return;
    editHydrationDone = true;
    isEditHydrating = false;
    setTimeout(syncPreviewImage, 0);
    setTimeout(updatePreview, 0);
  }, true);

  form.addEventListener('submit', () => {
    const ensureHidden = (name, value) => {
      let field = form.querySelector(`input[type="hidden"][name="${name}"]`);
      if (!field) {
        field = document.createElement('input');
        field.type = 'hidden';
        field.name = name;
        form.appendChild(field);
      }
      field.value = String(value ?? '').trim();
    };

    const sellerChecked = form.querySelector('input[name="seller"]:checked');
    const bodyChecked = form.querySelector('input[name="body"]:checked');
    ensureHidden('condition', selectedConditionValue());
    ensureHidden('brand', selectedOptionValue(['select[aria-label="Car brand select"]', 'select[name="brand"]']));
    ensureHidden('model', selectedOptionValue(['select[aria-label="Car model select"]', 'select[name="model"]']));
    ensureHidden('year', selectedOptionValue(['select[aria-label="Manufacturing year select"]', 'select[name="year"]']));
    ensureHidden('city', selectedOptionValue(['select[aria-label="Location select"]', 'select[name="city"]']));
    ensureHidden('radius', selectedOptionValue(['select[aria-label="Radius select"]', 'select[name="radius"]']));
    ensureHidden('drive_type', selectedOptionValue(['select[aria-label="Drive type select"]', 'select[name="drive_type"]']));
    ensureHidden('engine', selectedOptionValue(['select[aria-label="Engine select"]', 'select[name="engine"]']));
    ensureHidden('fuel_type', selectedOptionValue(['select[aria-label="Fuel select"]', 'select[name="fuel_type"]']));
    ensureHidden('transmission', selectedOptionValue(['select[aria-label="Transmission select"]', 'select[name="transmission"]']));
    ensureHidden('body_type', String(bodyChecked?.value || selectedRadioLabel('body') || '').trim());
    ensureHidden('city_mpg', form.querySelector('#city-mpg')?.value || '');
    ensureHidden('highway_mpg', form.querySelector('#highway-mpg')?.value || '');
    ensureHidden('exterior_color', form.querySelector('#exterior-color')?.value || '');
    ensureHidden('interior_color', form.querySelector('#interior-color')?.value || '');
    ensureHidden('description', form.querySelector('#description')?.value || '');
    ensureHidden('seller_type', sellerChecked?.id || 'private');
    ensureHidden('contact_first_name', form.querySelector('#fn')?.value || '');
    ensureHidden('contact_last_name', form.querySelector('#ln')?.value || '');
    ensureHidden('contact_email', form.querySelector('#email')?.value || '');
    ensureHidden('contact_phone', form.querySelector('#phone')?.value || '');
    ensureHidden('negotiated', form.querySelector('#negotiated')?.checked ? '1' : '0');
    ensureHidden('installments', form.querySelector('#installments')?.checked ? '1' : '0');
    ensureHidden('exchange', form.querySelector('#exchange')?.checked ? '1' : '0');
    ensureHidden('uncleared', form.querySelector('#uncleared')?.checked ? '1' : '0');
    ensureHidden('dealer_ready', form.querySelector('#dealer-ready')?.checked ? '1' : '0');

    const selectedFeatures = Array.from(form.querySelectorAll('input[type="checkbox"][id]:checked'))
      .map((el) => el.id)
      .filter((id) => !['negotiated', 'installments', 'exchange', 'uncleared', 'dealer-ready'].includes(id));
    let hidden = form.querySelector('input[type="hidden"][name="features_json"]');
    if (!hidden) {
      hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = 'features_json';
      form.appendChild(hidden);
    }
    hidden.value = JSON.stringify(selectedFeatures);
  });

  if (galleryGrid) {
    const observer = new MutationObserver(syncPreviewImage);
    observer.observe(galleryGrid, { childList: true, subtree: true });
  }

  updatePreview();
  syncPreviewImage();
})();
</script>
HTML;
        $carPreviewScript = str_replace('__CAR_EDIT_DATA__', $carEditJson ?: 'null', $carPreviewScript);
        $carPreviewScript = str_replace('__SCRIPT_CSRF__', csrf_token(), $carPreviewScript);
        $html = str_replace('</body>', $carPreviewScript . '</body>', $html);
    }

    if ($noFlashPage) {
        $revealScript = <<<'HTML'
<script>
(() => {
  const reveal = () => {
    document.body.classList.add('account-dom-ready');
    const style = document.getElementById('account-noflash-style');
    if (style) style.remove();
  };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => requestAnimationFrame(reveal), { once: true });
  } else {
    requestAnimationFrame(reveal);
  }
})();
</script>
HTML;
        $html = str_replace('</body>', $revealScript . '</body>', $html);
    }

    return response($html, 200)
        ->header('Content-Type', 'text/html; charset=UTF-8')
        ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
        ->header('Pragma', 'no-cache')
        ->header('Expires', '0');
};

$requireListingAuth = static function (Request $request): ?\Illuminate\Http\RedirectResponse {
    if (Auth::check()) {
        return null;
    }

    $next = '/' . ltrim($request->path(), '/');
    $query = $request->getQueryString();
    if ($query) {
        $next .= '?' . $query;
    }

    return redirect('/signin?next=' . urlencode($next));
};
