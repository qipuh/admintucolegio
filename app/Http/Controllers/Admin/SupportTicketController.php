<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SupportTicketController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['status', 'priority', 'school_id', 'q']);
        $status = $filters['status'] ?? 'pendientes';

        $tickets = SupportTicket::with('school:id,name', 'assignee:id,name')
            ->withCount('comments')
            ->when($status === 'pendientes', fn ($q) => $q->open())
            ->when($status !== 'pendientes' && $status !== 'todos', fn ($q) => $q->where('status', $status))
            ->when($filters['priority'] ?? null, fn ($q, $v) => $q->where('priority', $v))
            ->when($filters['school_id'] ?? null, fn ($q, $v) => $q->where('school_id', $v))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('title', 'like', "%{$v}%")
                ->orWhere('description', 'like', "%{$v}%")
                ->orWhere('reporter_name', 'like', "%{$v}%")))
            ->orderByRaw("FIELD(status, 'abierto', 'en_progreso', 'esperando', 'resuelto', 'cerrado')")
            ->orderByRaw("FIELD(priority, 'urgente', 'alta', 'media', 'baja')")
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Support/Index', [
            'tickets' => $tickets,
            'filters' => ['status' => $status] + $filters,
            'schools' => School::orderBy('name')->get(['id', 'name']),
            'counts' => SupportTicket::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Support/Form', [
            'schools' => School::orderBy('name')->get(['id', 'name']),
            'users' => User::orderBy('name')->get(['id', 'name']),
            'defaults' => ['school_id' => $request->integer('school_id') ?: null],
            'options' => $this->options(),
        ]);
    }

    public function store(Request $request)
    {
        $ticket = SupportTicket::create($this->validated($request) + [
            'created_by' => $request->user()->id,
            'status' => 'abierto',
        ]);

        return redirect()->route('admin.support.show', $ticket)->with('success', 'Ticket creado.');
    }

    public function show(SupportTicket $ticket)
    {
        $ticket->load('school', 'assignee:id,name', 'creator:id,name', 'comments.user:id,name');

        return Inertia::render('Support/Show', [
            'ticket' => $ticket,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'options' => $this->options(),
        ]);
    }

    public function update(Request $request, SupportTicket $ticket)
    {
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(SupportTicket::STATUSES)],
            'priority' => ['sometimes', Rule::in(SupportTicket::PRIORITIES)],
            'assigned_to' => ['sometimes', 'nullable', 'exists:users,id'],
        ]);

        if (isset($data['status']) && $data['status'] !== $ticket->status) {
            $data['resolved_at'] = in_array($data['status'], ['resuelto', 'cerrado'], true) ? now() : null;

            $ticket->comments()->create([
                'user_id' => $request->user()->id,
                'body' => 'Cambió el estado a «'.str_replace('_', ' ', $data['status']).'».',
            ]);
        }

        $ticket->update($data);

        return back()->with('success', 'Ticket actualizado.');
    }

    public function comment(Request $request, SupportTicket $ticket)
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $ticket->comments()->create($data + ['user_id' => $request->user()->id]);
        $ticket->touch();

        return back();
    }

    public function destroy(SupportTicket $ticket)
    {
        $ticket->delete();

        return redirect()->route('admin.support.index')->with('success', 'Ticket eliminado.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'school_id' => ['nullable', 'exists:schools,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'category' => ['required', Rule::in(SupportTicket::CATEGORIES)],
            'priority' => ['required', Rule::in(SupportTicket::PRIORITIES)],
            'reporter_name' => ['nullable', 'string', 'max:255'],
            'reporter_contact' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function options(): array
    {
        return [
            'categories' => SupportTicket::CATEGORIES,
            'priorities' => SupportTicket::PRIORITIES,
            'statuses' => SupportTicket::STATUSES,
        ];
    }
}
