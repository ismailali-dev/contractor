@extends('layouts.front', ['title' => 'Monaclick - Home'])

@section('content')
  <div class="card border-0 shadow-sm overflow-hidden mb-5">
    <div class="card-body p-4 p-md-5">
      <h1 class="display-5 fw-bold mb-3">One marketplace for Contractors, Real Estate, Cars and Events</h1>
      <p class="fs-lg text-body-secondary mb-4">Finder-style homepage powered by live database listings.</p>
      <form action="{{ route('listings.index') }}" method="GET" class="row g-2">
        <div class="col-md-5"><input class="form-control form-control-lg" name="q" placeholder="Search listing title"></div>
        <div class="col-md-4">
          <select class="form-select form-select-lg" name="module">
            <option value="">All modules</option>
            @foreach ($modules as $module)
              <option value="{{ $module }}">{{ ucwords(str_replace('-', ' ', $module)) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3 d-grid"><button class="btn btn-primary btn-lg" type="submit">Search</button></div>
      </form>
    </div>
  </div>

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h3 mb-0">Featured listings</h2>
    <a class="btn btn-outline-primary" href="{{ route('listings.index') }}">View all</a>
  </div>

  <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-4 g-4">
    @foreach ($featured as $listing)
      <div class="col">
        <article class="card h-100 hover-effect-opacity border-0 shadow-sm">
          <img src="{{ $listing->image }}" class="card-img-top" alt="{{ $listing->title }}" style="height: 200px; object-fit: cover;" onerror="this.onerror=null;this.src='/finder/assets/img/placeholders/preview-square.svg';">
          <div class="card-body">
            <span class="badge bg-faded-primary text-primary mb-2">{{ strtoupper($listing->module) }}</span>
            <h3 class="h6 mb-1"><a class="hover-effect-underline" href="{{ route('listings.show', $listing) }}">{{ $listing->title }}</a></h3>
            <p class="small text-body-secondary mb-2">{{ $listing->city->name }} - {{ $listing->category->name }}</p>
            <div class="d-flex justify-content-between small">
              <span class="text-warning"><i class="fi-star-filled me-1"></i>{{ number_format($listing->rating, 1) }} ({{ $listing->reviews_count }})</span>
              <span class="fw-semibold text-dark-emphasis">{{ \$listing->display_price }}</span>
            </div>
          </div>
        </article>
      </div>
    @endforeach
  </div>
@endsection
