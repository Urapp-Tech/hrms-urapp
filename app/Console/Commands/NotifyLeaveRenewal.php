<?php

namespace App\Console\Commands;

use App\Mail\LeaveRenewalNotification;
use App\Models\Employee;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class NotifyLeaveRenewal extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notify:leave-renewal';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send email to employees whose leaves are renewed';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $employees = Employee::all();

        foreach ($employees as $employee) {
            if ($employee && ($employee->leave_start_date || $employee->company_doj)) {
                $joiningDate = $employee->leave_start_date ?? $employee->company_doj;
                $currentDate = date('Y-m-d');
                $monthDay = date('m-d', strtotime($joiningDate));
                $currentYear = date('Y', strtotime($currentDate));
                $leaveStartYear = ($currentDate >= "{$currentYear}-{$monthDay}") ? $currentYear : $currentYear - 1;
                $start_date = "{$leaveStartYear}-{$monthDay}";
                $end_date = date('Y-m-d', strtotime($start_date . ' +1 year -1 day'));

                if ($currentDate == $start_date) {
                    $leaveDetails = [
                        'start_date' => $start_date,
                        'end_date' => $end_date,
                    ];

                    // Send Email
                    Mail::to($employee->email)->send(new LeaveRenewalNotification($employee, $leaveDetails));

                    $this->info("Leave renewal email sent to: {$employee->email}");
                }
            }
        }

        return 0;
    }
}
