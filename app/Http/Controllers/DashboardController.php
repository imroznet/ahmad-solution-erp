<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard.index', [
            'userCount' => User::query()->count(),
            'activeUserCount' => User::query()->where('is_active', true)->count(),
            'auditCount' => AuditLog::query()->count(),
            'recentActivity' => AuditLog::query()->with('user')->latest('id')->limit(8)->get(),
        ]);
    }
}
