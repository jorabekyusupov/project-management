<?php

namespace App\Filament\Pages;

use App\Filament\Resources\TicketResource;
use App\Library\Bot\InfoBot;
use App\Models\Project;
use App\Models\Ticket;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use App\Filament\Actions\ExportTicketsAction;
use App\Exports\TicketsExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;

class ProjectBoard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-view-columns';

    protected static string $view = 'filament.pages.project-board';

    protected static ?string $title = 'Доска проектов';

    protected static ?string $navigationLabel = 'Доска проектов';

    protected static ?string $navigationGroup = 'Управление проектами';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'project-board/{project_id?}';

    public ?Project $selectedProject = null;

    public Collection $projects;

    public Collection $ticketStatuses;

    public ?Ticket $selectedTicket = null;

    public ?int $selectedProjectId = null;

    public function mount($project_id = null): void
    {
        if (auth()->user()->hasRole(['super_admin'])) {
            $this->projects = Project::all();
        } else {
            $this->projects = auth()->user()->projects;
        }

        if ($project_id && $this->projects->contains('id', $project_id)) {
            $this->selectedProjectId = (int)$project_id;
            $this->selectedProject = Project::find($project_id);
            $this->loadTicketStatuses();
        } elseif ($this->projects->isNotEmpty() && !is_null($project_id)) {
            Notification::make()
                ->title(__('Project Not Found'))
                ->danger()
                ->send();
            $this->redirect(static::getUrl());
        }
    }

    public function selectProject(int $projectId): void
    {
        $this->selectedTicket = null;
        $this->ticketStatuses = collect();
        $this->selectedProjectId = $projectId;
        $this->selectedProject = Project::find($projectId);

        if ($this->selectedProject) {
            $url = static::getUrl(['project_id' => $projectId]);
            $this->redirect($url);

            $this->loadTicketStatuses();
        }
    }

    public function updatedSelectedProjectId($value): void
    {
        if ($value) {
            $this->selectProject((int)$value);
        } else {
            $this->selectedProject = null;
            $this->ticketStatuses = collect();

            $this->redirect(static::getUrl());
        }
    }

    public function loadTicketStatuses(): void
    {
        if (!$this->selectedProject) {
            $this->ticketStatuses = collect();

            return;
        }

        $this->ticketStatuses = $this->selectedProject->ticketStatuses()
            ->with(['tickets' => function ($query) {
                $query->with(['assignees', 'status', 'priority'])
                    ->orderBy('created_at', 'desc');
            }])
            ->orderBy('sort_order')
            ->get();
    }

    #[On('ticket-moved')]
    public function moveTicket($ticketId, $newStatusId): void
    {
        $ticket = Ticket::find($ticketId)
            ->load(['project', 'priority', 'creator', 'assignees', 'status']);;

        if ($ticket && $ticket->project_id === $this->selectedProject?->id) {
            $ticket->update([
                'ticket_status_id' => $newStatusId,
            ]);

            $this->loadTicketStatuses();

            $this->dispatch('ticket-updated');

            Notification::make()
                ->title(__('Ticket Updated'))
                ->success()
                ->send();

            $assignees = $ticket->assignees->pluck('name')->implode(', ');
            $assigneesChatIDs = $ticket->assignees->pluck('chat_id')->filter()->all();
            if (!empty($ticket->project->chat_id)) {
                $text = '🔧 Cтатус задачи обновлен!' . PHP_EOL .
                    '🆔 Проект: ' . $ticket->project->name . PHP_EOL .
                    '👨‍💼 Создатель: ' . $ticket->creator->name . PHP_EOL .
                    '❕ Статус: ' . $ticket->status->name . PHP_EOL .
                    '🔖 Этап: ' . ($ticket->epic ? $ticket->epic->name : 'Не указан') . PHP_EOL .
                    '⏰ Срок: ' . ($ticket->due_date ? $ticket->due_date->format('d.m.Y') : 'Не указан') . PHP_EOL .
                    '‼️ Приоритет: ' . ($ticket->priority ? $ticket->priority->name : 'Не указан') . PHP_EOL .
                    '👥 Исполнители: ' . ($assignees ?: 'Не назначены') . PHP_EOL;
                app(InfoBot::class)
                    ->send($ticket->project->chat_id,
                        $text,
                        $ticket->project->thread_id
                    );
            }
            if (!empty($assigneesChatIDs)) {
                foreach ($assigneesChatIDs as $assigneesChatID) {
                    app(InfoBot::class)
                        ->send($assigneesChatID,
                            '🔧 Cтатус задачи обновлен: ' . $ticket->name . PHP_EOL .
                            '🆔 Проект: ' . $ticket->project->name . PHP_EOL .
                            '👨‍💼 Создатель: ' . $ticket->creator->name . PHP_EOL .
                            '❕ Статус: ' . $ticket->status->name . PHP_EOL .
                            '🔖 Этап: ' . ($ticket->epic ? $ticket->epic->name : 'Не указан') . PHP_EOL .
                            '⏰ Срок: ' . ($ticket->due_date ? $ticket->due_date->format('d.m.Y') : 'Не указан') . PHP_EOL .
                            '‼️ Приоритет: ' . ($ticket->priority ? $ticket->priority->name : 'Не указан') . PHP_EOL
                        );
                }
            }
        }
    }

    #[On('refresh-board')]
    public function refreshBoard(): void
    {
        $this->loadTicketStatuses();
        $this->dispatch('ticket-updated');
    }

    public function showTicketDetails(int $ticketId): void
    {
        $ticket = Ticket::with(['assignees', 'status', 'project', 'priority'])->find($ticketId);

        if (!$ticket) {
            Notification::make()
                ->title(__('Ticket Not Found'))
                ->danger()
                ->send();

            return;
        }


        $url = TicketResource::getUrl('view', ['record' => $ticketId]);
        $this->js("window.open('{$url}', '_blank')");
    }

    public function closeTicketDetails(): void
    {
        $this->selectedTicket = null;
    }

    public function editTicket(int $ticketId): void
    {
        $ticket = Ticket::find($ticketId);

        if (!$this->canEditTicket($ticket)) {
            Notification::make()
                ->title(__('Permission Denied'))
                ->body(__('You do not have permission to edit this ticket.'))
                ->danger()
                ->send();

            return;
        }

        $this->redirect(TicketResource::getUrl('edit', ['record' => $ticketId]));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('new_ticket')
                ->label('New Ticket')
                ->translateLabel()
                ->icon('heroicon-m-plus')
                ->visible(fn() => $this->selectedProject !== null && auth()->user()->hasRole(['super_admin']))
                ->url(fn(): string => TicketResource::getUrl('create', [
                    'project_id' => $this->selectedProject?->id,
                    'ticket_status_id' => $this->selectedProject?->ticketStatuses->first()?->id,
                ])),

            Action::make('refresh_board')
                ->label('Refresh Board')
                ->translateLabel()
                ->icon('heroicon-m-arrow-path')
                ->action('refreshBoard')
                ->color('warning'),

            ExportTicketsAction::make()
                ->visible(fn() => $this->selectedProject !== null),
        ];
    }

    private function canViewTicket(?Ticket $ticket): bool
    {
        if (!$ticket) {
            return false;
        }

        return auth()->user()->hasRole(['super_admin'])
            || $ticket->user_id === auth()->id()
            || $ticket->assignees()->where('users.id', auth()->id())->exists();
    }

    private function canEditTicket(?Ticket $ticket): bool
    {
        if (!$ticket) {
            return false;
        }

        return auth()->user()->hasRole(['super_admin'])
            || $ticket->user_id === auth()->id()
            || $ticket->assignees()->where('users.id', auth()->id())->exists();
    }

    private function canManageTicket(?Ticket $ticket): bool
    {
        if (!$ticket) {
            return false;
        }

        return auth()->user()->hasRole(['super_admin'])
            || $ticket->user_id === auth()->id()
            || $ticket->assignees()->where('users.id', auth()->id())->exists();
    }


    public function exportTickets(array $selectedColumns): void
    {
        if (empty($selectedColumns)) {
            Notification::make()
                ->title(__('Export Failed'))
                ->body(__('Please select at least one column to export.'))
                ->danger()
                ->send();
            return;
        }

        $tickets = collect();

        if ($this->selectedProject) {
            $tickets = $this->selectedProject->tickets()
                ->with(['assignees', 'status', 'project', 'epic'])
                ->orderBy('created_at', 'desc')
                ->get();
        } elseif ($this->ticketStatuses->isNotEmpty()) {
            $ticketIds = $this->ticketStatuses->flatMap(function ($status) {
                return $status->tickets->pluck('id');
            });

            $tickets = Ticket::whereIn('id', $ticketIds)
                ->with(['assignees', 'status', 'project', 'epic'])
                ->orderBy('created_at', 'asc')
                ->get();
        }

        if ($tickets->isEmpty()) {
            Notification::make()
                ->title(__('Export Failed'))
                ->body(__('No tickets found to export.'))
                ->warning()
                ->send();
            return;
        }

        try {
            $fileName = 'tickets_' . ($this->selectedProject?->name ?? 'export') . '_' . now()->format('Y-m-d_H-i-s') . '.xlsx';
            $fileName = \Illuminate\Support\Str::slug($fileName, '_') . '.xlsx';
            $export = new TicketsExport($tickets, $selectedColumns);
            Excel::store($export, 'exports/' . $fileName, 'public');
            $downloadUrl = asset('storage/exports/' . $fileName);
            $this->js("
                fetch('{$downloadUrl}')
                    .then(response => response.blob())
                    .then(blob => {
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.style.display = 'none';
                        a.href = url;
                        a.download = '{$fileName}';
                        document.body.appendChild(a);
                        a.click();
                        window.URL.revokeObjectURL(url);
                        document.body.removeChild(a);
                    });
            ");

            Notification::make()
                ->title(__('Export Successful'))
                ->body(__('Your Excel file is being downloaded.'))
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title(__('Export Failed'))
                ->body(__('An error occurred while exporting:') . $e->getMessage())
                ->danger()
                ->send();
        }
    }
}
