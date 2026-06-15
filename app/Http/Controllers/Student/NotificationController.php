<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;

class NotificationController extends Controller
{
    /**
     * Mark a single in-app notification as read. Guarded so a student can only
     * touch notifications addressed to their own account.
     */
    public function markAsRead(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        abort_unless(
            $notification->notifiable_id === $request->user()->id
                && $notification->notifiable_type === $request->user()->getMorphClass(),
            403,
        );

        $notification->markAsRead();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Notification marked as read.')]);

        return back();
    }

    /**
     * Clear the student's unread notification badge in one shot.
     */
    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('All notifications marked as read.')]);

        return back();
    }
}
