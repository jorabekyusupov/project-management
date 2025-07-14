<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Models\Epic;
use App\Models\TicketStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class TicketsRelationManager extends RelationManager
{
    protected static string $relationship = 'tickets';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return $ownerRecord->tickets_count ?? $ownerRecord->tickets()->count();
    }

    public function form(Form $form): Form
    {
        $projectId = $this->getOwnerRecord()->id;

        $defaultStatus = TicketStatus::where('project_id', $projectId)->first();
        $defaultStatusId = $defaultStatus ? $defaultStatus->id : null;

        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->label('Ticket Name'),
                
                Forms\Components\Select::make('ticket_status_id')
                    ->label('Status')
                    ->options(function () use ($projectId) {
                        return TicketStatus::where('project_id', $projectId)
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->default($defaultStatusId)
                    ->required()
                    ->searchable(),
                
                Forms\Components\Select::make('epic_id')
                    ->label('Epic')
                    ->options(function () use ($projectId) {
                        return Epic::where('project_id', $projectId)
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->nullable(),
                
                // UPDATED: Multi-user assignment
                Forms\Components\Select::make('assignees')
                    ->label('Assignees')
                    ->multiple()
                    ->relationship(
                        name: 'assignees',
                        titleAttribute: 'name',
                        modifyQueryUsing: function ($query) {
                            $projectId = $this->getOwnerRecord()->id;
                            // Only show project members
                            return $query->whereHas('projects', function ($query) use ($projectId) {
                                $query->where('projects.id', $projectId);
                            });
                        }
                    )
                    ->searchable()
                    ->preload()
                    ->default(function ($record) {
                        if ($record && $record->exists) {
                            return $record->assignees->pluck('id')->toArray();
                        }
                        
                        // Auto-assign current user if they're a project member
                        $project = $this->getOwnerRecord();
                        $isCurrentUserMember = $project->members()->where('users.id', auth()->id())->exists();
                        
                        return $isCurrentUserMember ? [auth()->id()] : [];
                    })
                    ->helperText('Select multiple users to assign this ticket to. Only project members can be assigned.'),
                
                Forms\Components\DatePicker::make('due_date')
                    ->label('Due Date')
                    ->nullable(),
                
                Forms\Components\RichEditor::make('description')
                    ->columnSpanFull()
                    ->nullable(),

                // Show created by in edit mode
                Forms\Components\Select::make('created_by')
                    ->label('Created By')
                    ->relationship('creator', 'name')
                    ->disabled()
                    ->hiddenOn('create'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('uuid')
                    ->label('Ticket ID')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('status.name')
                    ->badge()
                    ->color(fn ($record) => match ($record->status?->name) {
                        'To Do' => 'warning',
                        'In Progress' => 'info',
                        'Review' => 'primary',
                        'Done' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),
                
                // UPDATED: Display multiple assignees
                Tables\Columns\TextColumn::make('assignees.name')
                    ->label('Assignees')
                    ->badge()
                    ->separator(',')
                    ->expandableLimitedList()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Created By')
                    ->sortable()
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('due_date')
                    ->date()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('ticket_status_id')
                    ->label('Status')
                    ->options(function () {
                        $projectId = $this->getOwnerRecord()->id;

                        return TicketStatus::where('project_id', $projectId)
                            ->pluck('name', 'id')
                            ->toArray();
                    }),
                
                // UPDATED: Filter by assignees
                Tables\Filters\SelectFilter::make('assignees')
                    ->label('Assignee')
                    ->relationship('assignees', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),
                
                // Filter by creator
                Tables\Filters\SelectFilter::make('created_by')
                    ->label('Created By')
                    ->relationship('creator', 'name')
                    ->searchable()
                    ->preload(),
                
                // Filter by epic
                Tables\Filters\SelectFilter::make('epic_id')
                    ->label('Epic')
                    ->options(function () {
                        $projectId = $this->getOwnerRecord()->id;
                        return Epic::where('project_id', $projectId)
                            ->pluck('name', 'id')
                            ->toArray();
                    }),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        // Set project_id and created_by
                        $data['project_id'] = $this->getOwnerRecord()->id;
                        $data['created_by'] = auth()->id();
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    
                    Tables\Actions\BulkAction::make('updateStatus')
                        ->label('Update Status')
                        ->icon('heroicon-o-arrow-path')
                        ->form([
                            Forms\Components\Select::make('ticket_status_id')
                                ->label('Status')
                                ->options(function (RelationManager $livewire) {
                                    $projectId = $livewire->getOwnerRecord()->id;

                                    return TicketStatus::where('project_id', $projectId)
                                        ->pluck('name', 'id')
                                        ->toArray();
                                })
                                ->required(),
                        ])
                        ->action(function (array $data, Collection $records) {
                            foreach ($records as $record) {
                                $record->update([
                                    'ticket_status_id' => $data['ticket_status_id'],
                                ]);
                            }
                            
                            Notification::make()
                                ->success()
                                ->title('Status updated')
                                ->body(count($records) . ' tickets have been updated.')
                                ->send();
                        }),
                    
                    // NEW: Bulk assign users
                    Tables\Actions\BulkAction::make('assignUsers')
                        ->label('Assign Users')
                        ->icon('heroicon-o-user-plus')
                        ->form([
                            Forms\Components\Select::make('assignees')
                                ->label('Assignees')
                                ->multiple()
                                ->options(function (RelationManager $livewire) {
                                    return $livewire->getOwnerRecord()
                                        ->members()
                                        ->pluck('name', 'users.id')
                                        ->toArray();
                                })
                                ->searchable()
                                ->preload()
                                ->required(),
                            
                            Forms\Components\Radio::make('assignment_mode')
                                ->label('Assignment Mode')
                                ->options([
                                    'replace' => 'Replace existing assignees',
                                    'add' => 'Add to existing assignees',
                                ])
                                ->default('add')
                                ->required(),
                        ])
                        ->action(function (array $data, Collection $records) {
                            foreach ($records as $record) {
                                if ($data['assignment_mode'] === 'replace') {
                                    $record->assignees()->sync($data['assignees']);
                                } else {
                                    $record->assignees()->syncWithoutDetaching($data['assignees']);
                                }
                            }
                            
                            Notification::make()
                                ->success()
                                ->title('Users assigned')
                                ->body(count($records) . ' tickets have been updated with new assignees.')
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}