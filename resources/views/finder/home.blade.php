<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1, viewport-fit=cover">
  <title>Monaclick | {{ $modules[$selectedModule] ?? 'Home' }}</title>
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

    <section class="container pt-5 mt-2">
      <div class="card border-0 overflow-hidden mb-5" style="background: linear-gradient(90deg, rgba(var(--fn-primary-rgb), .16) 0%, rgba(var(--fn-primary-rgb), .06) 100%);">
        <div class="card-body p-4 p-md-5">
          <div class="row align-items-center g-4">
            <div class="col-lg-7">
              <h1 class="display-5 mb-3">{{ $modules[$selectedModule] ?? 'Listings' }} Marketplace</h1>
              <p class="fs-lg mb-4">Finder-style home with dynamic listings, categories and smart routing.</p>
              <form action="{{ route('listings.module', ['module' => $selectedModule]) }}" method="GET" class="row g-2">
                <div class="col-sm-8">
                  <input type="text" class="form-control form-control-lg" name="q" placeholder="Search in {{ $modules[$selectedModule] ?? 'listings' }}">
                </div>
                <div class="col-sm-4 d-grid">
                  <button class="btn btn-primary btn-lg" type="submit">Search</button>
                </div>
              </form>
            </div>
            <div class="col-lg-5">
              <div class="vstack gap-2">
                @foreach($categories as $category)
                  <a href="{{ route('listings.module', ['module' => $selectedModule, 'category' => $category->slug]) }}" class="btn btn-outline-dark justify-content-between">
                    {{ $category->name }}
                    <i class="fi-chevron-right fs-xs"></i>
                  </a>
                @endforeach
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="d-flex align-items-center justify-content-between pb-2 mb-3">
        <h2 class="h3 mb-0">Featured {{ $modules[$selectedModule] ?? 'Listings' }}</h2>
        <a href="{{ route('listings.module', ['module' => $selectedModule]) }}" class="btn btn-outline-primary">View all</a>
      </div>

      <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-4 g-4 pb-5">
        @forelse($featured as $listing)
          @php
            $entryUrl = route('finder.entry', ['module' => $listing->module, 'slug' => $listing->slug]);
          @endphp
          <div class="col">
            <article class="card h-100 hover-effect-opacity border-0 shadow-sm">
              <div class="position-relative">
                <img src="{{ $listing->image_url }}" class="card-img-top" style="height: 220px; object-fit: cover;" alt="{{ $listing->title }}" onerror="this.onerror=null;this.src='/finder/assets/img/placeholders/preview-square.svg';">
                <span class="badge bg-light text-dark-emphasis position-absolute top-0 start-0 mt-3 ms-3">{{ strtoupper($listing->module) }}</span>
              </div>
              <div class="card-body">
                <h3 class="h6 mb-2">
                  <a class="hover-effect-underline stretched-link" href="{{ $entryUrl }}">{{ $listing->title }}</a>
                </h3>
                <p class="small text-body-secondary mb-2">{{ $listing->city->name }} - {{ $listing->category->name }}</p>
                <div class="d-flex align-items-center justify-content-between">
                  <span class="small text-warning"><i class="fi-star-filled me-1"></i>{{ number_format($listing->rating, 1) }} ({{ $listing->reviews_count }})</span>
                  <span class="fw-semibold">{{ $listing->display_price }}</span>
                </div>
              </div>
            </article>
          </div>
        @empty
          <div class="col-12">
            <div class="alert alert-info mb-0">No listings available for {{ $modules[$selectedModule] ?? 'this module' }} yet.</div>
          </div>
        @endforelse
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
