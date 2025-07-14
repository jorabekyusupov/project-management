<?php

namespace App\Filament\Pages;

use App\Models\Project;
use App\Models\Ticket;
use Auth;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class TicketTimeline extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationLabel = 'Timeline';

    protected static ?string $title = 'Ticket Timeline';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.ticket-timeline';

    protected static ?string $navigationGroup = 'Project Management';

    public ?string $projectId = null;

    public Collection $projects;

    public ?Project $selectedProject = null;

    protected static ?string $slug = 'ticket-timeline/{project_id?}';

    public function mount($project_id = null): void
    {
        $user = Auth::user();

        if ($user->hasRole('super_admin')) {
            $this->projects = Project::all();
        } else {
            $this->projects = $user->projects;
        }

        if ($project_id && $this->projects->contains('id', $project_id)) {
            $this->projectId = $project_id;
            $this->selectedProject = Project::find($project_id);
        } elseif ($this->projects->isNotEmpty() && ! is_null($project_id)) {
            Notification::make()
                ->title('Project Not Found')
                ->danger()
                ->send();
            $this->redirect(static::getUrl());
        }
    }

    public function updatedProjectId($value): void
    {
        if ($value) {
            $this->selectProject($value);
        } else {
            $this->selectedProject = null;
            $this->redirect(static::getUrl());
        }
    }

    public function selectProject($projectId): void
    {
        $this->projectId = $projectId;
        $this->selectedProject = Project::find($projectId);

        if ($this->selectedProject) {
            $url = static::getUrl(['project_id' => $projectId]);
            $this->redirect($url);
        }
    }

    public function getTicketsProperty(): Collection
    {
        if (!$this->projectId || !$this->selectedProject) {
            return collect();
        }

        $query = Ticket::query()
            ->with(['status', 'project'])
            ->whereNotNull('due_date')
            ->orderBy('due_date');

        if ($this->projectId) {
            $query->where('project_id', $this->projectId);
        } else {
            $projectIds = $this->projects->pluck('id')->toArray();
            $query->whereIn('project_id', $projectIds);
        }

        return $query->get();
    }

    public function getMonthHeaders(): array
    {
        if (!$this->selectedProject) {
            return [];
        }

        $tickets = $this->tickets;

        if ($tickets->isEmpty()) {
            $months = [];
            $current = Carbon::now()->subMonths(3)->startOfMonth();
            for ($i = 0; $i < 6; $i++) {
                $months[] = $current->format('M Y');
                $current->addMonth();
            }

            return $months;
        }

        $earliestDate = null;
        $latestDate = null;

        foreach ($tickets as $ticket) {
            if ($ticket->due_date) {
                $createdAt = $ticket->created_at ?? Carbon::parse($ticket->due_date)->subDays(14);
                $dueDate = Carbon::parse($ticket->due_date);

                if ($earliestDate === null || $createdAt < $earliestDate) {
                    $earliestDate = $createdAt;
                }

                if ($latestDate === null || $dueDate > $latestDate) {
                    $latestDate = $dueDate;
                }
            }
        }

        if ($earliestDate === null || $latestDate === null) {
            return ['Jan 2025', 'Feb 2025', 'Mar 2025', 'Apr 2025'];
        }

        $earliestDate = $earliestDate->startOfMonth();
        $latestDate = $latestDate->endOfMonth();

        $months = [];
        $current = clone $earliestDate;

        while ($current <= $latestDate) {
            $months[] = $current->format('M Y');
            $current->addMonth();
        }

        return $months;
    }

    public function getTimelineData(): array
    {
        if (!$this->selectedProject) {
            return [
                'tasks' => [],
            ];
        }

        $tickets = $this->tickets;

        if ($tickets->isEmpty()) {
            return [
                'tasks' => [],
            ];
        }

        $monthHeaders = $this->getMonthHeaders();
        $monthRanges = $this->getMonthDateRanges($monthHeaders);

        $tasks = [];
        $now = Carbon::now();

        foreach ($tickets as $index => $ticket) {
            if (! $ticket->due_date) {
                continue;
            }

            $startDate = $ticket->created_at ? Carbon::parse($ticket->created_at) : Carbon::parse($ticket->due_date)->subDays(14);
            $endDate = Carbon::parse($ticket->due_date);

            $hue = ($index * 137) % 360;
            $color = "hsl({$hue}, 70%, 50%)";

            $remainingDays = $now->diffInDays($endDate, false);

            $barSpans = [];

            foreach ($monthRanges as $monthIndex => $monthRange) {
                $monthStart = $monthRange['start'];
                $monthEnd = $monthRange['end'];
                $daysInMonth = $monthStart->daysInMonth;

                if ($startDate <= $monthEnd && $endDate >= $monthStart) {
                    $startPosition = 0;
                    if ($startDate > $monthStart) {
                        $daysFromMonthStart = $monthStart->diffInDays($startDate);
                        $startPosition = ($daysFromMonthStart / $daysInMonth) * 100;
                    }

                    $endPosition = 100;
                    if ($endDate < $monthEnd) {
                        $daysFromMonthStart = $monthStart->diffInDays($endDate);
                        $endPosition = (($daysFromMonthStart + 1) / $daysInMonth) * 100;
                    }

                    $widthPercentage = $endPosition - $startPosition;

                    $barSpans[$monthIndex] = [
                        'start_position' => $startPosition,
                        'width_percentage' => $widthPercentage,
                    ];
                }
            }

            $status = strtolower($ticket->status->name ?? 'default');
            $statusLabel = ucfirst($status);
            $isOverdue = $endDate < $now && ! in_array($status, ['completed', 'done', 'closed', 'resolved']);

            $remainingDaysText = '';
            if ($remainingDays > 0) {
                $remainingDaysText = "{$remainingDays} days left";
            } elseif ($remainingDays === 0) {
                $remainingDaysText = 'Due today';
            } else {
                $remainingDaysText = abs($remainingDays).' days overdue';
            }

            $tasks[] = [
                'id' => $ticket->id,
                'title' => $ticket->name,
                'ticket_id' => $ticket->uuid,
                'color' => $color,
                'bar_spans' => $barSpans,
                'start_date' => $startDate->format('M j'),
                'end_date' => $endDate->format('M j'),
                'remaining_days' => $remainingDays,
                'remaining_days_text' => $remainingDaysText,
                'status' => $status,
                'status_label' => $statusLabel,
                'is_overdue' => $isOverdue,
            ];
        }

        usort($tasks, function ($a, $b) {
            if ($a['is_overdue'] && ! $b['is_overdue']) {
                return -1;
            }
            if (! $a['is_overdue'] && $b['is_overdue']) {
                return 1;
            }

            return $a['remaining_days'] <=> $b['remaining_days'];
        });

        return [
            'tasks' => $tasks,
        ];
    }

    private function getMonthDateRanges(array $monthHeaders): array
    {
        $ranges = [];

        foreach ($monthHeaders as $index => $monthHeader) {
            $date = Carbon::createFromFormat('M Y', $monthHeader);
            $ranges[$index] = [
                'start' => (clone $date)->startOfMonth(),
                'end' => (clone $date)->endOfMonth(),
            ];
        }

        return $ranges;
    }
}
