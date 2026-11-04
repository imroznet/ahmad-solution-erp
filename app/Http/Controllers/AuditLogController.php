<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $event = trim((string) $request->query('event', ''));

        $logs = AuditLog::query()
            ->with('user')
            ->when($event !== '', fn ($query) => $query->where('event', $event))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('audit.index', [
            'logs' => $logs,
            'event' => $event,
            'events' => AuditLog::query()->select('event')->distinct()->orderBy('event')->pluck('event'),
        ]);
    }
}
