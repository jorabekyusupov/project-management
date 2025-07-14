<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TicketResource\Pages;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\TicketPriority;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use App\Models\Epic;

class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationLabel = 'Tickets';

    protected static ?string $navigationGroup = 'Project Management';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (! auth()->user()->hasRole(['super_admin'])) {
            $query->where(function ($query) {
                $query->whereHas('assignees', function ($query) {
                        $query->where('users.id', auth()->id());
                    })
                    ->orWhere('created_by', auth()->id())
                    ->orWhereHas('project.members', function ($query) {
                        $query->where('users.id', auth()->id());
                    });
            });
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        $projectId = request()->query('project_id') ?? request()->input('project_id');
        $statusId = request()->query('ticket_status_id') ?? request()->input('ticket_status_id');

        return $form
            ->schema([
                Forms\Components\Select::make('project_id')
                    ->label('Project')
                    ->options(function () {
                        if (auth()->user()->hasRole(['super_admin'])) {
                            return Project::pluck('name', 'id')->toArray();
                        }

                        return auth()->user()->projects()->pluck('name', 'projects.id')->toArray();
                    })
                    ->default($projectId)
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (callable $set) {
                        $set('ticket_status_id', null);
                        $set('assignees', []);
                        $set('epic_id', null);
                    }),

                Forms\Components\Select::make('ticket_status_id')
                    ->label('Status')
                    ->options(function ($get) {
                        $projectId = $get('project_id');
                        if (! $projectId) {
                            return [];
                        }

                        return TicketStatus::where('project_id', $projectId)
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->default($statusId)
                    ->required()
                    ->searchable()
                    ->preload(),

                Forms\Components\Select::make('priority_id')
                    ->label('Priority')
                    ->options(TicketPriority::pluck('name', 'id')->toArray())
                    ->searchable()
                    ->preload()
                    ->nullable(),

                Forms\Components\Select::make('epic_id')
                    ->label('Epic')
                    ->options(function (callable $get) {
                        $projectId = $get('project_id');
                        
                        if (!$projectId) {
                            return [];
                        }
                        
                        return Epic::where('project_id', $projectId)
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->hidden(fn (callable $get): bool => !$get('project_id')),

                Forms\Components\TextInput::make('name')
                    ->label('Ticket Name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\RichEditor::make('description')
                    ->label('Description')
                    ->fileAttachmentsDirectory('attachments')
                    ->columnSpanFull(),

                // Multi-user assignment
                Forms\Components\Select::make('assignees')
                    ->label('Assigned to')
                    ->multiple()
                    ->relationship(
                        name: 'assignees',
                        titleAttribute: 'name',
                        modifyQueryUsing: function (Builder $query, callable $get) {
                            $projectId = $get('project_id');
                            if (! $projectId) {
                                return $query->whereRaw('1 = 0'); // Return empty result
                            }

                            $project = Project::find($projectId);
                            if (! $project) {
                                return $query->whereRaw('1 = 0'); // Return empty result
                            }

                            // Only show project members
                            return $query->whereHas('projects', function ($query) use ($projectId) {
                                $query->where('projects.id', $projectId);
                            });
                        }
                    )
                    ->searchable()
                    ->preload()
                    ->helperText('Select multiple users to assign this ticket to. Only project members can be assigned.')
                    ->hidden(fn (callable $get): bool => !$get('project_id'))
                    ->live(),
                Forms\Components\DatePicker::make('due_date')
                    ->label('Due Date')
                    ->nullable(),

                // Show created by field in edit mode
                Forms\Components\Select::make('created_by')
                    ->label('Created By')
                    ->relationship('creator', 'name')
                    ->disabled()
                    ->hiddenOn('create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('uuid')
                    ->label('Ticket ID')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('project.name')
                    ->label('Project')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('status.name')
                    ->label('Status')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('priority.name')
                    ->label('Priority')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'High' => 'danger',
                        'Medium' => 'warning',
                        'Low' => 'success',
                        default => 'gray',
                    })
                    ->sortable()
                    ->default('—')
                    ->placeholder('No Priority'),

                // Display multiple assignees
                Tables\Columns\TextColumn::make('assignees.name')
                    ->label('Assign To')
                    ->badge()
                    ->separator(',')
                    ->limitList(2)
                    ->expandableLimitedList()
                    ->searchable(),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Created By')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('epic.name')
                    ->label('Epic')
                    ->sortable()
                    ->searchable()
                    ->default('—')
                    ->placeholder('No Epic'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('project_id')
                    ->label('Project')
                    ->options(function () {
                        if (auth()->user()->hasRole(['super_admin'])) {
                            return Project::pluck('name', 'id')->toArray();
                        }
            
                        return auth()->user()->projects()->pluck('name', 'projects.id')->toArray();
                    })
                    ->searchable()
                    ->preload(),
            
                Tables\Filters\SelectFilter::make('ticket_status_id')
                    ->label('Status')
                    ->options(function () {
                        $projectId = request()->input('tableFilters.project_id');
                        
                        if (!$projectId) {
                            return [];
                        }
                        
                        return TicketStatus::where('project_id', $projectId)
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->searchable()
                    ->preload(),
                    
                Tables\Filters\SelectFilter::make('epic_id')
                    ->label('Epic')
                    ->options(function () {
                        $projectId = request()->input('tableFilters.project_id');
                        
                        if (!$projectId) {
                            return [];
                        }
                        
                        return Epic::where('project_id', $projectId)
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('priority_id')
                    ->label('Priority')
                    ->options(TicketPriority::pluck('name', 'id')->toArray())
                    ->searchable()
                    ->preload(),

                // Filter by assignees
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
            
                Tables\Filters\Filter::make('due_date')
                    ->form([
                        Forms\Components\DatePicker::make('due_from'),
                        Forms\Components\DatePicker::make('due_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['due_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('due_date', '>=', $date),
                            )
                            ->when(
                                $data['due_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('due_date', '<=', $date),
                            );
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(auth()->user()->hasRole(['super_admin'])),

                    Tables\Actions\BulkAction::make('updateStatus')
                        ->label('Update Status')
                        ->icon('heroicon-o-arrow-path')
                        ->form([
                            Forms\Components\Select::make('ticket_status_id')
                                ->label('Status')
                                ->options(function () {
                                    $firstTicket = Ticket::find(request('records')[0] ?? null);
                                    if (! $firstTicket) {
                                        return [];
                                    }

                                    return TicketStatus::where('project_id', $firstTicket->project_id)
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
                        }),

                    Tables\Actions\BulkAction::make('updatePriority')
                        ->label('Update Priority')
                        ->icon('heroicon-o-flag')
                        ->form([
                            Forms\Components\Select::make('priority_id')
                                ->label('Priority')
                                ->options(TicketPriority::pluck('name', 'id')->toArray())
                                ->nullable(),
                        ])
                        ->action(function (array $data, Collection $records) {
                            foreach ($records as $record) {
                                $record->update([
                                    'priority_id' => $data['priority_id'],
                                ]);
                            }
                        }),

                    // New bulk action for assigning users
                    Tables\Actions\BulkAction::make('assignUsers')
                        ->label('Assign Users')
                        ->icon('heroicon-o-user-plus')
                        ->form([
                            Forms\Components\Select::make('assignees')
                                ->label('Assignees')
                                ->multiple()
                                ->options(function () {
                                    $firstTicket = Ticket::find(request('records')[0] ?? null);
                                    if (! $firstTicket) {
                                        return [];
                                    }

                                    $project = $firstTicket->project;
                                    if (! $project) {
                                        return [];
                                    }

                                    return $project->members()
                                        ->select('users.id', 'users.name')
                                        ->pluck('users.name', 'users.id')
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
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [

        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTickets::route('/'),
            'create' => Pages\CreateTicket::route('/create'),
            'edit' => Pages\EditTicket::route('/{record}/edit'),
            'view' => Pages\ViewTicket::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $query = static::getEloquentQuery();

        return $query->count();
    }
}