<?php

namespace App\Http\Controllers\Dashboard;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use App\Models\Setup\Branch;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\CourtManagement\Court;
use App\Models\CourtManagement\CourtSlot;
use App\Models\BookingManagement\CourtBooking;
use App\Models\BookingManagement\CourtBookingSummary;
use App\Models\User;

class AdminDashboardController extends Controller
{

    public function user_context()
    {
        $user = Auth::user();
        $vendor = null;
        $branch = null;

        if ($user->hasRole('Club Branch Manager')) {
            $branch = Branch::where('user_id', $user->id)->first();
            $vendor = User::where('id', $branch->vendor_id)->first();
        } else if ($user->hasRole('Club Owner')) {
            $vendor = User::where('id', $user->id)->first();
        }
        return response()->json([
            'branch' => $branch,
            'vendor' => $vendor,
        ]);
    }
    public function admin_dashboard(Request $request)
    {

        $filterBy = request('filter_by');

        $data = CourtBookingSummary::myBookings()->with('slots')
            ->when(request('vendor_id'), fn($q) => $q->where('vendor_id', request('vendor_id')))
            ->when(request('branch_id'), fn($q) => $q->where('branch_id', request('branch_id')))
            ->get();

        $branch_wise = Branch::myBranches()
            ->with(['bookings' => function ($q) use ($filterBy) {
                if ($filterBy === 'today') {
                    $q->whereDate('booking_date', today());
                } elseif ($filterBy === 'yesterday') {
                    $q->whereDate('booking_date', today()->subDay());
                } elseif ($filterBy === 'this_week') {
                    $q->whereBetween('booking_date', [now()->startOfWeek(), now()->endOfWeek()]);
                } elseif ($filterBy === 'this_month') {
                    $q->whereMonth('booking_date', now()->month)
                        ->whereYear('booking_date', now()->year);
                } elseif ($filterBy === 'this_year') {
                    $q->whereYear('booking_date', now()->year);
                } elseif ($filterBy === 'custom' && request()->filled(['start_date', 'end_date'])) {
                    $q->whereBetween('booking_date', [
                        Carbon::parse(request('start_date'))->startOfDay(),
                        Carbon::parse(request('end_date'))->endOfDay(),
                    ]);
                }
            }])
            ->when(request('vendor_id'), fn($q) => $q->where('vendor_id', request('vendor_id')))
            ->when(request('branch_id'), fn($q) => $q->where('id', request('branch_id')))
            ->get();

        $branch_wise_summary = $branch_wise->map(function ($branch) {
            $filteredBookings = $branch->bookings->filter(fn($item) => $item->status === 3);

            $total_revenue = $filteredBookings->sum(fn($item) => $item->total_amount);
            $total_bookings = $filteredBookings->count();
            $total_visitors = $branch->bookings->unique('customer_id')->count();

            return [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'total_revenue' => $total_revenue,
                'total_bookings' => $total_bookings,
                'total_visitors' => $total_visitors,
            ];
        });

        $court_wise = Court::myCourts()
            ->with(['bookings' => function ($q) use ($filterBy) {
                if ($filterBy === 'today') {
                    $q->whereDate('booking_date', today());
                } elseif ($filterBy === 'yesterday') {
                    $q->whereDate('booking_date', today()->subDay());
                } elseif ($filterBy === 'this_week') {
                    $q->whereBetween('booking_date', [now()->startOfWeek(), now()->endOfWeek()]);
                } elseif ($filterBy === 'this_month') {
                    $q->whereMonth('booking_date', now()->month)
                        ->whereYear('booking_date', now()->year);
                } elseif ($filterBy === 'this_year') {
                    $q->whereYear('booking_date', now()->year);
                } elseif ($filterBy === 'custom' && request()->filled(['start_date', 'end_date'])) {
                    $q->whereBetween('booking_date', [
                        Carbon::parse(request('start_date'))->startOfDay(),
                        Carbon::parse(request('end_date'))->endOfDay(),
                    ]);
                }
            }])
            ->when(request('vendor_id'), fn($q) => $q->where('vendor_id', request('vendor_id')))
            ->when(request('branch_id'), fn($q) => $q->where('branch_id', request('branch_id')))
            ->get();

        $court_wise_summary = $court_wise->map(function ($court) {
            $filteredBookings = $court->bookings->filter(fn($item) => $item->status === 3);

            $total_revenue = $filteredBookings->sum(fn($item) => $item->total_amount);
            $total_bookings = $filteredBookings->count();
            $total_visitors = $court->bookings->unique('customer_id')->count();

            return [
                'court_id' => $court->id,
                'court_name' => $court->name,
                'total_revenue' => $total_revenue,
                'total_bookings' => $total_bookings,
                'total_visitors' => $total_visitors,
            ];
        });


        $ten_latest_bookings = CourtBookingSummary::with(['slots', 'vendor', 'branch', 'court'])->where('status',3)->latest()->limit(10)->myBookings()->get();


        $chart_labels = [];
        $revenue = 0;
        $bookings = 0;
        $visitors = 0;

        $filtered_courts = $data->when($filterBy, function ($c, $filter) {
            if ($filter === 'today') {
                return $c->filter(fn($item) => Carbon::parse($item->booking_date)->isToday());
            } elseif ($filter === 'yesterday') {
                return $c->filter(fn($item) => Carbon::parse($item->booking_date)->isYesterday());
            } elseif ($filter === 'this_week') {
                return $c->filter(
                    fn($item) =>
                    Carbon::parse($item->booking_date)->between(now()->startOfWeek(), now()->endOfWeek())
                );
            } elseif ($filter === 'this_month') {
                return $c->filter(
                    fn($item) =>
                    Carbon::parse($item->booking_date)->isSameMonth(now())
                );
            } elseif ($filter === 'this_year') {
                return $c->filter(
                    fn($item) =>
                    Carbon::parse($item->booking_date)->isSameYear(now())
                );
            } elseif ($filter === 'custom' && request()->filled(['start_date', 'end_date'])) {
                $start = Carbon::parse(request('start_date'))->startOfDay();
                $end   = Carbon::parse(request('end_date'))->endOfDay();

                return $c->filter(
                    fn($item) =>
                    Carbon::parse($item->booking_date)->between($start, $end)
                );
            }

            return $c;
        });


        $filtered_data = $data->when($filterBy, function ($c, $filter) use (&$chart_labels) {
            if (in_array($filter, ['today', 'yesterday', 'this_week'])) {
                $start = now()->subDays(6)->startOfDay();
                $end   = now()->endOfDay();

                $c = $c->filter(
                    fn($item) =>
                    Carbon::parse($item->booking_date)->between($start, $end)
                );

                $chart_labels = collect(range(6, 0))->map(
                    fn($i) =>
                    Carbon::today()->subDays($i)->format('d M')
                )->toArray();
            } elseif ($filter === 'this_month') {
                $c = $c->filter(
                    fn($item) =>
                    Carbon::parse($item->booking_date)->isSameMonth(now())
                );

                $chart_labels = collect(range(1, now()->day))
                    ->map(fn($d) => Carbon::createFromDate(now()->year, now()->month, $d)->format('d M'))
                    ->toArray();
            } elseif ($filter === 'this_year') {
                $c = $c->filter(
                    fn($item) =>
                    Carbon::parse($item->booking_date)->isSameYear(now())
                );

                $chart_labels = collect(range(1, now()->month))
                    ->map(fn($m) => Carbon::create()->month($m)->format('M'))
                    ->toArray();
            } elseif ($filter === 'custom' && request()->filled(['start_date', 'end_date'])) {
                $start = Carbon::parse(request('start_date'))->startOfDay();
                $end   = Carbon::parse(request('end_date'))->endOfDay();

                $c = $c->filter(
                    fn($item) =>
                    Carbon::parse($item->booking_date)->between($start, $end)
                );

                $chart_labels = collect(
                    $start->daysUntil($end->copy()->addDay())
                )->map(fn($date) => $date->format('d M'))
                    ->toArray();
            }

            return $c;
        });

        $totalBookings = $filtered_data->count();

        $payment_type_data = [
            'cash' => 0,
            'bank_transfer' => 0,
            'debit_card' => 0,
        ];

        if ($totalBookings > 0) {
            $payment_type_data['cash'] = round(
                ($filtered_data->where('payment_type', 1)->count() / $totalBookings) * 100,
                2
            );
            $payment_type_data['bank_transfer'] = round(
                ($filtered_data->where('payment_type', 2)->count() / $totalBookings) * 100,
                2
            );
            $payment_type_data['debit_card'] = round(
                ($filtered_data->where('payment_type', 3)->count() / $totalBookings) * 100,
                2
            );
        }


        $revenue_line_chart = collect($chart_labels)->map(function ($label) use ($filtered_data) {
            return $filtered_data->filter(function ($item) use ($label) {
                $date = Carbon::parse($item->booking_date);
                if (strlen($label) === 3) {
                    return $date->format('M') === $label;
                } else {
                    return $date->format('d M') === $label;
                }
            })->where('status', 3)->sum(fn($item) => $item->total_amount);
        })->toArray();

        $booking_line_chart = collect($chart_labels)->map(function ($label) use ($filtered_data) {
            return $filtered_data->filter(function ($item) use ($label) {
                $date = Carbon::parse($item->booking_date);
                if (strlen($label) === 3) {
                    return $date->format('M') === $label;
                } else {
                    return $date->format('d M') === $label;
                }
            })->where('status', 3)->count();
        })->toArray();

        $visitor_line_chart = collect($chart_labels)->map(function ($label) use ($filtered_data) {
            return $filtered_data->filter(function ($item) use ($label) {
                $date = Carbon::parse($item->booking_date);
                if (strlen($label) === 3) {
                    return $date->format('M') === $label;
                } else {
                    return $date->format('d M') === $label;
                }
            })->unique('customer_id')->count();
        })->toArray();


        $revenue_till_date = $data->where('status', 3)->sum(fn($item) => $item->total_amount);
        $booking_till_date = $data->where('status', 3)->count();
        $visitors_till_date = $data->unique('customer_id')->count();
        $total_courts = Court::myCourts()->when(request('vendor_id'), function ($q) {
            $q->where('vendor_id', request('vendor_id'));
        })
            ->when(request('branch_id'), function ($q) {
                $q->where('branch_id', request('branch_id'));
            })->active()->count();

        if ($filterBy == 'today') {
            $revenue = $revenue_line_chart[count($revenue_line_chart) - 1] ?? 0;
            $bookings = $booking_line_chart[count($booking_line_chart) - 1] ?? 0;
            $visitors = $visitor_line_chart[count($visitor_line_chart) - 1] ?? 0;
        } else if ($filterBy == 'yesterday') {
            $revenue = ($revenue_line_chart[count($revenue_line_chart) - 1] + $revenue_line_chart[count($revenue_line_chart) - 2]) ?? 0;
            $bookings = ($booking_line_chart[count($booking_line_chart) - 1] + $booking_line_chart[count($booking_line_chart) - 2]) ?? 0;
            $visitors = ($visitor_line_chart[count($visitor_line_chart) - 1] + $visitor_line_chart[count($visitor_line_chart) - 2]) ?? 0;
        } else {
            $revenue = $filtered_data->sum(fn($item) => $item->total_amount);
            $bookings = $filtered_data->count();
            $visitors = $filtered_data->unique('customer_id')->count();
        }

        // $court_revenue = $data
        //     ->groupBy('court_id')
        //     ->mapWithKeys(function ($group, $courtId) {
        //         $courtName = $group->first()->court->name ?? 'Unknown Court';
        //         return [$courtName => $group->sum(fn($item) => $item->revenue)];
        //     })
        //     ->sortDesc();

        $court_revenue = $filtered_courts
            ->groupBy(fn($item) => \Carbon\Carbon::parse($item->booking_date)->format('Y-m-d'))
            ->map(function ($groupByDate) {
                return $groupByDate
                    ->groupBy('court_id')
                    ->mapWithKeys(function ($group, $courtId) {
                        $courtName = $group->first()->court->name ?? 'Unknown Court';
                        return [$courtName => $group->sum('total_amount')];
                    });
            });

        $court_booked_slots = $filtered_courts->groupBy('court_id')->mapWithKeys(function ($group, $courtId) {
            $courtName = $group->first()->court->name ?? 'Unknown Court';
            return [$courtName => $group->sum(fn($item) => $item->slots->count())];
        })->sortDesc();

        $court_slot = $this->getAvailableSlots($request, $court_booked_slots);

        return response()->json([
            'revenue_till_date' => $revenue_till_date,
            'booking_till_date' => $booking_till_date,
            'visitors_till_date' => $visitors_till_date,
            'total_courts' => $total_courts,
            'chart_labels' => $chart_labels,
            'booking_line_chart' => $booking_line_chart,
            'visitor_line_chart' => $visitor_line_chart,
            'payment_type_distribution' => $payment_type_data,
            'revenue_line_chart' => $revenue_line_chart,
            'revenue' => $revenue,
            'bookings' => $bookings,
            'visitors' => $visitors,
            'court_revenue' => $court_revenue,
            'branch_wise_summary' => $branch_wise_summary,
            'court_wise_summary' => $court_wise_summary,
            'ten_latest_bookings' => $ten_latest_bookings,
            'court_summary' => $court_slot,
        ]);
    }

    function countSlots(string $startTime, string $endTime, int $duration): int
    {
        $start = Carbon::createFromFormat('H:i:s', $startTime);
        $end   = Carbon::createFromFormat('H:i:s', $endTime);
        return intdiv($start->diffInMinutes($end), $duration);
    }

    function getBookingsByCourtAndDay(int $courtId, string $day): int
    {
        $rows = CourtSlot::where('court_id', $courtId)->where('day', $day)->get();

        $totalSlots = 0;

        foreach ($rows as $row) {
            $totalSlots += $this->countSlots($row->start_time, $row->end_time, $row->duration);
        }

        return $totalSlots;
    }

    function getDatesByCourt(int $courtId, string $startDate, string $endDate): array
    {
        $period = CarbonPeriod::create($startDate, $endDate);

        $result = [];

        foreach ($period as $date) {
            $result[] = [
                'date' => $date->toDateString(),
                'day'  => $date->format('D'), // Monday, Tuesday, etc.
            ];
        }
        return $result;
    }

    function getAvailableSlots(Request $filter, $booked_slots): array
    {
        switch ($filter['filter_by']) {
            case 'today':
                $startDate = $endDate = Carbon::today();
                break;
            case 'yesterday':
                $startDate = Carbon::yesterday();
                $endDate = Carbon::today();
                break;
            case 'this_week':
                $startDate = Carbon::today()->subDays(6);
                $endDate   = Carbon::today();
                break;
            case 'this_month':
                $startDate = Carbon::now()->startOfMonth();
                $endDate   = Carbon::now()->endOfMonth();
                break;
            case 'this_year':
                $startDate = Carbon::now()->startOfYear();
                $endDate   = Carbon::now()->endOfYear();
                break;
            case 'custom':
                $startDate = Carbon::parse($filter['start_date'])->startOfDay();
                $endDate   = Carbon::parse($filter['end_date'])->endOfDay();
                break;
            default:
                null;
        }

        // Step 2: fetch all courts
        $courts = Court::myCourts()
            ->when(request('vendor_id'), fn($q) => $q->where('vendor_id', request('vendor_id')))
            ->when(request('branch_id'), fn($q) => $q->where('branch_id', request('branch_id')))
            ->get();

        $finalResult = [];
        $final = [];
        // Step 3: loop courts
        foreach ($courts as $court) {
            $dates = $this->getDatesByCourt($court->id, $startDate->toDateString(), $endDate->toDateString());

            $totalSlots = 0;

            // Step 4: loop dates
            foreach ($dates as $d) {
                $totalSlots += $this->getBookingsByCourtAndDay($court->id, $d['day']);
            }

            $finalResult[] = [
                'name' => $court->name,
                'available' => $totalSlots,
                'booked'    => $booked_slots[$court->name] ?? 0,
                'empty'     => ($totalSlots - ($booked_slots[$court->name] ?? 0)) < 0 ? 0 : ($totalSlots - ($booked_slots[$court->name] ?? 0)),
            ];

            $final = collect($finalResult)->mapWithKeys(fn($item) => [$item['name'] => $item])->toArray();
        }

        return $final;
    }
}
