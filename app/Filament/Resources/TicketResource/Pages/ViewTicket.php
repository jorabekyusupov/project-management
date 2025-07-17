<?php

namespace App\Filament\Resources\TicketResource\Pages;

use App\Filament\Pages\ProjectBoard;
use App\Filament\Resources\TicketResource;
use App\Models\Ticket;
use App\Models\TicketComment;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\RichEditor;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    public ?int $editingCommentId = null;

    protected function getHeaderActions(): array
    {
        $ticket = $this->getRecord();
        $project = $ticket->project;
        $canComment = auth()->user()->hasRole(['super_admin'])
            || $project->members()->where('users.id', auth()->id())->exists();

        return [
           Actions\Action::make('download_file')
                ->label('Download File')
               ->translateLabel()
                ->icon('heroicon-c-folder-arrow-down')
                ->visible(fn (Ticket $record): bool => !empty($record->file))
                ->action(function ($record) {
                    return response()->download(storage_path('app/public/' . $record->file));
                }),
            Actions\EditAction::make()
                ->visible(function () {
                    $ticket = $this->getRecord();

                    return auth()->user()->hasRole(['super_admin'])
                        || $ticket->created_by === auth()->id()
                        || $ticket->assignees()->where('users.id', auth()->id())->exists();
                }),

            Actions\Action::make('addComment')
                ->label(__('Add Comment'))
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('success')
                ->form([
                    RichEditor::make('comment')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $ticket = $this->getRecord();

                    $ticket->comments()->create([
                        'user_id' => auth()->id(),
                        'comment' => $data['comment'],
                    ]);

                    Notification::make()
                        ->title(__('Comment added successfully'))
                        ->success()
                        ->send();
                })
                ->visible($canComment),

            Action::make('back')
                ->label(__('Back to Board'))
                ->color('gray')
                ->url(fn () => ProjectBoard::getUrl(['project_id' => $this->record->project_id])),
        ];
    }

    public function handleEditComment($id)
    {
        $comment = TicketComment::find($id);

        if (! $comment) {
            Notification::make()
                ->title(__('Comment not found'))
                ->danger()
                ->send();

            return;
        }

        // Check permissions
        if (! auth()->user()->hasRole(['super_admin']) && $comment->user_id !== auth()->id()) {
            Notification::make()
                ->title(__('You do not have permission to edit this comment'))
                ->danger()
                ->send();

            return;
        }

        $this->editingCommentId = $id; // Set ID komentar yang sedang diedit
        $this->mountAction('editComment', ['commentId' => $id]);
    }

    public function handleDeleteComment($id)
    {
        $comment = TicketComment::find($id);

        if (! $comment) {
            Notification::make()
                ->title(__('Comment not found'))
                ->danger()
                ->send();

            return;
        }

        // Check permissions
        if (! auth()->user()->hasRole(['super_admin']) && $comment->user_id !== auth()->id()) {
            Notification::make()
                ->title(__('You do not have permission to delete this comment'))
                ->danger()
                ->send();

            return;
        }

        $comment->delete();

        Notification::make()
            ->title(__('Comment deleted successfully'))
            ->success()
            ->send();

        // Refresh the page
        $this->redirect($this->getResource()::getUrl('view', ['record' => $this->getRecord()]));
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Grid::make(3)
                    ->schema([
                        Group::make([
                            Section::make()
                                ->schema([
                                    TextEntry::make('uuid')
                                        ->label(__('Ticket ID'))
                                        ->copyable(),

                                    TextEntry::make('name')
                                        ->label(__('Ticket Name')),

                                    TextEntry::make('project.name')
                                        ->label(__('project')),
                                ]),
                        ])->columnSpan(1),

                        Group::make([
                            Section::make()
                                ->schema([
                                    TextEntry::make('status.name')
                                        ->label(__('status'))
                                        ->badge()
                                        ->color(fn ($state) => match ($state) {
                                            'To Do' => 'warning',
                                            'In Progress' => 'info',
                                            'Review' => 'primary',
                                            'Done' => 'success',
                                            default => 'gray',
                                        }),

                                    // FIXED: Multi-user assignees
                                    TextEntry::make('assignees.name')
                                        ->label(__('Assigned To'))
                                        ->badge()
                                        ->separator(',')
                                        ->default('Unassigned'),

                                    TextEntry::make('creator.name')
                                        ->label(__('Created By'))
                                        ->default('Unknown'),

                                    TextEntry::make('due_date')
                                        ->label(__('Due Date'))
                                        ->date(),
                                ]),
                        ])->columnSpan(1),

                        Group::make([
                            Section::make()
                                ->schema([
                                    TextEntry::make('created_at')
                                        ->label(__('Created at'))
                                        ->dateTime(),

                                    TextEntry::make('updated_at')
                                        ->label(__('Updated at'))
                                        ->dateTime(),

                                    TextEntry::make('epic.name')
                                        ->label(__('epic'))
                                        ->default('No Epic'),
                                ]),
                        ])->columnSpan(1),
                    ]),

                Section::make(__('description'))
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        TextEntry::make('description')
                            ->label(__('description'))
                            ->hiddenLabel()
                            ->html()
                            ->columnSpanFull(),
                    ]),

                Section::make(__('comments'))
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->description(__('Discussion about this ticket'))
                    ->schema([
                        TextEntry::make('comments_list')
                            ->label('Recent Comments')
                            ->state(function (Ticket $record) {
                                if (method_exists($record, 'comments')) {
                                    return $record->comments()->with('user')->latest()->get();
                                }

                                return collect();
                            })
                            ->view('filament.resources.ticket-resource.latest-comments'),
                    ])
                    ->collapsible(),

                Section::make(__('Status History'))
                    ->icon('heroicon-o-clock')
                    ->collapsible()
                    ->schema([
                        TextEntry::make('histories')
                            ->hiddenLabel()
                            ->view('filament.resources.ticket-resource.timeline-history'),
                    ]),
            ]);
    }

    protected function getActions(): array
    {
        return [
            Action::make('editComment')
                ->label(__('Edit Comment'))
                ->mountUsing(function (Forms\Form $form, array $arguments) {
                    $commentId = $arguments['commentId'] ?? null;

                    if (! $commentId) {
                        return;
                    }

                    $comment = TicketComment::find($commentId);

                    if (! $comment) {
                        return;
                    }

                    $form->fill([
                        'commentId' => $comment->id,
                        'comment' => $comment->comment,
                    ]);
                })
                ->form([
                    Hidden::make('commentId')
                        ->required(),
                    RichEditor::make('comment')
                        ->label(__('comment'))
                        ->toolbarButtons([
                            'blockquote',
                            'bold',
                            'bulletList',
                            'codeBlock',
                            'h2',
                            'h3',
                            'italic',
                            'link',
                            'orderedList',
                            'redo',
                            'strike',
                            'underline',
                            'undo',
                        ])
                        ->required(),
                ])
                ->action(function (array $data) {
                    $comment = TicketComment::find($data['commentId']);

                    if (! $comment) {
                        Notification::make()
                            ->title(__('Comment not found'))
                            ->danger()
                            ->send();

                        return;
                    }

                    // Check permissions
                    if (! auth()->user()->hasRole(['super_admin']) && $comment->user_id !== auth()->id()) {
                        Notification::make()
                            ->title(__('You do not have permission to edit this comment'))
                            ->danger()
                            ->send();

                        return;
                    }

                    $comment->update([
                        'comment' => $data['comment'],
                    ]);

                    Notification::make()
                        ->title(__('Comment updated successfully'))
                        ->success()
                        ->send();

                    // Reset editingCommentId
                    $this->editingCommentId = null;

                    // Refresh the page
                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->getRecord()]));
                })
                ->modalWidth('lg')
                ->modalHeading(__('Edit Comment'))
                ->modalSubmitActionLabel(__('Update'))
                ->color('success')
                ->icon('heroicon-o-pencil'),
        ];
    }
}