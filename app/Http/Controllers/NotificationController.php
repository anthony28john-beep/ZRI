<?php

namespace App\Http\Controllers;

use App\Exceptions\AccountException;
use App\Exceptions\ClassroomUnavailable;
use App\Services\BlockMemberships;
use App\Services\StudentNotifications;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __invoke(Request $request)
    {
        $role = $request->user()->role;
        $data = ['role' => $role, 'loading' => false];
        if ($role === 'teacher') {
            try {
                $notifications = app(BlockMemberships::class)->pendingNotificationsForTeacher($request->user()->uid);
            } catch (AccountException|ClassroomUnavailable) {
                $notifications = ['count' => null, 'items' => []];
            }
            $data += ['pendingApplicationCount' => $notifications['count'], 'applicationNotifications' => $notifications['items']];
        } else {
            try {
                $notifications = app(StudentNotifications::class)->forStudent($request->user()->uid);
            } catch (AccountException) {
                $notifications = ['count' => 0, 'items' => [], 'unavailable' => ['Notifications are temporarily unavailable.']];
            }
            $data['studentNotifications'] = $notifications;
        }

        return response()->json([
            'html' => view('partials.notification-content', $data)->render(),
            'count' => $notifications['count'], 'role' => $role,
        ])->header('Cache-Control', 'private, no-store');
    }
}
