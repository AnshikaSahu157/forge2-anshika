<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Models\ActivityLog;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;

class CommentController extends Controller
{
    public function index(Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        return response()->json($ticket->comments()->with('user')->latest()->get());
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

        return response()->json($comment->load('user'), 201);
    }
}
