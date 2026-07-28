<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1, viewport-fit=cover">
  <title>Monaclick | Listings</title>
  <script src="/finder/assets/js/theme-switcher.js"></script>
  <link rel="stylesheet" href="/finder/assets/icons/finder-icons.min.css">
  <link rel="stylesheet" href="/finder/assets/vendor/swiper/swiper-bundle.min.css">
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
      <div class="card card-body border-0 shadow-sm p-4 mb-4">
        <form class="row g-2">
          <div class="col-lg-4">
            <input type="text" class="form-control" name="q" value="{{ request('q') }}" placeholder="Search title">
          </div>
          <div class="col-lg-2">
            <select class="form-select" name="module">
              <option value="">All modules</option>
              <option value="contractors" @selected(($selectedModule ?? request('module')) === 'contractors')>Contractors</option>
              <option value="real-estate" @selected(($selectedModule ?? request('module')) === 'real-estate')>Real Estate</option>
              <option value="cars" @selected(($selectedModule ?? request('module')) === 'cars')>Cars</option>
              <option value="events" @selected(($selectedModule ?? request('module')) === 'events')>Events</option>
            </select>
          </div>
          <div class="col-lg-3">
            <select class="form-select" name="category">
              <option value="">Any category</option>
              @foreach ($categories as $category)
                <option value="{{ $category->slug }}" @selected(request('category') === $category->slug)>{{ $category->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-lg-2">
            <select class="form-select" name="city">
              <option value="">Any city</option>
              @foreach ($cities as $city)
                <option value="{{ $city->slug }}" @selected(request('city') === $city->slug)>{{ $city->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-lg-1 d-grid">
            <button class="btn btn-primary" type="submit">Go</button>
          </div>
        </form>
      </div>

      <div class="d-flex align-items-center gap-2 gap-sm-3 pb-3 mb-2">
        <div class="fs-sm text-nowrap">Showing {{ $listings->count() }} results</div>
      </div>

      <div class="vstack gap-4">
        @forelse ($listings as $listing)
          @php
            $entryUrl = route('finder.entry', ['module' => $listing->module, 'slug' => $listing->slug]);
          @endphp
          <article class="card hover-effect-opacity overflow-hidden">
            <div class="row g-0">
              <div class="col-sm-4 position-relative bg-body-tertiary" style="min-height: 220px">
                <a class="d-block w-100 h-100" href="{{ $entryUrl }}">
                  <img src="{{ $listing->image_url }}" class="position-absolute top-0 start-0 w-100 h-100 object-fit-cover" alt="{{ $listing->title }}" onerror="this.onerror=null;this.src='/finder/assets/img/placeholders/preview-square.svg';">
                </a>
              </div>
              <div class="col-sm-8 d-flex p-3 p-sm-4" style="min-height: 255px">
                <div class="row flex-lg-nowrap g-0 position-relative pt-1 pt-sm-0 w-100">
                  <div class="col-lg-8 pe-lg-4">
                    <h3 class="h6 mb-2">
                      <a class="hover-effect-underline stretched-link" href="{{ $entryUrl }}">{{ $listing->title }}</a>
                    </h3>
                    <div class="fs-sm mb-2 mb-lg-3">
                      <span class="fw-medium text-dark-emphasis">{{ strtoupper($listing->module) }}</span>
                      <i class="fi-bullet fs-base align-middle"></i>
                      <span class="fw-medium text-dark-emphasis">{{ $listing->category->name }}</span>
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
                      <p class="fs-sm mb-0">{{ $excerpt }}</p>
                    @endif
                  </div>
                  <hr class="vr flex-shrink-0 d-none d-lg-block m-0">
                  <div class="col-lg-4 d-flex flex-column pt-3 pt-lg-1 ps-lg-4">
                    <ul class="list-unstyled pb-2 pb-lg-4 mb-3">
                      <li class="d-flex align-items-center gap-1">
                        <i class="fi-star-filled text-warning"></i>
                        <span class="fs-sm text-secondary-emphasis">{{ number_format($listing->rating, 1) }}</span>
                        <span class="fs-xs text-body-secondary align-self-end">({{ $listing->reviews_count }})</span>
                      </li>
                      <li class="d-flex align-items-center gap-1 fs-sm">
                        <i class="fi-map-pin"></i>
                        {{ $listing->city->name }}
                      </li>
                    </ul>
                    <div class="fw-semibold mb-2">{{ $listing->display_price }}</div>
                    <a class="btn btn-outline-dark position-relative z-2 mt-auto" href="{{ $entryUrl }}">
                      View
                    </a>
                  </div>
                </div>
              </div>
            </div>
          </article>
        @empty
          <div class="alert alert-info mb-0">No listings found for selected filters.</div>
        @endforelse
      </div>

      <div class="pt-4">
        {{ $listings->links() }}
      </div>
    </section>
  </main>

  <footer id="monaclick-shell-footer" class="footer bg-body border-top">
    <div class="container py-4 small text-body-secondary">Monaclick</div>
  </footer>

  <script src="/finder/assets/vendor/swiper/swiper-bundle.min.js"></script>
  <script src="/finder/assets/js/monaclick-shell.js"></script>
  <script src="/finder/assets/js/theme.min.js"></script>
</body>
</html>
