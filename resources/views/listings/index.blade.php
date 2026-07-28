@extends('layouts.front', ['title' => 'Monaclick - Listings'])

@section('content')
  <div class="pb-4 mb-2 mb-sm-3">
    <h1 class="h2 mb-0">Browse Listings</h1>
    <p class="text-body-secondary mt-2 mb-0">Finder-style directory view with dynamic Monaclick data.</p>
  </div>

  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3 p-sm-4">
      <form class="row g-2 g-md-3" method="GET">
        <div class="col-lg-4">
          <input class="form-control form-control-lg" name="q" value="{{ request('q') }}" placeholder="Search title">
        </div>
        <div class="col-sm-6 col-lg-2">
          <select class="form-select form-select-lg" name="module">
            <option value="">All modules</option>
            <option value="contractors" @selected(($selectedModule ?? request('module'))==='contractors')>Contractors</option>
            <option value="real-estate" @selected(($selectedModule ?? request('module'))==='real-estate')>Real Estate</option>
            <option value="cars" @selected(($selectedModule ?? request('module'))==='cars')>Cars</option>
            <option value="events" @selected(($selectedModule ?? request('module'))==='events')>Events</option>
          </select>
        </div>
        <div class="col-sm-6 col-lg-3">
          <select class="form-select form-select-lg" name="category">
            <option value="">Any category</option>
            @foreach ($categories as $category)
              <option value="{{ $category->slug }}" @selected(request('category')===$category->slug)>{{ $category->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-sm-8 col-lg-2">
          <select class="form-select form-select-lg" name="city">
            <option value="">Any city</option>
            @foreach ($cities as $city)
              <option value="{{ $city->slug }}" @selected(request('city')===$city->slug)>{{ $city->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-sm-4 col-lg-1 d-grid">
          <button class="btn btn-primary btn-lg" type="submit">Go</button>
        </div>
      </form>
    </div>
  </div>

  <div class="d-flex align-items-center justify-content-between pb-2 mb-3">
    <div class="fs-sm text-body-secondary">Showing {{ $listings->count() }} of {{ $listings->total() }} results</div>
  </div>

  <div class="vstack gap-4">
    @forelse ($listings as $listing)
      <article class="card hover-effect-opacity overflow-hidden">
        <div class="row g-0">
              <div class="col-sm-4 position-relative bg-body-tertiary" style="min-height: 220px">
            <a href="{{ route('listings.show', $listing) }}" class="d-block position-absolute top-0 start-0 w-100 h-100 z-1">
              <img
                src="{{ $listing->image_url }}"
                class="position-absolute top-0 start-0 w-100 h-100 object-fit-cover"
                alt="{{ $listing->title }}"
                onerror="this.onerror=null;this.src='/finder/assets/img/placeholders/preview-square.svg';">
              <span class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(180deg, rgba(0,0,0,0) 0%, rgba(0,0,0,.16) 100%)"></span>
            </a>
          </div>
          <div class="col-sm-8 d-flex p-3 p-sm-4" style="min-height: 230px">
            <div class="w-100 position-relative pt-1 pt-sm-0">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="badge bg-faded-primary text-primary">{{ strtoupper($listing->module) }}</span>
                <div class="fs-sm text-warning">
                  <i class="fi-star-filled me-1"></i>{{ number_format($listing->rating, 1) }}
                  <span class="text-body-secondary">({{ $listing->reviews_count }})</span>
                </div>
              </div>

              <p class="fs-sm text-body-secondary mb-2">{{ $listing->city->name }} - {{ $listing->category->name }}</p>
              <h3 class="h5 mb-2">
                <a class="hover-effect-underline stretched-link" href="{{ route('listings.show', $listing) }}">{{ $listing->title }}</a>
              </h3>
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
                <p class="mb-3 text-body">{{ $excerpt }}</p>
              @endif

              <div class="d-flex align-items-center justify-content-between mt-auto">
                <span class="fs-4 fw-semibold text-dark-emphasis">{{ $listing->display_price }}</span>
                <span class="btn btn-outline-dark btn-sm">
                  View details
                  <i class="fi-chevron-right fs-xs ms-1"></i>
                </span>
              </div>
            </div>
          </div>
        </div>
      </article>
    @empty
      <div class="card border-0 bg-body-tertiary">
        <div class="card-body p-4 text-center text-body-secondary">
          No listings found for selected filters.
        </div>
      </div>
    @endforelse
  </div>

  <div class="pt-4 mt-2">
    {{ $listings->onEachSide(1)->links() }}
  </div>
@endsection
