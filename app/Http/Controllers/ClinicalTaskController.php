<?php

namespace App\Http\Controllers;

use App\Models\ClinicalTask;
use App\Services\ClinicalTaskGenerator;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClinicalTaskController extends Controller
{
    public function __construct(protected ClinicalTaskGenerator $generator) {}

    public function index(Request $request)
    {
        $this->authorizeTasks();

        // Reconcile on load so the worklist reflects the record rather than the
        // last time someone happened to run the command.
        $this->generator->run();

        $filter = $request->input('filter', 'mine');

        $tasks = ClinicalTask::with(['patient', 'visit', 'assignedTo'])
            ->open()
            ->when($filter === 'mine', fn ($query) => $query->where('assigned_to', auth()->id()))
            ->when($filter === 'overdue', fn ($query) => $query->whereNotNull('due_at')->where('due_at', '<', now()))
            ->orderByRaw("CASE priority WHEN 'critical' THEN 1 WHEN 'urgent' THEN 2 ELSE 3 END")
            ->orderByRaw('due_at IS NULL')
            ->orderBy('due_at')
            ->paginate(25)
            ->withQueryString();

        $counts = [
            'mine' => ClinicalTask::open()->where('assigned_to', auth()->id())->count(),
            'all' => ClinicalTask::open()->count(),
            'overdue' => ClinicalTask::overdue()->count(),
        ];

        return view('tasks.index', compact('tasks', 'filter', 'counts'));
    }

    public function complete(Request $request, ClinicalTask $task)
    {
        $this->authorizeTasks();

        abort_unless($task->status === ClinicalTask::STATUS_OPEN, 422, 'That task is already closed.');

        $validated = $request->validate([
            'completion_note' => 'nullable|string',
        ]);

        $task->complete(auth()->id(), $validated['completion_note'] ?? null);

        return back()->with('success', 'Task closed.');
    }

    public function reassign(Request $request, ClinicalTask $task)
    {
        $this->authorizeTasks();

        $validated = $request->validate([
            'assigned_to' => ['required', Rule::exists('users', 'id')],
        ]);

        $task->update(['assigned_to' => $validated['assigned_to']]);

        return back()->with('success', 'Task reassigned.');
    }

    protected function authorizeTasks(): void
    {
        if (! auth()->user()->hasPermission('manage_clinical_tasks')) {
            abort(403, 'You are not permitted to work on clinical tasks.');
        }
    }
}
