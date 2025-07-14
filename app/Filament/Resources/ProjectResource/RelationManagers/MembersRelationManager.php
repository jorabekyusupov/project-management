<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Library\Bot\InfoBot;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return $ownerRecord->members_count ?? $ownerRecord->members()->count();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([

            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable()
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['name', 'email'])
                    ->after(function ($record) {
                        if ($record->chat_id) {
                            $ownerRecord = $this->getOwnerRecord();

                            app(InfoBot::class)
                                ->send($record->chat_id,
                                    '🆕 Вам добавлен новый участник в проект: ' . $ownerRecord->name . PHP_EOL .
                                    '📅 Дата начала: ' . $ownerRecord->start_date->format('d/m/Y') . PHP_EOL .
                                    '📅 Дата окончания: ' . ($ownerRecord->end_date ? $ownerRecord->end_date->format('d/m/Y') : 'Не указана') . PHP_EOL
                               );
                        }
                    })
                    ->label('Add Member'),
            ])
            ->actions([
                Tables\Actions\DetachAction::make()
                    ->label('Remove'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make()
                        ->label('Remove Selected'),
                ]),
            ]);
    }
}
