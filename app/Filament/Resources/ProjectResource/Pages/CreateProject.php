<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProject extends CreateRecord
{
    protected static string $resource = ProjectResource::class;

    protected function afterCreate(): void
    {


        $createDefaultStatuses = $this->data['create_default_statuses'] ?? true;

        if ($createDefaultStatuses) {
            $defaultStatuses = [
                ['name' => 'Черновик', 'color' => '#6B7280', 'sort_order' => 0],
                ['name' => 'Задачи', 'color' => '#F59E0B', 'sort_order' => 1],
                ['name' => 'В процессе', 'color' => '#3B82F6', 'sort_order' => 2],
                ['name' => 'Готово', 'color' => '#8B5CF6', 'sort_order' => 3],
                ['name' => 'Тестирование', 'color' => '#1127ba', 'sort_order' => 4],
                ['name' => 'Законченный', 'color' => '#00ff19', 'sort_order' => 5]
            ];

            foreach ($defaultStatuses as $status) {
                $this->record->ticketStatuses()->create($status);
            }
        }


    }
}
