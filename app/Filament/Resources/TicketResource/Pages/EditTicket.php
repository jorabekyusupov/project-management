<?php

namespace App\Filament\Resources\TicketResource\Pages;

use App\Filament\Resources\TicketResource;
use App\Library\Bot\InfoBot;
use App\Models\Project;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditTicket extends EditRecord
{
    protected static string $resource = TicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Handle assignees validation before saving
        if (!empty($data['assignees']) && !empty($data['project_id'])) {
            $project = Project::find($data['project_id']);
            
            if ($project) {
                $validAssignees = [];
                $invalidAssignees = [];
                
                foreach ($data['assignees'] as $userId) {
                    $isMember = $project->members()->where('users.id', $userId)->exists();
                    
                    if ($isMember) {
                        $validAssignees[] = $userId;
                    } else {
                        $invalidAssignees[] = $userId;
                    }
                }
                
                // Update data with only valid assignees
                $data['assignees'] = $validAssignees;
                
                // Show warning if some users were invalid
                if (!empty($invalidAssignees)) {
                    Notification::make()
                        ->warning()
                        ->title('Some assignees removed')
                        ->body('Some selected users are not members of this project and have been removed from assignees.')
                        ->send();
                }
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        // Sync assignees after saving (since it's a many-to-many relationship)
        if (isset($this->data['assignees']) && is_array($this->data['assignees'])) {
            $this->record->assignees()->sync($this->data['assignees']);
        }
        $ticket = $this->getRecord()
            ->refresh()
            ->load(['project', 'priority', 'creator', 'assignees', 'status']);
        $assignees = $ticket->assignees->pluck('name')->implode(', ');
        $assigneesChatIDs = $ticket->assignees->pluck('chat_id')->filter()->all();
        $text = '🔧 Задача обновлена!' . PHP_EOL .
            '🆔 Проект: ' . $ticket->project->name . PHP_EOL .
            '👨‍💼 Создатель: ' . $ticket->creator->name . PHP_EOL .
            '❕ Статус: ' . $ticket->status->name . PHP_EOL .
            '🔖 Этап: ' . ($ticket->epic ? $ticket->epic->name : 'Не указан') . PHP_EOL .
            '⏰ Срок: ' . ($ticket->due_date ? $ticket->due_date->format('d.m.Y') : 'Не указан') . PHP_EOL .
            '‼️ Приоритет: ' . ($ticket->priority ? $ticket->priority->name : 'Не указан') . PHP_EOL .
            '👥 Исполнители: ' . ($assignees ?: 'Не назначены') . PHP_EOL;
        app(InfoBot::class)
            ->send($ticket->project->chat_id,
                $text
            );

        if (!empty($assigneesChatIDs)) {
            foreach ($assigneesChatIDs as $assigneesChatID) {
                app(InfoBot::class)
                    ->send($assigneesChatID,
                        '🆕 Вам назначена новая задача: ' . $ticket->name . PHP_EOL .
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

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Ticket updated')
            ->body('The ticket has been updated successfully.');
    }


}