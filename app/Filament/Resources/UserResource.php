<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Пользователи';
    protected static ?string $pluralLabel = 'Пользователи';
    protected static ?string $label = 'Пользователь';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label(__('name'))
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->label(__('email'))
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\DateTimePicker::make('email_verified_at'),
                Forms\Components\TextInput::make('password')
                    ->label(__('password'))
                    ->password()
                    ->dehydrateStateUsing(fn ($state) => ! empty($state) ? Hash::make($state) : null
                    )
                    ->dehydrated(fn ($state) => ! empty($state))
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->maxLength(255),
                Forms\Components\Select::make('roles')
                    ->relationship('roles', 'name')
                    ->label(__('roles'))
                    ->multiple()
                    ->preload()
                    ->searchable(),
                Forms\Components\TextInput::make('chat_id')
                    ->label(__('Телеграм Чат ID'))
                    ->maxLength(255)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label(__('email'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('roles.name')
                    ->label(__('roles'))
                    ->badge()
                    ->separator(',')
                    ->tooltip(fn (User $record): string => $record->roles->pluck('name')->join(', ') ?: 'No Roles')
                    ->sortable(),

                Tables\Columns\TextColumn::make('projects_count')
                    ->label(__('projects'))

                    ->counts('projects')
                    ->tooltip(fn (User $record): string => $record->projects->pluck('name')->join(', ') ?: 'No Projects')
                    ->sortable(),

                Tables\Columns\TextColumn::make('assigned_tickets_count')
                    ->label(__('Assigned Tickets'))
                    ->counts('assignedTickets')
                    ->tooltip('Number of tickets assigned to this user')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_tickets_count')
                    ->label(__('Created Tickets'))
                    ->getStateUsing(function (User $record): int {
                        return $record->createdTickets()->count();
                    })
                    ->tooltip('Number of tickets created by this user')
                    ->sortable(),

                Tables\Columns\TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->label(__('Email Verified At'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->label(__('Created at'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->label(__('Updated at'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('has_projects')
                    ->label(__('Has Projects'))
                    ->query(fn (Builder $query): Builder => $query->whereHas('projects')),

                Tables\Filters\Filter::make('has_assigned_tickets')
                    ->label(__('Has Assigned Tickets'))
                    ->query(fn (Builder $query): Builder => $query->whereHas('assignedTickets')),

                Tables\Filters\Filter::make('has_created_tickets')
                    ->label(__('Has Created Tickets'))
                    ->query(fn (Builder $query): Builder => $query->whereHas('createdTickets')),

                // Filter by role
                Tables\Filters\SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->label(__('roles'))
                    ->multiple()
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('email_unverified')
                    ->label(__('Email Unverified'))
                    ->query(fn (Builder $query): Builder => $query->whereNull('email_verified_at')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    
                    // NEW: Bulk action to assign role
                    Tables\Actions\BulkAction::make('assignRole')
                        ->label('Assign Role')
                        ->icon('heroicon-o-shield-check')
                        ->form([
                            Forms\Components\Select::make('roles')
                                ->label('Roles')
                                ->relationship('roles', 'name')
                                ->multiple()
                                ->preload()
                                ->searchable()
                                ->required(),
                            
                            Forms\Components\Radio::make('role_mode')
                                ->label('Assignment Mode')
                                ->options([
                                    'replace' => 'Replace existing roles',
                                    'add' => 'Add to existing roles',
                                ])
                                ->default('add')
                                ->required(),
                        ])
                        ->action(function (array $data, $records) {
                            foreach ($records as $record) {
                                if ($data['role_mode'] === 'replace') {
                                    $record->roles()->sync($data['roles']);
                                } else {
                                    $record->roles()->syncWithoutDetaching($data['roles']);
                                }
                            }
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ProjectsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit')
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}