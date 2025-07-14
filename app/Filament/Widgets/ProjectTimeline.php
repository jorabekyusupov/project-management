<?php

namespace App\Filament\Widgets;

use App\Models\Project;
use Filament\Widgets\Widget;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class ProjectTimeline extends Widget
{
    use HasWidgetShield;
    protected static string $view = 'filament.widgets.project-timeline';
    
    protected int | string | array $columnSpan = 'full';

    static ?int $sort = 2;
    
    public string $filter = 'active';
    
    public function getProjects()
    {
        $query = Project::query()
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->orderBy('name');
            
        if ($this->filter === 'active') {
            $query->where('end_date', '>=', Carbon::today());
        }
            
        $userIsSuperAdmin = auth()->user() && (
            (method_exists(auth()->user(), 'hasRole') && auth()->user()->hasRole('super_admin'))
            || (isset(auth()->user()->role) && auth()->user()->role === 'super_admin')
        );

        if (!$userIsSuperAdmin) {
            $query->whereHas('members', function ($query) {
                $query->where('user_id', auth()->id());
            });
        }
            
        return $query->get();
    }
    
    public function setFilter($filter)
    {
        $this->filter = $filter;
    }
    
    public function getTotalProjects()
    {
        $query = Project::query()
            ->whereNotNull('start_date')
            ->whereNotNull('end_date');
            
        $userIsSuperAdmin = auth()->user() && (
            (method_exists(auth()->user(), 'hasRole') && auth()->user()->hasRole('super_admin'))
            || (isset(auth()->user()->role) && auth()->user()->role === 'super_admin')
        );

        if (!$userIsSuperAdmin) {
            $query->whereHas('members', function ($query) {
                $query->where('user_id', auth()->id());
            });
        }
            
        return [
            'all' => $query->count(),
            'active' => $query->where('end_date', '>=', Carbon::today())->count(),
            'completed' => $query->where('end_date', '<', Carbon::today())->count(),
        ];
    }
    
    protected function getViewData(): array
    {
        $projects = $this->getProjects();
        $today = Carbon::today();
        $counts = $this->getTotalProjects();
        
        $timelineData = [];
        
        foreach ($projects as $project) {
            if (!$project->start_date || !$project->end_date) {
                continue;
            }
            
            $startDate = Carbon::parse($project->start_date);
            $endDate = Carbon::parse($project->end_date);
            $totalDays = $startDate->diffInDays($endDate) + 1;
            
            if ($endDate->lt($startDate)) {
                continue;
            }
            
            $pastDays = 0;
            $remainingDays = 0;
            $progressPercent = 0;
            
            if ($today->lt($startDate)) {
                $pastDays = 0;
                $remainingDays = $totalDays;
                $progressPercent = 0;
            } elseif ($today->gt($endDate)) {
                $pastDays = $totalDays;
                $remainingDays = 0;
                $progressPercent = 100;
            } else {
                $pastDays = $startDate->diffInDays($today);
                $remainingDays = $today->diffInDays($endDate);
                $progressPercent = ($pastDays / $totalDays) * 100;
            }
            
            $status = 'In Progress';
            $statusColor = 'text-blue-600';
            
            if ($today->gt($endDate)) {
                $status = 'Completed';
                $statusColor = 'text-green-600';
            } elseif ($project->remaining_days <= 0) {
                $status = 'Overdue';
                $statusColor = 'text-red-600';
            } elseif ($project->remaining_days <= 7) {
                $status = 'Approaching Deadline';
                $statusColor = 'text-yellow-600';
            } elseif ($today->lt($startDate)) {
                $status = 'Not Started';
                $statusColor = 'text-gray-600';
            }
            
            $timelineData[] = [
                'id' => $project->id,
                'name' => $project->name,
                'start_date' => $startDate->format('d/m/Y'),
                'end_date' => $endDate->format('d/m/Y'),
                'total_days' => $totalDays,
                'past_days' => $pastDays,
                'remaining_days' => $project->remaining_days,
                'progress_percent' => round($progressPercent, 1),
                'status' => $status,
                'status_color' => $statusColor,
            ];
        }
        
        usort($timelineData, function($a, $b) {
            if ($a['remaining_days'] <= 0 && $b['remaining_days'] > 0) {
                return -1;
            }
            if ($a['remaining_days'] > 0 && $b['remaining_days'] <= 0) {
                return 1;
            }
            
            return $a['remaining_days'] <=> $b['remaining_days'];
        });
        
        return [
            'projects' => $timelineData,
            'filter' => $this->filter,
            'counts' => $counts,
        ];
    }
}