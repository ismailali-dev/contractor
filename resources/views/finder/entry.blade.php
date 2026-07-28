<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1, viewport-fit=cover">
  <title>Monaclick | {{ $listing->title }}</title>
  <script src="/finder/assets/js/theme-switcher.js"></script>
  <link rel="stylesheet" href="/finder/assets/icons/finder-icons.min.css">
  <link rel="stylesheet" href="/finder/assets/css/theme.min.css">
</head>
<body>
  <main class="page-wrapper">
    <header id="monaclick-shell-header" class="navbar navbar-expand-lg bg-body navbar-sticky sticky-top z-fixed px-0">
      <div class="container py-3">
        <span class="fs-4 fw-semibold">Monaclick</span>
      </div>
    </header>

    <section class="container py-5">
      <div class="row g-4">
        <div class="col-lg-7">
          <div class="ratio ratio-16x9 bg-body-tertiary rounded overflow-hidden">
            <img src="{{ $listing->image_url }}" class="w-100 h-100 object-fit-cover" alt="{{ $listing->title }}" onerror="this.onerror=null;this.src='/finder/assets/img/placeholders/preview-square.svg';">
          </div>

          @if ($listing->images->isNotEmpty())
            <div class="row row-cols-2 row-cols-md-4 g-2 mt-1">
              @foreach ($listing->images->take(4) as $image)
                <div class="col">
                  <div class="ratio ratio-1x1 bg-body-tertiary rounded overflow-hidden">
                    <img src="{{ $image->image_url }}" class="w-100 h-100 object-fit-cover" alt="{{ $listing->title }}" onerror="this.onerror=null;this.src='/finder/assets/img/placeholders/preview-square.svg';">
                  </div>
                </div>
              @endforeach
            </div>
          @endif
        </div>

        <div class="col-lg-5">
          <span class="badge bg-faded-primary text-primary mb-2">{{ strtoupper($listing->module) }}</span>
          <h1 class="h2 mb-2">{{ $listing->title }}</h1>
          <p class="text-body-secondary mb-3">{{ $listing->city->name }} - {{ $listing->category->name }}</p>

          <div class="d-flex align-items-center gap-3 mb-3">
            <div class="d-flex align-items-center gap-1">
              <i class="fi-star-filled text-warning"></i>
              <span>{{ number_format($listing->rating, 1) }}</span>
              <span class="text-body-secondary">({{ $listing->reviews_count }})</span>
            </div>
            <div class="fw-semibold fs-4">{{ $listing->display_price }}</div>
          </div>

          @php
            $excerpt = trim((string) ($listing->excerpt ?? ''));
            if ($excerpt !== '' && (str_starts_with($excerpt, '{') || str_starts_with($excerpt, '['))) {
              $decoded = json_decode($excerpt, true);
              if (is_array($decoded) && ($decoded['_mc_restaurant_v1'] ?? false)) {
                $cuisine = (string) ($listing->category?->name ?? '');
                $cityName = (string) ($listing->city?->name ?? '');
                $bits = array_values(array_filter([$cuisine !== '' ? "{$cuisine} restaurant" : 'Restaurant', $cityName !== '' ? "in {$cityName}" : '']));
                $excerpt = $bits ? (implode(' ', $bits) . '.') : '';
              } else {
                $excerpt = '';
              }
            }
          @endphp
          @if ($excerpt !== '')
            <p class="mb-4">{{ $excerpt }}</p>
          @endif

          @if (!empty($listing->features) && count($listing->features) > 0)
            <div class="card border-0 bg-body-tertiary mb-3">
              <div class="card-body">
                <h2 class="h6 mb-2">Features</h2>
                <div class="d-flex flex-wrap gap-2">
                  @foreach ($listing->features as $feature)
                    <span class="badge bg-body-secondary text-body">{{ ucwords(str_replace(['-', '_'], ' ', (string) $feature)) }}</span>
                  @endforeach
                </div>
              </div>
            </div>
          @endif

          @if ($listing->module === 'contractors' && $listing->contractorDetail)
            <div class="card border-0 bg-body-tertiary mb-3">
              <div class="card-body">
                <h2 class="h6 mb-2">Contractor Details</h2>
                <div class="small">Service area: {{ $listing->contractorDetail->service_area ?: 'N/A' }}</div>
                <div class="small">License: {{ $listing->contractorDetail->license_number ?: 'N/A' }}</div>
                <div class="small">Verified: {{ $listing->contractorDetail->is_verified ? 'Yes' : 'No' }}</div>
              </div>
            </div>
          @endif

          @if ($listing->module === 'real-estate' && $listing->propertyDetail)
            <div class="card border-0 bg-body-tertiary mb-3">
              <div class="card-body">
                <h2 class="h6 mb-2">Property Details</h2>
                <div class="small">Type: {{ $listing->propertyDetail->property_type ?: 'N/A' }}</div>
                <div class="small">Beds/Baths: {{ $listing->propertyDetail->bedrooms ?: 0 }} / {{ $listing->propertyDetail->bathrooms ?: 0 }}</div>
                <div class="small">Area: {{ $listing->propertyDetail->area_sqft ?: 0 }} sqft</div>
              </div>
            </div>
          @endif

          @if ($listing->module === 'cars' && $listing->carDetail)
            <div class="card border-0 bg-body-tertiary mb-3">
              <div class="card-body">
                <h2 class="h6 mb-2">Car Details</h2>
                <div class="small">Year: {{ $listing->carDetail->year ?: 'N/A' }}</div>
                <div class="small">Mileage: {{ $listing->carDetail->mileage ?: 'N/A' }}</div>
                <div class="small">Fuel: {{ ucfirst($listing->carDetail->fuel_type ?: 'n/a') }}</div>
              </div>
            </div>
          @endif

          @if ($listing->module === 'events' && $listing->eventDetail)
            <div class="card border-0 bg-body-tertiary mb-3">
              <div class="card-body">
                <h2 class="h6 mb-2">Event Details</h2>
                <div class="small">Starts: {{ optional($listing->eventDetail->starts_at)->format('M d, Y h:i A') ?: 'N/A' }}</div>
                <div class="small">Ends: {{ optional($listing->eventDetail->ends_at)->format('M d, Y h:i A') ?: 'N/A' }}</div>
                <div class="small">Venue: {{ $listing->eventDetail->venue ?: 'N/A' }}</div>
              </div>
            </div>
          @endif

          <a class="btn btn-outline-dark" href="{{ route('listings.module', ['module' => $listing->module]) }}">
            <i class="fi-chevron-left fs-xs me-1"></i>
            Back to listings
          </a>
        </div>
      </div>

      @if ($related->isNotEmpty())
        <div class="pt-5 mt-2">
          <h2 class="h4 mb-4">Related listings</h2>
          <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-4">
            @foreach ($related as $item)
              <div class="col">
                <article class="card h-100 border-0 shadow-sm hover-effect-opacity">
                  <img src="{{ $item->image_url }}" class="card-img-top" style="height: 180px; object-fit: cover;" alt="{{ $item->title }}" onerror="this.onerror=null;this.src='/finder/assets/img/placeholders/preview-square.svg';">
                  <div class="card-body">
                    <h3 class="h6 mb-1">
                      <a class="hover-effect-underline" href="{{ route('finder.entry', ['module' => $item->module, 'slug' => $item->slug]) }}">{{ $item->title }}</a>
                    </h3>
                    <p class="small text-body-secondary mb-2">{{ $item->city->name }}</p>
                    <div class="small text-warning">
                      <i class="fi-star-filled me-1"></i>{{ number_format($item->rating, 1) }} ({{ $item->reviews_count }})
                    </div>
                  </div>
                </article>
              </div>
            @endforeach
          </div>
        </div>
      @endif
    </section>
  </main>

  <footer id="monaclick-shell-footer" class="footer bg-body border-top">
    <div class="container py-4 small text-body-secondary">Monaclick</div>
  </footer>

  <script src="/finder/assets/js/monaclick-shell.js"></script>
  <script src="/finder/assets/js/theme.min.js"></script>
</body>
</html>
