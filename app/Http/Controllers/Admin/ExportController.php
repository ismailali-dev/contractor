<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\ListingReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ExportController extends Controller
{
    public function listings(Request $request)
    {
        $this->authorizeAdmin();

        $query = Listing::query()
            ->with(['category:id,name', 'city:id,name', 'user:id,name,email']);

        if ($request->filled('module')) {
            $query->where('module', $request->string('module')->toString());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('admin_status') && Schema::hasColumn('listings', 'admin_status')) {
            $query->where('admin_status', $request->string('admin_status')->toString());
        }

        $filename = 'listings-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'id',
                'module',
                'status',
                'admin_status',
                'title',
                'slug',
                'price',
                'rating',
                'reviews_count',
                'category',
                'city',
                'user_name',
                'user_email',
                'published_at',
                'created_at',
            ]);

            $query->orderByDesc('id')->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $listing) {
                    fputcsv($out, [
                        $listing->id,
                        $listing->module,
                        $listing->status,
                        Schema::hasColumn('listings', 'admin_status') ? ($listing->admin_status ?? '') : '',
                        $listing->title,
                        $listing->slug,
                        $listing->display_price,
                        $listing->rating,
                        $listing->reviews_count,
                        $listing->category?->name,
                        $listing->city?->name,
                        $listing->user?->name,
                        $listing->user?->email,
                        optional($listing->published_at)->toDateTimeString(),
                        optional($listing->created_at)->toDateTimeString(),
                    ]);
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function reports(Request $request)
    {
        $this->authorizeAdmin();

        abort_unless(Schema::hasTable('listing_reports'), 404);

        $query = ListingReport::query()
            ->with(['listing:id,title,slug,module', 'reporter:id,name,email', 'resolver:id,name,email']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('reason')) {
            $query->where('reason', $request->string('reason')->toString());
        }

        $filename = 'reports-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'id',
                'listing_id',
                'listing_slug',
                'listing_module',
                'reason',
                'status',
                'message',
                'admin_note',
                'reporter_user',
                'reporter_email',
                'reporter_ip',
                'resolved_by',
                'resolved_at',
                'created_at',
            ]);

            $query->orderByDesc('id')->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $report) {
                    fputcsv($out, [
                        $report->id,
                        $report->listing_id,
                        $report->listing?->slug,
                        $report->listing?->module,
                        $report->reason,
                        $report->status,
                        $report->message,
                        $report->admin_note,
                        $report->reporter?->name,
                        $report->reporter_email ?: $report->reporter?->email,
                        $report->reporter_ip,
                        $report->resolver?->email,
                        optional($report->resolved_at)->toDateTimeString(),
                        optional($report->created_at)->toDateTimeString(),
                    ]);
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function authorizeAdmin(): void
    {
        $user = auth()->user();
        abort_unless($user && method_exists($user, 'hasRole') && $user->hasRole('admin'), 403);
    }
}

