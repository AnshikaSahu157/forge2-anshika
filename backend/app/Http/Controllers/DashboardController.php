<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        $table = (new Ticket())->getTable();

        $byStatus = DB::table($table)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $byPriority = DB::table($table)
            ->select('priority', DB::raw('count(*) as count'))
            ->groupBy('priority')
            ->pluck('count', 'priority')
            ->toArray();

        // Normalize all expected keys
        $statusKeys = ['open', 'in_progress', 'resolved', 'closed'];
        $priorityKeys = ['low', 'medium', 'high', 'urgent'];

        foreach ($statusKeys as $k) {
            $byStatus[$k] = $byStatus[$k] ?? 0;
        }
        foreach ($priorityKeys as $k) {
            $byPriority[$k] = $byPriority[$k] ?? 0;
        }

        // Tickets per day for last 7 days
        $sevenDaysAgo = now()->subDays(6)->startOfDay();
        $perDay = DB::table($table)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->where('created_at', '>=', $sevenDaysAgo)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('count', 'date')
            ->toArray();

        $perDayData = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = now()->subDays($i)->format('Y-m-d');
            $perDayData[] = [
                'date' => $d,
                'count' => $perDay[$d] ?? 0,
            ];
        }

        $total = array_sum($byStatus);

        return response()->json([
            'by_status' => $byStatus,
            'by_priority' => $byPriority,
            'per_day' => $perDayData,
            'total' => $total,
        ]);
    }
}
