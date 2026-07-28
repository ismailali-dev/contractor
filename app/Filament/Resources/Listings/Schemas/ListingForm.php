<?php

namespace App\Filament\Resources\Listings\Schemas;

use App\Models\Listing;
use App\Models\ListingClaimRequest;
use App\Models\TaxonomyTerm;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ListingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable(),
                Select::make('module')
                    ->required()
                    ->options(Listing::MODULE_OPTIONS)
                    ->default('contractors')
                    ->live()
                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                        $set('category_id', null);
                        if (! in_array($state, ['contractors', 'restaurants'], true)) {
                            $set('features', []);
                        }
                    }),
                Select::make('category_id')
                    ->relationship(
                        name: 'category',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query, Get $get) => $query
                            ->when($get('module'), fn (Builder $builder, string $module) => $builder->where('module', $module))
                            ->orderBy('name')
                    )
                    ->required()
                    ->searchable(),
                Select::make('city_id')
                    ->relationship(
                        name: 'city',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query
                            ->where('is_active', true)
                            ->orderBy('sort_order')
                            ->orderBy('name')
                    )
                    ->required()
                    ->searchable(),
                TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                        if (filled($state) && blank($get('slug'))) {
                            $set('slug', Str::slug($state));
                        }
                    }),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(255),
                Textarea::make('excerpt')
                    ->columnSpanFull()
                    ->visible(fn (Get $get, ?Listing $record): bool => ($get('module') ?? $record?->module) !== 'restaurants'),
                TextInput::make('claim_email')
                    ->label('Email')
                    ->email()
                    ->maxLength(255),
                Placeholder::make('claim_status')
                    ->label('Claim Status')
                    ->content(function (?Listing $record): string {
                        if (! $record) {
                            return 'This listing has not been claimed yet.';
                        }

                        $latestClaim = ListingClaimRequest::query()
                            ->where('listing_id', $record->id)
                            ->whereNotNull('used_at')
                            ->latest('used_at')
                            ->first();

                        if (! $latestClaim) {
                            return 'This listing is currently unclaimed.';
                        }

                        $claimedBy = trim((string) ($latestClaim->email ?: $record->user?->email ?: 'Unknown email'));
                        $claimedAt = $latestClaim->used_at?->format('M d, Y h:i A') ?? 'Unknown time';

                        return "Claimed by {$claimedBy} on {$claimedAt}.";
                    }),
                TextInput::make('price')
                    ->prefix('$')
                    ->helperText('For real estate rent listings, /mo is auto added.')
                    ->maxLength(100)
                    ->required(fn (Get $get): bool => $get('status') === 'published'),
                Select::make('budget_tier')
                    ->label('Budget Tier')
                    ->options([
                        1 => '$',
                        2 => '$$',
                        3 => '$$$',
                        4 => '$$$$',
                    ])
                    ->default(2)
                    ->required(),
                Toggle::make('availability_now')
                    ->label('Available Now')
                    ->default(true),
                CheckboxList::make('features')
                    ->label('Listing Features')
                    ->options(fn (): array => TaxonomyTerm::optionsFor(TaxonomyTerm::TYPE_FEATURE, [
                        'eco-friendly' => 'Eco-friendly',
                        'free-consultation' => 'Free consultation',
                        'online-consultation' => 'Online consultation',
                        'free-estimate' => 'Free estimate',
                        'verified-hires' => 'Verified hires',
                        'weekend-consultations' => 'Weekend consultations',
                    ]))
                    ->visible(fn (Get $get): bool => in_array($get('module'), ['contractors', 'restaurants'], true))
                    ->columns(2),
                TextInput::make('rating')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(5)
                    ->default(0.0),
                TextInput::make('reviews_count')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
                FileUpload::make('image')
                    ->image()
                    ->disk('public')
                    ->directory('listings/cover')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(4096)
                    ->helperText('Use JPG/PNG/WEBP up to 4MB. Recommended 1600x900.')
                    ->required(fn (Get $get): bool => $get('status') === 'published'),
                FileUpload::make('gallery_images')
                    ->label('Gallery Images')
                    ->image()
                    ->multiple()
                    ->disk('public')
                    ->directory('listings/gallery')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(4096)
                    ->maxFiles(12)
                    ->helperText('Optional: up to 12 JPG/PNG/WEBP images, 4MB each.'),
                Select::make('status')
                    ->options(['draft' => 'Draft', 'published' => 'Published'])
                    ->default('published')
                    ->required(),
                Select::make('admin_status')
                    ->label('Review Status')
                    ->options([
                        'approved' => 'Approved',
                        'pending' => 'Pending',
                        'rejected' => 'Rejected',
                    ])
                    ->default('approved')
                    ->required(),
                Textarea::make('rejection_reason')
                    ->label('Rejection Reason')
                    ->columnSpanFull()
                    ->visible(fn (Get $get): bool => $get('admin_status') === 'rejected'),
                DateTimePicker::make('published_at'),

                Placeholder::make('contractor_heading')
                    ->content('Contractor Details')
                    ->visible(fn (Get $get): bool => $get('module') === 'contractors'),
                TextInput::make('contractor_service_area')
                    ->label('Service Area')
                    ->required(fn (Get $get): bool => $get('module') === 'contractors' && $get('status') === 'published')
                    ->visible(fn (Get $get): bool => $get('module') === 'contractors'),
                TextInput::make('contractor_license_number')
                    ->label('License Number')
                    ->visible(fn (Get $get): bool => $get('module') === 'contractors'),
                Toggle::make('contractor_is_verified')
                    ->label('Verified Contractor')
                    ->default(false)
                    ->visible(fn (Get $get): bool => $get('module') === 'contractors'),
                KeyValue::make('contractor_business_hours')
                    ->label('Business Hours')
                    ->keyLabel('Day')
                    ->valueLabel('Hours')
                    ->visible(fn (Get $get): bool => $get('module') === 'contractors'),

                Placeholder::make('property_heading')
                    ->content('Real Estate Details')
                    ->visible(fn (Get $get): bool => $get('module') === 'real-estate'),
                TextInput::make('property_type')
                    ->visible(fn (Get $get): bool => $get('module') === 'real-estate'),
                Select::make('property_listing_type')
                    ->label('Listing Type')
                    ->options([
                        'rent' => 'Rent',
                        'sale' => 'Sale',
                    ])
                    ->visible(fn (Get $get): bool => $get('module') === 'real-estate'),
                TextInput::make('property_bedrooms')
                    ->label('Bedrooms')
                    ->numeric()
                    ->required(fn (Get $get): bool => $get('module') === 'real-estate' && $get('status') === 'published')
                    ->visible(fn (Get $get): bool => $get('module') === 'real-estate'),
                TextInput::make('property_bathrooms')
                    ->label('Bathrooms')
                    ->numeric()
                    ->visible(fn (Get $get): bool => $get('module') === 'real-estate'),
                TextInput::make('property_area_sqft')
                    ->label('Area (sqft)')
                    ->numeric()
                    ->required(fn (Get $get): bool => $get('module') === 'real-estate' && $get('status') === 'published')
                    ->visible(fn (Get $get): bool => $get('module') === 'real-estate'),

                Placeholder::make('car_heading')
                    ->content('Car Details')
                    ->visible(fn (Get $get): bool => $get('module') === 'cars'),
                TextInput::make('car_year')
                    ->label('Year')
                    ->numeric()
                    ->minValue(1900)
                    ->maxValue((int) date('Y') + 1)
                    ->required(fn (Get $get): bool => $get('module') === 'cars' && $get('status') === 'published')
                    ->visible(fn (Get $get): bool => $get('module') === 'cars'),
                TextInput::make('car_mileage')
                    ->label('Mileage')
                    ->numeric()
                    ->required(fn (Get $get): bool => $get('module') === 'cars' && $get('status') === 'published')
                    ->visible(fn (Get $get): bool => $get('module') === 'cars'),
                Select::make('car_fuel_type')
                    ->label('Fuel Type')
                    ->options([
                        'petrol' => 'Petrol',
                        'diesel' => 'Diesel',
                        'electric' => 'Electric',
                        'hybrid' => 'Hybrid',
                    ])
                    ->visible(fn (Get $get): bool => $get('module') === 'cars'),
                Select::make('car_transmission')
                    ->label('Transmission')
                    ->options([
                        'automatic' => 'Automatic',
                        'manual' => 'Manual',
                    ])
                    ->visible(fn (Get $get): bool => $get('module') === 'cars'),
                TextInput::make('car_body_type')
                    ->label('Body Type')
                    ->visible(fn (Get $get): bool => $get('module') === 'cars'),
                Toggle::make('car_is_verified')
                    ->label('Verified Badge')
                    ->default(false)
                    ->visible(fn (Get $get): bool => $get('module') === 'cars'),

                Placeholder::make('restaurant_heading')
                    ->content('Restaurant Details')
                    ->visible(fn (Get $get): bool => $get('module') === 'restaurants'),
                TextInput::make('restaurant_address')
                    ->label('Address')
                    ->visible(fn (Get $get): bool => $get('module') === 'restaurants'),
                TextInput::make('restaurant_zip_code')
                    ->label('ZIP Code')
                    ->visible(fn (Get $get): bool => $get('module') === 'restaurants'),
                TextInput::make('restaurant_state')
                    ->label('State Code')
                    ->maxLength(2)
                    ->visible(fn (Get $get): bool => $get('module') === 'restaurants'),
                TextInput::make('restaurant_seating_capacity')
                    ->label('Seating Capacity')
                    ->numeric()
                    ->visible(fn (Get $get): bool => $get('module') === 'restaurants'),
                CheckboxList::make('restaurant_services')
                    ->label('Services')
                    ->options([
                        'dine-in' => 'Dine-in',
                        'takeaway' => 'Takeaway',
                        'delivery' => 'Delivery',
                        'reservations' => 'Reservations',
                        'outdoor-seating' => 'Outdoor seating',
                        'family-friendly' => 'Family friendly',
                    ])
                    ->columns(2)
                    ->visible(fn (Get $get): bool => $get('module') === 'restaurants'),
                Textarea::make('restaurant_opening_hours_json')
                    ->label('Opening Hours (JSON)')
                    ->rows(6)
                    ->helperText('Paste JSON object for opening_hours (keys: monday..sunday).')
                    ->visible(fn (Get $get): bool => $get('module') === 'restaurants'),
                TextInput::make('restaurant_contact_name')
                    ->label('Contact Name')
                    ->visible(fn (Get $get): bool => $get('module') === 'restaurants'),
                TextInput::make('restaurant_phone')
                    ->label('Phone')
                    ->visible(fn (Get $get): bool => $get('module') === 'restaurants'),
                TextInput::make('restaurant_email')
                    ->label('Email')
                    ->email()
                    ->visible(fn (Get $get): bool => $get('module') === 'restaurants'),

            ]);
    }
}
