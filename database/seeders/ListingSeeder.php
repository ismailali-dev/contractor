<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\City;
use App\Models\Listing;
use App\Models\ListingImage;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ListingSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $categories = collect([
            ['name' => 'Plumbing', 'slug' => 'plumbing', 'module' => 'contractors'],
            ['name' => 'Roofing', 'slug' => 'roofing', 'module' => 'contractors'],
            ['name' => 'Electrical', 'slug' => 'electrical', 'module' => 'contractors'],
            ['name' => 'Painting', 'slug' => 'painting', 'module' => 'contractors'],
            ['name' => 'Remodeling', 'slug' => 'remodeling', 'module' => 'contractors'],
            ['name' => 'Apartments', 'slug' => 'apartments', 'module' => 'real-estate'],
            ['name' => 'Family Homes', 'slug' => 'family-homes', 'module' => 'real-estate'],
            ['name' => 'Condos', 'slug' => 'condos', 'module' => 'real-estate'],
            ['name' => 'Townhouses', 'slug' => 'townhouses', 'module' => 'real-estate'],
            ['name' => 'Commercial Spaces', 'slug' => 'commercial-spaces', 'module' => 'real-estate'],
            ['name' => 'SUV', 'slug' => 'suv', 'module' => 'cars'],
            ['name' => 'Sedan', 'slug' => 'sedan', 'module' => 'cars'],
            ['name' => 'Coupe', 'slug' => 'coupe', 'module' => 'cars'],
            ['name' => 'Truck', 'slug' => 'truck', 'module' => 'cars'],
            ['name' => 'Electric', 'slug' => 'electric', 'module' => 'cars'],
            ['name' => 'Concerts', 'slug' => 'concerts', 'module' => 'events'],
            ['name' => 'Workshops', 'slug' => 'workshops', 'module' => 'events'],
            ['name' => 'Conferences', 'slug' => 'conferences', 'module' => 'events'],
            ['name' => 'Festivals', 'slug' => 'festivals', 'module' => 'events'],
            ['name' => 'Networking', 'slug' => 'networking', 'module' => 'events'],
            ['name' => 'BBQ', 'slug' => 'bbq', 'module' => 'restaurants'],
            ['name' => 'Sushi', 'slug' => 'sushi', 'module' => 'restaurants'],
            ['name' => 'Pizza', 'slug' => 'pizza', 'module' => 'restaurants'],
            ['name' => 'Steakhouse', 'slug' => 'steakhouse', 'module' => 'restaurants'],
            ['name' => 'Mediterranean', 'slug' => 'mediterranean', 'module' => 'restaurants'],
        ])->map(fn (array $item) => Category::updateOrCreate(
            [
                'module' => $item['module'],
                'slug' => $item['slug'],
            ],
            $item
        ));

        $hasStateCode = Schema::hasColumn('cities', 'state_code');
        $seedCities = collect([
            ['name' => 'Austin', 'state_code' => 'TX'],
            ['name' => 'Dallas', 'state_code' => 'TX'],
            ['name' => 'Houston', 'state_code' => 'TX'],
            ['name' => 'Chicago', 'state_code' => 'IL'],
            ['name' => 'Los Angeles', 'state_code' => 'CA'],
            ['name' => 'San Diego', 'state_code' => 'CA'],
            ['name' => 'Phoenix', 'state_code' => 'AZ'],
            ['name' => 'Seattle', 'state_code' => 'WA'],
        ])->values();

        $cities = $seedCities->map(function (array $city, int $index) use ($hasStateCode) {
            $name = (string) $city['name'];
            $stateCode = strtoupper(trim((string) ($city['state_code'] ?? '')));
            $slug = Str::slug($name);

            $identity = ['slug' => $slug];
            if ($hasStateCode && $stateCode !== '') {
                $identity['state_code'] = $stateCode;
            }

            $payload = [
                'name' => $name,
                'is_active' => true,
                'sort_order' => $index + 1,
            ];
            if ($hasStateCode) {
                $payload['state_code'] = $stateCode !== '' ? $stateCode : null;
            }

            return City::updateOrCreate($identity, $payload);
        });

        $images = [
            'contractors' => [
                'https://images.unsplash.com/photo-1581578731548-c64695cc6952?auto=format&fit=crop&w=1200&q=60',
                'https://images.unsplash.com/photo-1504307651254-35680f356dfd?auto=format&fit=crop&w=1200&q=60',
                'https://images.unsplash.com/photo-1621905251189-08b45d6a269e?auto=format&fit=crop&w=1200&q=60',
                'https://images.unsplash.com/photo-1505798577917-a65157d3320a?auto=format&fit=crop&w=1200&q=60',
                'https://images.unsplash.com/photo-1523419409543-6eeb2416d7a2?auto=format&fit=crop&w=1200&q=60',
            ],
            'real-estate' => [
                'https://images.unsplash.com/photo-1560518883-ce09059eeffa?auto=format&fit=crop&w=1200&q=60',
                'https://images.unsplash.com/photo-1494526585095-c41746248156?auto=format&fit=crop&w=1200&q=60',
                'https://images.unsplash.com/photo-1572120360610-d971b9d7767c?auto=format&fit=crop&w=1200&q=60',
                'https://images.unsplash.com/photo-1460317442991-0ec209397118?auto=format&fit=crop&w=1200&q=60',
                'https://images.unsplash.com/photo-1516455590571-18256e5bb9ff?auto=format&fit=crop&w=1200&q=60',
            ],
            'cars' => [
                'https://images.unsplash.com/photo-1494976388531-d1058494cdd8?auto=format&fit=crop&w=1200&q=60',
                'https://images.unsplash.com/photo-1549924231-f129b911e442?auto=format&fit=crop&w=1200&q=60',
                'https://images.unsplash.com/photo-1525609004556-c46c7d6cf023?auto=format&fit=crop&w=1200&q=60',
                'https://images.unsplash.com/photo-1555215695-3004980ad54e?auto=format&fit=crop&w=1200&q=60',
                'https://images.unsplash.com/photo-1503376780353-7e6692767b70?auto=format&fit=crop&w=1200&q=60',
            ],
            'events' => [
                'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?auto=format&fit=crop&w=1200&q=60',
                'https://images.unsplash.com/photo-1511578314322-379afb476865?auto=format&fit=crop&w=1200&q=60',
                'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?auto=format&fit=crop&w=1200&q=60',
                'https://images.unsplash.com/photo-1501281668745-f7f57925c3b4?auto=format&fit=crop&w=1200&q=60',
                'https://images.unsplash.com/photo-1515169067868-5387ec356754?auto=format&fit=crop&w=1200&q=60',
            ],
            'restaurants' => [
                'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=1200&q=60',
                'https://images.unsplash.com/photo-1552566626-52f8b828add9?auto=format&fit=crop&w=1200&q=60',
                'https://images.unsplash.com/photo-1559339352-11d035aa65de?auto=format&fit=crop&w=1200&q=60',
                'https://images.unsplash.com/photo-1555396273-367ea4eb4db5?auto=format&fit=crop&w=1200&q=60',
                'https://images.unsplash.com/photo-1551632436-cbf8dd35adfa?auto=format&fit=crop&w=1200&q=60',
            ],
        ];

        $titles = [
            'contractors' => [
                'PrimeFix Home Services', 'Elite Roofing and Repair', 'UrbanVolt Electric Crew', 'Northline Plumbing Pro',
                'BrightCoat Painting Team', 'Rapid Remodel Experts', 'Citywide Handy Masters', 'MetroCraft Builders',
                'Apex Leak Solutions', 'Skyline Roof Works', 'TrueNorth Home Repair', 'ClearPath Renovations',
            ],
            'real-estate' => [
                'Modern Family House', 'Downtown Smart Apartment', 'Lakeview Luxury Condo', 'Sunset Townhouse',
                'Central Office Loft', 'Garden Side Apartment', 'Riverside Family Villa', 'Urban Micro Condo',
                'Business Hub Retail Space', 'Maple Street Townhome', 'Premium City Penthouse', 'Executive Work Suite',
            ],
            'cars' => [
                'BMW X5 2022', 'Honda Accord 2021', 'Tesla Model 3 Performance', 'Ford F-150 XLT',
                'Mercedes C300 Coupe', 'Toyota RAV4 Hybrid', 'Audi A6 Premium', 'Nissan Altima SV',
                'Chevrolet Silverado LT', 'Hyundai Ioniq 5', 'Kia Sportage EX', 'Lexus ES 350',
            ],
            'events' => [
                'Weekend Music Festival', 'Startup Growth Workshop', 'Digital Marketing Summit', 'Sunset Jazz Night',
                'Tech Innovators Conference', 'City Food Carnival', 'Founders Networking Meetup', 'Rock Arena Live',
                'Creative Design Bootcamp', 'E-Commerce Masterclass', 'Startup Demo Day', 'Community Wellness Expo',
            ],
            'restaurants' => [
                'Saffron Grill House', 'Olive & Thyme Bistro', 'Tokyo Ember Sushi', 'Stone Oven Pizza Co.',
                'Maple Street Steakhouse', 'Blue Harbor Seafood', 'Garden Flame BBQ', 'Urban Noodle Bar',
                'Copper Table Kitchen', 'Luna Tapas Lounge', 'The Daily Brunch Co.', 'Spice Route Dining',
            ],
        ];

        $rows = collect();

        foreach (['contractors', 'real-estate', 'cars', 'events', 'restaurants'] as $module) {
            $moduleCategories = $categories->where('module', $module)->values();
            foreach ($titles[$module] as $index => $title) {
                $category = $moduleCategories[$index % $moduleCategories->count()];
                $city = $cities[$index % $cities->count()];
                $slug = Str::slug($title);
                $hash = abs(crc32($slug));

                if ($module === 'contractors') {
                    $amount = 80 + (($hash % 18) * 25);
                    $price = "From \${$amount}";
                } elseif ($module === 'real-estate') {
                    $isRent = in_array($category->slug, ['apartments', 'condos', 'townhouses'], true);
                    $price = $isRent
                        ? '$' . number_format(1200 + (($hash % 26) * 120)) . '/mo'
                        : '$' . number_format(220000 + (($hash % 35) * 9500));
                } elseif ($module === 'cars') {
                    $price = '$' . number_format(16000 + (($hash % 60) * 1400));
                } elseif ($module === 'restaurants') {
                    $price = '$' . number_format(18 + ($hash % 48)) . ' avg';
                } else {
                    $price = '$' . number_format(25 + ($hash % 120)) . ' Ticket';
                }

                $rows->push([
                    'module' => $module,
                    'category' => $category->slug,
                    'title' => $title,
                    'slug' => $slug,
                    'price' => $price,
                    'rating' => round(4.0 + (($hash % 11) / 10), 1),
                    'reviews' => 18 + ($hash % 280),
                    'city' => $city->slug,
                    'image' => $images[$module][$index % count($images[$module])],
                    'hash' => $hash,
                ]);
            }
        }

        foreach ($rows as $row) {
            $category = $categories->firstWhere('slug', $row['category']);
            $city = $cities->firstWhere('slug', $row['city']);

            preg_match('/\d[\d,]*/', (string) $row['price'], $matches);
            $amount = isset($matches[0]) ? (int) str_replace(',', '', $matches[0]) : 0;
            $budgetTier = match (true) {
                $amount <= 100 => 1,
                $amount <= 1000 => 2,
                $amount <= 5000 => 3,
                default => 4,
            };

            $features = [];
            if (in_array($row['module'], ['contractors', 'restaurants'], true)) {
                $features[] = 'eco-friendly';
                $features[] = 'verified-hires';
                if ($row['hash'] % 2 === 0) $features[] = 'free-consultation';
                if ($row['hash'] % 3 === 0) $features[] = 'weekend-consultations';
                if ($row['hash'] % 5 === 0) $features[] = 'online-consultation';
                if ($row['hash'] % 7 === 0) $features[] = 'free-estimate';
            }

            Listing::updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'category_id' => $category?->id,
                    'city_id' => $city?->id,
                    'module' => $row['module'],
                    'title' => $row['title'],
                    'excerpt' => 'Finder-style sample listing for Monaclick marketplace. Curated demo data for rapid product validation.',
                    'price' => $row['price'],
                    'budget_tier' => $budgetTier,
                    'availability_now' => $row['hash'] % 4 !== 0,
                    'features' => array_values(array_unique($features)),
                    'rating' => $row['rating'],
                    'reviews_count' => $row['reviews'],
                    'image' => $row['image'],
                    'status' => 'published',
                    'published_at' => Carbon::now()->subDays($row['hash'] % 45),
                ]
            );
        }

        Listing::query()->with(['contractorDetail', 'propertyDetail', 'carDetail', 'eventDetail'])->each(function (Listing $listing): void {
            $hash = abs(crc32($listing->slug));

            if ($listing->module === 'contractors') {
                $listing->contractorDetail()->updateOrCreate(
                    ['listing_id' => $listing->id],
                    [
                        'service_area' => $listing->city?->name . ' Metro',
                        'license_number' => 'LIC-' . strtoupper(substr(md5($listing->slug), 0, 6)),
                        'is_verified' => in_array('verified-hires', $listing->features ?? [], true),
                        'business_hours' => [
                            'Mon-Fri' => '09:00 AM - 06:00 PM',
                            'Sat' => '10:00 AM - 04:00 PM',
                        ],
                    ]
                );
            } else {
                $listing->contractorDetail()->delete();
            }

            if ($listing->module === 'real-estate') {
                $listing->propertyDetail()->updateOrCreate(
                    ['listing_id' => $listing->id],
                    [
                        'property_type' => in_array($listing->category?->slug, ['commercial-spaces'], true) ? 'Commercial' : 'Residential',
                        'bedrooms' => 1 + ($hash % 5),
                        'bathrooms' => 1 + ($hash % 3),
                        'area_sqft' => 700 + (($hash % 35) * 85),
                        'listing_type' => str_contains((string) $listing->price, '/mo') ? 'rent' : 'sale',
                    ]
                );
            } else {
                $listing->propertyDetail()->delete();
            }

            if ($listing->module === 'cars') {
                $fuelTypes = ['petrol', 'diesel', 'electric', 'hybrid'];
                $transmissions = ['automatic', 'manual'];
                $bodyTypes = ['SUV', 'Sedan', 'Coupe', 'Truck', 'Hatchback'];

                $listing->carDetail()->updateOrCreate(
                    ['listing_id' => $listing->id],
                    [
                        'year' => 2017 + ($hash % 9),
                        'mileage' => 12000 + (($hash % 16) * 4500),
                        'fuel_type' => $fuelTypes[$hash % count($fuelTypes)],
                        'transmission' => $transmissions[$hash % count($transmissions)],
                        'body_type' => $bodyTypes[$hash % count($bodyTypes)],
                    ]
                );
            } else {
                $listing->carDetail()->delete();
            }

            if ($listing->module === 'events') {
                $start = Carbon::now()->addDays(($hash % 120) + 5)->setTime(18 + ($hash % 4), 0);
                $listing->eventDetail()->updateOrCreate(
                    ['listing_id' => $listing->id],
                    [
                        'starts_at' => $start,
                        'ends_at' => (clone $start)->addHours(3 + ($hash % 3)),
                        'venue' => ($listing->city?->name ?? 'City') . ' Convention Center',
                        'capacity' => 120 + (($hash % 9) * 80),
                    ]
                );
            } else {
                $listing->eventDetail()->delete();
            }

            ListingImage::query()->updateOrCreate(
                [
                    'listing_id' => $listing->id,
                    'sort_order' => 1,
                ],
                [
                    'image_path' => $listing->image,
                    'is_cover' => true,
                ]
            );
        });
    }
}
