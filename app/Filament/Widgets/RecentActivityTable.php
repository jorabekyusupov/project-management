<?php

namespace App\Filament\Widgets;

use App\Models\TicketHistory;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class RecentActivityTable extends BaseWidget
{
    use HasWidgetShield;



    public function getTableHeading(): ?string
    {
      return  __('Recent Activities');
    }


    protected int | string | array $columnSpan = [
        'md' => 2,
        'xl' => 1,
    ];
    
    protected static ?int $sort = 3;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                TicketHistory::query()
                    ->with(['ticket.project', 'user', 'status'])
                    ->when(!auth()->user()->hasRole('super_admin'), function ($query) {
                        $query->whereHas('ticket.project.members', function ($subQuery) {
                            $subQuery->where('user_id', auth()->id());
                        });
                    })
                    ->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('activity_summary')
                    ->label('Activity')
                    ->state(function (TicketHistory $record): string {
                        $ticketName = $record->ticket->name ?? __('Unknown ticket');
                        $trimmedName = strlen($ticketName) > 40 ? substr($ticketName, 0, 40) . '...' : $ticketName;
                        $userName = $record->user->name ?? __('Unknown user');
                        return "<span class='text-primary-600 font-medium'>{$userName}</span> изменено \"{$trimmedName}\"";
                    })
                    ->description(function (TicketHistory $record): string {
                        $isToday = $record->created_at->isToday();
                        $time = $isToday 
                            ? $record->created_at->format('H:i')
                            : $record->created_at->format('M d, H:i');
                        $project = $record->ticket->project->name ?? 'No Project';
                        $uuid = $record->ticket->uuid ?? '';
                        return "{$time} • {$uuid} • {$project}";
                    })
                    ->html()
                    ->searchable(['users.name', 'tickets.name', 'tickets.uuid'])
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('status.name')
                    ->label('Status')
                    ->badge()
                    ->alignEnd()
                    ->color(fn (TicketHistory $record): string => match($record->status->name ?? '') {
                        'To Do', 'Backlog' => 'gray',
                        'In Progress', 'Doing' => 'warning', 
                        'Review', 'Testing' => 'info',
                        'Done', 'Completed' => 'success',
                        'Cancelled', 'Blocked' => 'danger',
                        default => 'primary',
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('start_date')
                            ->label('Start Date')
                            ->default(today()),
                        \Filament\Forms\Components\DatePicker::make('end_date')
                            ->label('End Date')
                            ->default(today()),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['start_date'], function ($query, $date) {
                                $query->whereDate('created_at', '>=', $date);
                            })
                            ->when($data['end_date'], function ($query, $date) {
                                $query->whereDate('created_at', '<=', $date);
                            });
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['start_date'] ?? null) {
                            $indicators[] = __("From").": " . \Carbon\Carbon::parse($data['start_date'])->format('M d, Y');
                        }
                        if ($data['end_date'] ?? null) {
                            $indicators[] = __("To").": ". \Carbon\Carbon::parse($data['end_date'])->format('M d, Y');
                        }
                        return $indicators;
                    }),

                Tables\Filters\Filter::make('today')
                    ->label(__('Today Only'))
                    ->query(fn ($query) => $query->whereDate('created_at', today()))
                    ->toggle(),

                Tables\Filters\SelectFilter::make('user')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->filtersFormColumns(2)
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->size('sm')
                    ->tooltip(__('Open Ticket'))
                    ->url(fn (TicketHistory $record): string => 
                        route('filament.admin.resources.tickets.view', $record->ticket)
                    )
                    ->openUrlInNewTab()
            ])
            ->recordUrl(fn (TicketHistory $record) => 
                route('filament.admin.resources.tickets.view', $record->ticket)
            )
            ->paginated([5, 25, 50])
            ->poll('30s')
            ->striped()
            ->emptyStateHeading(__('No Activity Found'))
            ->emptyStateDescription(__('No ticket activities found for the selected period.'))
            ->emptyStateIcon('heroicon-o-clock');
    }
}