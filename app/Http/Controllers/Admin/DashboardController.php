<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Logbook;
use App\Models\User;
use App\Models\Laporan;
use App\Models\Bimbingan;
use App\Models\DataKkl;
use App\Models\DataKkn;
use App\Models\Tutorial;
use App\Models\Question;
use App\Models\Panduan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {

        $activityFilter = $request->input('activity_filter', 'all');
        $statusFilter = $request->input('status_filter', 'all');
        $page = $request->input('page', 1);


        $totalLogbooks = Logbook::count();
        $activeUsers = User::where('role', User::ROLE_MAHASISWA)
            ->whereHas('logbook', function ($query) {
                $query->where('created_at', '>=', now()->subDays(30));
            })->count();
        $totalBimbingans = Bimbingan::count();
        $completionRate = $this->calculateCompletionRate();


        $lastWeekLogbooks = Logbook::where('created_at', '>=', now()->subWeek())->count();
        $newUsersThisWeek = User::where('role', User::ROLE_MAHASISWA)
            ->where('created_at', '>=', now()->subWeek())
            ->count();
        $todayBimbingans = Bimbingan::whereDate('created_at', Carbon::today())->count();


        $lastWeekCompletionRate = $this->calculateCompletionRate(now()->subWeek());
        $completionRateChange = $completionRate - $lastWeekCompletionRate;


        $recentActivities = $this->getRecentActivities($activityFilter, $statusFilter, $page);


        $recentSubmissions = $this->getRecentSubmissions();


        $latestTutorial = Tutorial::latest()->first();
        $latestFaqs = Question::latest()->select('question', 'answer')->take(3)->get();
        $latestPanduan = Panduan::latest()->first();

        return inertia('Admin/Dashboard', [
            'stats' => [
                [
                    'title' => 'Total Logbooks',
                    'value' => (string)$totalLogbooks,
                    'change' => "+{$lastWeekLogbooks} minggu ini",
                    'changeType' => 'increase'
                ],
                [
                    'title' => 'User Aktif',
                    'value' => (string)$activeUsers,
                    'change' => "+{$newUsersThisWeek} baru",
                    'changeType' => 'increase'
                ],
                [
                    'title' => 'Bimbingans',
                    'value' => (string)$totalBimbingans,
                    'change' => "+{$todayBimbingans} hari ini",
                    'changeType' => 'increase'
                ],
                [
                    'title' => 'Penyelesaian Laporan',
                    'value' => number_format($completionRate, 0) . '%',
                    'change' => sprintf('%+.1f%%', $completionRateChange),
                    'changeType' => $completionRateChange >= 0 ? 'increase' : 'decrease'
                ]
            ],
            'recentActivities' => $recentActivities,
            'recentSubmissions' => $recentSubmissions,
            'filters' => [
                'activity' => $activityFilter,
                'status' => $statusFilter,
            ],
            'latestTutorial' => $latestTutorial,
            'latestFaqs' => $latestFaqs,
            'latestPanduan' => $latestPanduan,
        ]);
    }

    private function calculateCompletionRate($date = null)
    {

        $kklQuery = DataKkl::query();
        $kknQuery = DataKkn::query();

        if ($date) {
            $kklQuery->where('created_at', '<=', $date);
            $kknQuery->where('created_at', '<=', $date);
        }


        $totalKkl = $kklQuery->count();
        $totalKkn = $kknQuery->count();
        $totalSubmissions = $totalKkl + $totalKkn;


        if ($totalSubmissions === 0) {
            return 0;
        }


        $completedKkl = (clone $kklQuery)->where('status', 'approved')->count();
        $completedKkn = (clone $kknQuery)->where('status', 'approved')->count();
        $totalCompleted = $completedKkl + $completedKkn;


        return ($totalCompleted / $totalSubmissions) * 100;
    }

    private function getRecentActivities($typeFilter = 'all', $statusFilter = 'all', $page = 1)
    {

        $kkl = DataKkl::select(
            'id',
            'user_id',
            'judul as description',
            'created_at',
            'updated_at',
            DB::raw("'KKL' as type"),
            DB::raw("CASE 
                WHEN status = 'approved' THEN 'Completed'
                WHEN status = 'rejected' THEN 'Rejected'
                ELSE 'Pending'
            END as status")
        );

        $kkn = DataKkn::select(
            'id',
            'user_id',
            'judul as description',
            'created_at',
            'updated_at',
            DB::raw("'KKN' as type"),
            DB::raw("CASE 
                WHEN status = 'approved' THEN 'Completed'
                WHEN status = 'rejected' THEN 'Rejected'
                ELSE 'Pending'
            END as status")
        );


        if ($statusFilter !== 'all') {
            $mappedStatus = match ($statusFilter) {
                'pending' => 'Pending',
                'approved' => 'Completed',
                'rejected' => 'Rejected',
                default => $statusFilter
            };

            $statusCondition = DB::raw("CASE 
                WHEN status = 'approved' THEN 'Completed'
                WHEN status = 'rejected' THEN 'Rejected'
                ELSE 'Pending'
            END");

            $kkl->where($statusCondition, $mappedStatus);
            $kkn->where($statusCondition, $mappedStatus);
        }


        if ($typeFilter === 'kkl') {
            $query = $kkl;
        } elseif ($typeFilter === 'kkn') {
            $query = $kkn;
        } else {

            $query = $kkl->union($kkn);
        }

        $totalCount = $query->count();


        if ($totalCount === 0) {
            return new \Illuminate\Pagination\LengthAwarePaginator(
                [],
                0,
                5,
                $page,
                ['path' => request()->url()]
            );
        }


        $activities = $query->orderBy('updated_at', 'desc')
            ->paginate(5, ['*'], 'page', $page)
            ->through(function ($activity) {
                $user = User::find($activity->user_id);
                return [
                    'title' => "Laporan {$activity->type} by {$user->name}",
                    'time' => Carbon::parse($activity->updated_at)->diffForHumans(),
                    'status' => $activity->status,
                    'type' => $activity->type,
                    'description' => $activity->description
                ];
            });

        $activities->total_count = min($totalCount, 25);
        return $activities;
    }

    private function getRecentSubmissions()
    {
        $type = request()->input('tab', 'logbook');
        $search = request()->input('search');
        $perPage = request()->input('per_page', 10);

        if ($type === 'logbook') {
            return $this->getLogbooks($search, $perPage);
        } else {
            return $this->getBimbingans($search, $perPage);
        }
    }

    private function getLogbooks($search, $perPage)
    {
        $query = Logbook::with('user')
            ->select('id', 'user_id', 'tanggal', 'catatan', 'keterangan', 'created_at', 'updated_at');

        if ($search) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        $logbooks = $query->orderBy('updated_at', 'desc')
            ->paginate($perPage);

        return $logbooks->through(function ($logbook) {
            return [
                'id' => $logbook->id,
                'user' => ['name' => $logbook->user->name],
                'tanggal' => $logbook->tanggal,
                'catatan' => $logbook->catatan,
                'keterangan' => $logbook->keterangan ?? 'Pending',
                'created_at' => $logbook->created_at,
                'updated_at' => $logbook->updated_at
            ];
        });
    }

    private function getBimbingans($search, $perPage)
    {
        $query = Bimbingan::with('mahasiswa')
            ->select('id', 'user_id', 'tanggal', 'status', 'created_at', 'updated_at');

        if ($search) {
            $query->whereHas('mahasiswa', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        $bimbingans = $query->orderBy('updated_at', 'desc')
            ->paginate($perPage);

        return $bimbingans->through(function ($bimbingan) {
            return [
                'id' => $bimbingan->id,
                'name' => $bimbingan->mahasiswa->name,
                'type' => 'Bimbingan',
                'description' => 'Scheduled for ' . $bimbingan->tanggal,
                'status' => ucfirst($bimbingan->status),
                'lastUpdated' => $bimbingan->updated_at->format('Y-m-d')
            ];
        });
    }
}
