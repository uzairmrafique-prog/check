<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Setup\Customer;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AppDashboardController extends Controller
{

    

    public function app_dashboard(Request $request)
    {
        $filterBy = $request->filter_by;

        $customers = Customer::when($filterBy, function ($q) use ($filterBy, $request) {
            if ($filterBy === 'today') {
                $q->whereDate('created_at', today());
            } elseif ($filterBy === 'yesterday') {
                $q->whereDate('created_at', today()->subDay());
            } elseif ($filterBy === 'this_week') {
                $q->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
            } elseif ($filterBy === 'this_month') {
                $q->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year);
            } elseif ($filterBy === 'this_year') {
                $q->whereYear('created_at', now()->year);
            } elseif ($filterBy === 'custom' && $request->start_date && $request->end_date) {
                $q->whereBetween('created_at', [
                    Carbon::parse($request->start_date)->startOfDay(),
                    Carbon::parse($request->end_date)->endOfDay(),
                ]);
            }
        })->paginate(
            $request->per_page ?? 25,
            ['*'],
            'page',
            $request->page_no ?? 1
        );

        $customer_count = Customer::count();
        $active_user_count = Customer::where('is_active', 1)->count();

        return response()->json([
            'data' => $customers,
            'customer_count' => $customer_count,
            'active_user_count' => $active_user_count
        ]);
    }
}
