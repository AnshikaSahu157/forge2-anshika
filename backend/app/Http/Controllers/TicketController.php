<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Ticket::query();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->has('assignee_id')) {
            $query->where('assignee_id', $request->assignee_id);
        }

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return response()->json(
            $query->with(['requester', 'assignee'])->latest()->paginate(15)
        );
    }

    public function store(StoreTicketRequest $request): JsonResponse
    {
        $data = array_merge(
            ['status' => 'open', 'priority' => 'medium'],
            $request->validated(),
            ['requester_id' => $request->user()->id]
        );

        $ticket = Ticket::create($data);

        ActivityLog::create([
            'organization_id' => $ticket->organization_id,
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'action' => 'created',
            'properties' => ['status' => $ticket->status, 'priority' => $ticket->priority],
        ]);

        return response()->json($ticket->load(['requester', 'assignee']), 201);
    }

    public function show(Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        return response()->json($ticket->load(['requester', 'assignee', 'comments.user']));
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('update', $ticket);

        $changes = $request->validated();
        $ticket->update($changes);

        $properties = array_merge(
            ['changes' => $changes],
            ['status' => $ticket->status, 'priority' => $ticket->priority]
        );

        ActivityLog::create([
            'organization_id' => $ticket->organization_id,
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'action' => 'updated',
            'properties' => $properties,
        ]);

        return response()->json($ticket->fresh()->load(['requester', 'assignee']));
    }

    public function destroy(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('delete', $ticket);

        // Log activity before deletion (FK constraint)
        ActivityLog::create([
            'organization_id' => $ticket->organization_id,
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'action' => 'deleted',
            'properties' => ['subject' => $ticket->subject],
        ]);

        $ticket->delete();

        return response()->json(null, 204);
    }

    public function claim(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('update', $ticket);

        if ($ticket->assignee_id) {
            return response()->json(['message' => 'Ticket already assigned'], 422);
        }

        $ticket->update(['assignee_id' => $request->user()->id]);

        ActivityLog::create([
            'organization_id' => $ticket->organization_id,
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'action' => 'claimed',
            'properties' => ['agent' => $request->user()->name],
        ]);

        return response()->json($ticket->fresh()->load(['requester', 'assignee']));
    }

    public function assign(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('update', $ticket);

        $validated = $request->validate([
            'assignee_id' => ['required', Rule::exists('users', 'id')->where('organization_id', $request->user()->organization_id)],
        ]);

        $oldAssignee = $ticket->assignee_id;
        $ticket->update(['assignee_id' => $validated['assignee_id']]);

        ActivityLog::create([
            'organization_id' => $ticket->organization_id,
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'action' => 'assigned',
            'properties' => ['from' => $oldAssignee, 'to' => $validated['assignee_id']],
        ]);

        // Notify the new assignee
        Notification::create([
            'organization_id' => $ticket->organization_id,
            'user_id' => $validated['assignee_id'],
            'type' => 'ticket_assigned',
            'data' => [
                'ticket_id' => $ticket->id,
                'ticket_subject' => $ticket->subject,
                'assigned_by' => $request->user()->name,
            ],
        ]);

        return response()->json($ticket->fresh()->load(['requester', 'assignee']));
    }
}
