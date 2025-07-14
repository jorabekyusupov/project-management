<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class UserStatisticsTable extends BaseWidget
{
    use HasWidgetShield;
    
    protected static ?string $heading = 'User Statistics';
    
    protected int | string | array $columnSpan = [
        'md' => 2,
        'xl' => 1,
    ];
    
    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                User::query()
                    ->when(!auth()->user()->hasRole('super_admin'), function ($query) {
                        $query->where('id', auth()->id());
                    })
                    ->withCount([
                        'projects as total_projects',
                        'assignedTickets as total_assigned_tickets'
                    ])
                    ->orderBy('name')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('User')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->icon('heroicon-o-user'),
                
                Tables\Columns\TextColumn::make('total_projects')
                    ->label('Total Projects')
                    ->alignCenter()
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state === 0 => 'gray',
                        $state <= 2 => 'warning',
                        $state <= 5 => 'success',
                        default => 'primary',
                    }),
                
                Tables\Columns\TextColumn::make('total_assigned_tickets')
                    ->label('Total Tickets')
                    ->alignCenter()
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state === 0 => 'gray',
                        $state <= 3 => 'success',
                        $state <= 10 => 'warning',
                        default => 'danger',
                    }),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Joined')
                    ->date('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('total_assigned_tickets', 'desc')
            ->paginated([5, 25, 50]);
    }
}