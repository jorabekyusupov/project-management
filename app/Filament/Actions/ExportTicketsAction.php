<?php

namespace App\Filament\Actions;

use App\Exports\TicketsExport;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Notifications\Notification;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportTicketsAction
{
    public static function make(): Action
    {
        return Action::make('export_tickets')
            ->label(__("Export to Excel"))
            ->icon('heroicon-m-arrow-down-tray')
            ->color('success')
            ->form([
                Section::make(__('Select Columns to Export'))
                    ->description(__('Choose which columns you want to include in the Excel export'))
                    ->schema([
                        CheckboxList::make('columns')
                            ->label(__('Columns'))
                            ->options([
                                'uuid' => __('Ticket ID'),
                                'name' => __('title'),
                                'description' => __('description'),
                                'status' => __('status'),
                                'assignee' => __('assignee'),
                                'project' => __('project'),
                                'epic' => __('epic'),
                                'due_date' => __('Due Date'),
                                'created_at' => __('Created at'),
                                'updated_at' => __('Updated at'),
                            ])
                            ->default(['uuid', 'name', 'status', 'assignee', 'due_date', 'created_at'])
                            ->required()
                            ->minItems(1)
                            ->columns(2)
                            ->gridDirection('row')
                    ])
            ])
            ->action(function (array $data, $livewire): void {
                $livewire->exportTickets($data['columns'] ?? []);
            });
    }
}