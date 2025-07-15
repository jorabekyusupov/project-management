<?php

namespace App\Filament\Resources;

use App\Filament\Actions\ImportTicketsAction;
use App\Filament\Resources\ProjectResource\Pages;
use App\Filament\Resources\ProjectResource\RelationManagers;
use App\Models\Project;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;
    protected static ?string $label = 'Проект';
    protected static ?string $pluralLabel = 'Проекты';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label(__('name'))
                    ->required()
                    ->maxLength(255),
                Forms\Components\RichEditor::make('description')
                    ->label(__('description'))
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('ticket_prefix')
                    ->label(__('ticket_prefix'))
                    ->required()
                    ->maxLength(255),
                Forms\Components\DatePicker::make('start_date')
                    ->label(__('start_date'))
                    ->native(false)
                    ->displayFormat('d/m/Y'),
                Forms\Components\DatePicker::make('end_date')
                    ->label(__('end_date'))
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->afterOrEqual('start_date'),
                Forms\Components\Toggle::make('create_default_statuses')
                    ->label(__('use_default_ticket_statuses'))
                    ->helperText(__("Create standard Backlog, To Do, In Progress, Review, and Done statuses automatically"))
                    ->default(true)
                    ->dehydrated(false)
                    ->visible(fn($livewire) => $livewire instanceof Pages\CreateProject),
                Forms\Components\TextInput::make('chat_id')
                    ->label('Телеграм Чат ID')
                    ->helperText('Для вашей нужной группы
Добавьте бота @myidbot и из этой группы
Вы отправляете команду /getgroupid и получаете ID группы, который нужно указать здесь')
                    ->maxLength(255),
                Forms\Components\TextInput::make('thread_id')
                    ->label('Телеграм Чат Тема ID')
                    ->helperText('Вы присоединитесь к соответствующей группе Chat ID, указанной выше, и выберите в ней тему, которая вам принадлежит, чтобы скопировать ссылку на любое сообщение, и ссылка будет выглядеть так:
https://t.me/c/2535102279/3/60 и номер 3 в нем является идентификатором этой темы.
https://t.me/c/2535102279/Нам нужен ID/60')
                    ->maxLength(255)

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('ticket_prefix')
                    ->label(__('ticket_prefix'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('start_date')
                    ->label(__('start_date'))
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->label(__('end_date'))
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('remaining_days')
                    ->label(__('remaining_days'))
                    ->getStateUsing(function (Project $record): ?string {
                        if (!$record->end_date) {
                            return null;
                        }

                        return $record->remaining_days . ' ' . __('days');
                    })
                    ->badge()
                    ->color(fn(Project $record): string => !$record->end_date ? 'gray' :
                        ($record->remaining_days <= 0 ? 'danger' :
                            ($record->remaining_days <= 7 ? 'warning' : 'success'))
                    ),
                Tables\Columns\TextColumn::make('members_count')
                    ->counts('members')
                    ->label(__('members')),
                Tables\Columns\TextColumn::make('tickets_count')
                    ->counts('tickets')
                    ->label(__('tickets')),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('Updated at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // No toggle filter here
            ])
            ->actions([
                Tables\Actions\EditAction::make()
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\TicketStatusesRelationManager::class,
            RelationManagers\MembersRelationManager::class,
            RelationManagers\EpicsRelationManager::class,
            RelationManagers\TicketsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'edit' => Pages\EditProject::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $userIsSuperAdmin = auth()->user() && (
                (method_exists(auth()->user(), 'hasRole') && auth()->user()->hasRole('super_admin'))
                || (isset(auth()->user()->role) && auth()->user()->role === 'super_admin')
            );

        if (!$userIsSuperAdmin) {
            $query->whereHas('members', function (Builder $query) {
                $query->where('user_id', auth()->id());
            });
        }

        return $query;
    }
}
