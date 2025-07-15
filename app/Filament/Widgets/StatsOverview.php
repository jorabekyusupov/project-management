<?php

namespace App\Filament\Widgets;

use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class StatsOverview extends BaseWidget
{
    use HasWidgetShield;

    protected static ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole('super_admin');

        if ($isSuperAdmin) {
            return $this->getSuperAdminStats();
        } else {
            return $this->getUserStats();
        }
    }

    protected function getSuperAdminStats(): array
    {
        $totalProjects = Project::count();
        $totalTickets = Ticket::count();
        $newTicketsLastWeek = Ticket::where('created_at', '>=', Carbon::now()->subDays(7))->count();
        $usersCount = User::count();
        $unassignedTickets = Ticket::whereDoesntHave('assignees')->count();
        $myTickets = DB::table('tickets')
            ->join('ticket_users', 'tickets.id', '=', 'ticket_users.ticket_id')
            ->where('ticket_users.user_id', auth()->id())
            ->count();
        
        $myCreatedTickets = Ticket::where('created_by', auth()->id())->count();
        $overdueTickets = Ticket::where('tickets.due_date', '<', Carbon::now())
            ->whereHas('status', function ($query) {
                $query->whereNotIn('name', ['Completed', 'Done', 'Closed']);
            })
            ->count();

        return [
            Stat::make(__('Total Projects'), $totalProjects)
                ->description(__('Active projects in the system'))
                ->descriptionIcon('heroicon-m-rectangle-stack')
                ->color('primary'),

            Stat::make(__('Total Tickets'), $totalTickets)
                ->description(__('Tickets across all projects'))
                ->descriptionIcon('heroicon-m-ticket')
                ->color('success'),

            Stat::make(__('My Assigned Tickets'), $myTickets)
                ->description(__('Tickets assigned to you'))
                ->descriptionIcon('heroicon-m-user-circle')
                ->color('info'),

            Stat::make(__('New Tickets This Week'), $newTicketsLastWeek)
                ->description(__('Created in the last 7 days'))
                ->descriptionIcon('heroicon-m-plus-circle')
                ->color('info'),

            Stat::make(__('Unassigned Tickets'), $unassignedTickets)
                ->description(__('Tickets without any assignee'))
                ->descriptionIcon('heroicon-m-user-minus')
                ->color($unassignedTickets > 0 ? 'danger' : 'success'),

            Stat::make(__('My Created Tickets'), $myCreatedTickets)
                ->description(__('Tickets you created'))
                ->descriptionIcon('heroicon-m-pencil-square')
                ->color('warning'),

            Stat::make(__('Overdue Tickets'), $overdueTickets)
                ->description(__('Past due date'))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($overdueTickets > 0 ? 'danger' : 'success'),

            Stat::make(__('Team Members'), $usersCount)
                ->description(__('Registered users'))
                ->descriptionIcon('heroicon-m-users')
                ->color('gray'),
        ];
    }

    protected function getUserStats(): array
    {
        $user = auth()->user();
        
        $myProjects = $user->projects()->count();
        
        $myProjectIds = $user->projects()->pluck('projects.id')->toArray();

        $projectTickets = Ticket::whereIn('project_id', $myProjectIds)->count();

        $myAssignedTickets = DB::table('tickets')
            ->join('ticket_users', 'tickets.id', '=', 'ticket_users.ticket_id')
            ->where('ticket_users.user_id', $user->id)
            ->count();

        $myCreatedTickets = Ticket::where('created_by', $user->id)->count();

        $newTicketsThisWeek = Ticket::whereIn('project_id', $myProjectIds)
            ->where('tickets.created_at', '>=', Carbon::now()->subDays(7))
            ->count();

        $myOverdueTickets = DB::table('tickets')
            ->join('ticket_users', 'tickets.id', '=', 'ticket_users.ticket_id')
            ->join('ticket_statuses', 'tickets.ticket_status_id', '=', 'ticket_statuses.id')
            ->where('ticket_users.user_id', $user->id)
            ->where('tickets.due_date', '<', Carbon::now())
            ->whereNotIn('ticket_statuses.name', ['Completed', 'Done', 'Closed'])
            ->count();

        $myCompletedThisWeek = DB::table('tickets')
            ->join('ticket_users', 'tickets.id', '=', 'ticket_users.ticket_id')
            ->join('ticket_statuses', 'tickets.ticket_status_id', '=', 'ticket_statuses.id')
            ->where('ticket_users.user_id', $user->id)
            ->whereIn('ticket_statuses.name', ['Completed', 'Done', 'Closed'])
            ->where('tickets.updated_at', '>=', Carbon::now()->subDays(7))
            ->count();

        $teamMembers = User::whereHas('projects', function ($query) use ($myProjectIds) {
            $query->whereIn('projects.id', $myProjectIds);
        })->where('id', '!=', $user->id)->count();

        return [
            Stat::make(__('Registered users'), $myProjects)
                ->description(__('Projects you are member of'))
                ->descriptionIcon('heroicon-m-rectangle-stack')
                ->color('primary'),

            Stat::make(__('My Assigned Tickets'), $myAssignedTickets)
                ->description(__('Tickets assigned to you'))
                ->descriptionIcon('heroicon-m-user-circle')
                ->color($myAssignedTickets > 10 ? 'danger' : ($myAssignedTickets > 5 ? 'warning' : 'success')),

            Stat::make(__('My Created Tickets'), $myCreatedTickets)
                ->description(__('Tickets you created'))
                ->descriptionIcon('heroicon-m-pencil-square')
                ->color('info'),

            Stat::make(__('Project Tickets'), $projectTickets)
                ->description(__('Total tickets in your projects'))
                ->descriptionIcon('heroicon-m-ticket')
                ->color('success'),

            Stat::make(__('Completed This Week'), $myCompletedThisWeek)
                ->description(__('Your completed tickets'))
                ->descriptionIcon('heroicon-m-check-circle')
                ->color($myCompletedThisWeek > 0 ? 'success' : 'gray'),

            Stat::make(__('New Tasks This Week'), $newTicketsThisWeek)
                ->description(__('Created in your projects'))
                ->descriptionIcon('heroicon-m-plus-circle')
                ->color('info'),

            Stat::make(__('My Overdue Tasks'), $myOverdueTickets)
                ->description(__('Your past due tickets'))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($myOverdueTickets > 0 ? 'danger' : 'success'),

            Stat::make(__('Team Members'), $teamMembers)
                ->description(__('People in your projects'))
                ->descriptionIcon('heroicon-m-users')
                ->color('gray'),
        ];
    }
}