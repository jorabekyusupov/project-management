<?php

namespace App\Filament\Resources\TicketResource\Pages;

use App\Filament\Resources\TicketResource;
use App\Models\Project;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

class CreateTicket extends CreateRecord
{
    protected static string $resource = TicketResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set created_by to current user
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        // Create the ticket first
        $ticket = parent::handleRecordCreation($data);

        // Handle assignees validation and assignment
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
                
                // Assign only valid users
                if (!empty($validAssignees)) {
                    $ticket->assignees()->sync($validAssignees);
                }
                
                // Show warning if some users were invalid
                if (!empty($invalidAssignees)) {
                    Notification::make()
                        ->warning()
                        ->title('Some assignees removed')
                        ->body('Some selected users are not members of this project and have been removed from assignees.')
                        ->send();
                }
                
                // If no valid assignees, assign current user if they're a member
                if (empty($validAssignees)) {
                    $currentUserIsMember = $project->members()->where('users.id', auth()->id())->exists();
                    
                    if ($currentUserIsMember) {
                        $ticket->assignees()->sync([auth()->id()]);
                        
                        Notification::make()
                            ->info()
                            ->title('Auto-assigned')
                            ->body('No valid assignees found. You have been automatically assigned to this ticket.')
                            ->send();
                    }
                }
            }
        } else {
            // If no assignees provided, try to assign current user
            if (!empty($data['project_id'])) {
                $project = Project::find($data['project_id']);
                $currentUserIsMember = $project?->members()->where('users.id', auth()->id())->exists();
                
                if ($currentUserIsMember) {
                    $ticket->assignees()->sync([auth()->id()]);
                }
            }
        }

        return $ticket;
    }

    protected function getRedirectUrl(): string
    {
        $referer = request()->header('referer');

        if ($referer && str_contains($referer, 'project-board-page')) {
            return '/admin/project-board-page';
        }

        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Ticket created')
            ->body('The ticket has been created successfully.');
    }
}