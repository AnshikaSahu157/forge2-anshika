<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;

class CommentController extends Controller
{
    public function index(Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        $query = $ticket->comments()->with('user')->latest();

        // Customers can only see public comments
        if (! request()->user()->isAgent()) {
            $query->where('is_internal', false);
        }

        return response()->json($query->get());
    }

    public function store(StoreCommentRequest $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        $isInternal = $request->boolean('is_internal', false);

        // Only agents and admins can create internal comments
        if ($isInternal && ! $request->user()->isAgent()) {
            $isInternal = false;
        }

        $comment = $ticket->comments()->create([
            'organization_id' => $ticket->organization_id,
            'user_id' => $request->user()->id,
            'body' => $request->body,
            'is_internal' => $isInternal,
        ]);

        ActivityLog::create([
            'organization_id' => $ticket->organization_id,
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'action' => 'commented',
            'properties' => ['is_internal' => $comment->is_internal],
        ]);

        // Notify assignee if someone else commented
        if ($ticket->assignee_id && $ticket->assignee_id !== $request->user()->id) {
            Notification::create([
                'organization_id' => $ticket->organization_id,
                'user_id' => $ticket->assignee_id,
                'type' => 'comment_added',
                'data' => [
                    'ticket_id' => $ticket->id,
                    'ticket_subject' => $ticket->subject,
                    'comment_by' => $request->user()->name,
                ],
            ]);
        }

        // Notify requester if assignee or someone else commented
        if ($ticket->requester_id && $ticket->requester_id !== $request->user()->id && $ticket->requester_id !== $ticket->assignee_id) {
            Notification::create([
                'organization_id' => $ticket->organization_id,
                'user_id' => $ticket->requester_id,
                'type' => 'comment_added',
                'data' => [
                    'ticket_id' => $ticket->id,
                    'ticket_subject' => $ticket->subject,
                    'comment_by' => $request->user()->name,
                ],
            ]);
        }

        return response()->json($comment->load('user'), 201);
    }
}
