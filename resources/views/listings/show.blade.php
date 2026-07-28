@extends('layouts.front', ['title' => 'Monaclick - ' . $listing->title])

@section('content')
  <article class="row g-4">
    <div class="col-lg-7">
      <div class="position-relative">
        @php
          $badges = [];
          if ($listing->module === 'cars' && $listing->carDetail) {
            $condition = strtolower(trim((string) ($listing->carDetail->condition ?? '')));
            if ($condition !== '') {
              $badges[] = [
                'label' => str_contains($condition, 'new') ? 'New' : 'Used',
                'class' => 'text-bg-warning',
              ];
            }
            if ((bool) ($listing->carDetail->is_verified ?? false)) {
              array_unshift($badges, [
                'label' => 'Verified',
                'class' => 'text-bg-success',
              ]);
            }
          }
          if ($listing->module === 'contractors' && (bool) ($listing->contractorDetail?->is_verified ?? false)) {
            $badges[] = [
              'label' => 'Verified',
              'class' => 'text-bg-success',
            ];
          }
        @endphp
        @if ($badges)
          <div class="d-flex flex-column gap-2 align-items-start position-absolute top-0 start-0 z-1 mt-3 ms-3">
            @foreach ($badges as $badge)
              <span class="badge {{ $badge['class'] }}">{{ $badge['label'] }}</span>
            @endforeach
          </div>
        @endif
        <img src="{{ $listing->image_url }}" class="img-fluid rounded-4 shadow-sm w-100" alt="{{ $listing->title }}" style="max-height: 480px; object-fit: cover;" onerror="this.onerror=null;this.src='/finder/assets/img/placeholders/preview-square.svg';">
      </div>
      @if ($listing->images->isNotEmpty())
        <div class="row g-2 mt-2">
          @foreach ($listing->images->take(4) as $galleryImage)
            <div class="col-3">
              <img src="{{ $galleryImage->image_url }}" class="img-fluid rounded-3" alt="{{ $listing->title }}" onerror="this.onerror=null;this.src='/finder/assets/img/placeholders/preview-square.svg';">
            </div>
          @endforeach
        </div>
      @endif
    </div>
    <div class="col-lg-5">
      <p class="text-muted mb-2">{{ ucwords(str_replace('-', ' ', $listing->module)) }} - {{ $listing->city->name }}</p>
      <h1 class="h2 mb-3">{{ $listing->title }}</h1>
      @php
        $excerpt = trim((string) ($listing->excerpt ?? ''));
        $showExcerpt = $excerpt !== '' && !str_starts_with($excerpt, '{') && !str_starts_with($excerpt, '[');
      @endphp
      @if ($showExcerpt)
        <p class="mb-3">{{ $excerpt }}</p>
      @endif
      <div class="d-flex justify-content-between py-2 border-top border-bottom mb-3">
        <span class="fw-semibold">Price</span>
        <span>{{ $listing->display_price }}</span>
      </div>
      <div class="d-flex justify-content-between mb-4">
        <span class="fw-semibold">Rating</span>
        <span class="text-warning">{{ number_format($listing->rating, 1) }} ({{ $listing->reviews_count }} reviews)</span>
      </div>

      @if (!empty($listing->features) && count($listing->features) > 0)
        <div class="border rounded-3 p-3 mb-3">
          <h2 class="h6">Features</h2>
          <div class="d-flex flex-wrap gap-2">
            @foreach ($listing->features as $feature)
              <span class="badge text-bg-light">{{ ucwords(str_replace(['-', '_'], ' ', (string) $feature)) }}</span>
            @endforeach
          </div>
        </div>
      @endif

      @if ($listing->module === 'contractors' && $listing->contractorDetail)
        <div class="border rounded-3 p-3 mb-3">
          <h2 class="h6">Contractor Details</h2>
          <p class="mb-1"><strong>Service Area:</strong> {{ $listing->contractorDetail->service_area ?: 'N/A' }}</p>
          <p class="mb-1"><strong>License:</strong> {{ $listing->contractorDetail->license_number ?: 'N/A' }}</p>
          <p class="mb-0"><strong>Verified:</strong> {{ $listing->contractorDetail->is_verified ? 'Yes' : 'No' }}</p>
        </div>
      @endif

      @if ($listing->module === 'real-estate' && $listing->propertyDetail)
        <div class="border rounded-3 p-3 mb-3">
          <h2 class="h6">Property Details</h2>
          <p class="mb-1"><strong>Type:</strong> {{ $listing->propertyDetail->property_type ?: 'N/A' }}</p>
          <p class="mb-1"><strong>Listing:</strong> {{ ucfirst($listing->propertyDetail->listing_type ?: 'n/a') }}</p>
          <p class="mb-1"><strong>Beds/Baths:</strong> {{ $listing->propertyDetail->bedrooms ?: 0 }} / {{ $listing->propertyDetail->bathrooms ?: 0 }}</p>
          <p class="mb-0"><strong>Area:</strong> {{ $listing->propertyDetail->area_sqft ?: 0 }} sqft</p>
        </div>
      @endif

      @if ($listing->module === 'cars' && $listing->carDetail)
        <div class="border rounded-3 p-3 mb-3">
          <h2 class="h6">Car Details</h2>
          <p class="mb-1"><strong>Year:</strong> {{ $listing->carDetail->year ?: 'N/A' }}</p>
          <p class="mb-1"><strong>Mileage:</strong> {{ $listing->carDetail->mileage ?: 'N/A' }}</p>
          <p class="mb-1"><strong>Fuel:</strong> {{ ucfirst($listing->carDetail->fuel_type ?: 'n/a') }}</p>
          <p class="mb-0"><strong>Transmission:</strong> {{ ucfirst($listing->carDetail->transmission ?: 'n/a') }}</p>
          <p class="mb-0"><strong>Verified:</strong> {{ $listing->carDetail->is_verified ? 'Yes' : 'No' }}</p>
        </div>
      @endif

      @if ($listing->module === 'events' && $listing->eventDetail)
        <div class="border rounded-3 p-3 mb-3">
          <h2 class="h6">Event Details</h2>
          <p class="mb-1"><strong>Starts:</strong> {{ optional($listing->eventDetail->starts_at)->format('M d, Y h:i A') ?: 'N/A' }}</p>
          <p class="mb-1"><strong>Ends:</strong> {{ optional($listing->eventDetail->ends_at)->format('M d, Y h:i A') ?: 'N/A' }}</p>
          <p class="mb-1"><strong>Venue:</strong> {{ $listing->eventDetail->venue ?: 'N/A' }}</p>
          <p class="mb-0"><strong>Capacity:</strong> {{ $listing->eventDetail->capacity ?: 'N/A' }}</p>
        </div>
      @endif

      <a href="{{ route('listings.index') }}" class="btn btn-outline-primary">Back to listings</a>
    </div>
  </article>
@endsection
