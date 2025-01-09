<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\Holiday;
use Illuminate\Console\Command;

class AddEmployeesToHolidays extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'holidays:add-employees';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Associate all employees with all existing holidays';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Fetch all employees and holidays
        $employees = Employee::all();
        $holidays = Holiday::all();

        if ($employees->isEmpty() || $holidays->isEmpty()) {
            $this->warn('No employees or holidays found.');
            return 0;
        }

        // Loop through all holidays and attach all employees
        foreach ($holidays as $holiday) {
            $employeeIds = $employees->pluck('id')->toArray();
            $holiday->employees()->syncWithoutDetaching($employeeIds); // Attach employees without detaching existing ones
        }

        $this->info('All employees have been associated with all holidays.');
        return 0;
    }
}
