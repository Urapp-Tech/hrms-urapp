<?php

namespace App\Console\Commands;

use App\Models\AttendanceEmployee;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\Utility;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncAttendanceCheckout extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:sync-checkout';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync the first DUTY_ON or LOCK_IN as check-in and the last DUTY_OFF or LOCK_OUT as checkout entry for AttendanceEmployee.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $logs = AttendanceLog::where('processed', false)
                ->orderBy('employee_id')
                ->orderBy('timestamp')
                ->get()
                ->groupBy('employee_id')
                ->map(function ($groupedByEmployee) {
                    return $groupedByEmployee->groupBy(function ($item) {
                        return \Carbon\Carbon::parse($item['timestamp'])->format('Y-m-d');
                    });
                });

            foreach ($logs as $employeeId => $logsByDate) {

                foreach ($logsByDate as $logEntries) {
                    # code...
                    $firstDutyInLog = $logEntries->sortBy('timestamp')->filter(function ($log) {
                        return $log->status === 'DUTY_ON' || $log->status === 'LOCK_IN';
                    })->first();

                    $lastDutyOffLog = $logEntries->sortBy('timestamp')->filter(function ($log) {
                        return $log->status === 'DUTY_OFF' || $log->status === 'LOCK_OUT';
                    })->last();

                    if ($firstDutyInLog && $lastDutyOffLog) {
                        $shiftStartDate = Carbon::parse($firstDutyInLog->timestamp)->format('Y-m-d');
                        $clockOutTimestamp = Carbon::parse($lastDutyOffLog->timestamp);

                        // Fetch the employee's shift details
                        $employee = Employee::find($employeeId);
                        $settings = Utility::fetchSettings($employee->created_by);
                        $gracePeriod = $this->getValByName('company_grace_time', $settings);
                        $gracePeriod = $gracePeriod ?: '00:00:00';

                        $shift = Shift::find($employee->shift_id);

                        if (!$shift) {
                            $this->error("Shift not found for employee ID: $employeeId");
                            continue;
                        }

                        $isNightShift = strtotime($shift->start_time) > strtotime($shift->end_time);


                        $shiftEndTime = Carbon::parse($shiftStartDate . ' ' . $shift->end_time);
                        // if ($shiftEndTime->lt(Carbon::parse($shiftStartDate . ' ' . $shift->start_time))) {
                        //     $shiftEndTime->addDay(); // Handle night shifts
                        // }

                        // Determine the date for clock_out
                        $existingShiftAttendance = AttendanceEmployee::where('employee_id', $employee->id)
                        ->where('date', $shiftStartDate)
                        ->first();
                        $prevShiftAttendance = AttendanceEmployee::where('employee_id', $employee->id)
                        ->where('date', Carbon::parse($shiftStartDate)->subDay()->format('Y-m-d'))
                        ->first();
                        $attendanceDate= '';
                        if (!$existingShiftAttendance && $isNightShift && in_array($lastDutyOffLog->status, ['DUTY_OFF', 'LOCK_OUT']) && $prevShiftAttendance) {
                            $attendanceDate = Carbon::parse($shiftStartDate)->subDay()->format('Y-m-d');
                        }
                        else {
                            $attendanceDate = $shiftStartDate;
                        }

                        // $attendanceDate = $clockOutTimestamp->lessThanOrEqualTo($shiftEndTime)
                        //     ? $shiftStartDate // Same date for normal shifts
                        //     : Carbon::parse($shiftStartDate)->subDay()->format('Y-m-d'); // Previous day for night shifts

                        $attendance = AttendanceEmployee::updateOrCreate(
                            [
                                'employee_id' => $employeeId,
                                'date' => $attendanceDate,
                            ],
                            [
                                'clock_in' => Carbon::parse($firstDutyInLog->timestamp)->format('H:i:s'),
                                'clock_out' => $clockOutTimestamp->format('H:i:s'),
                            ]
                        );

                        $shiftStartDateTime = Carbon::parse($attendance->date . ' ' . $shift->start_time);
                        $shiftEndDateTime = Carbon::parse($attendance->date . ' ' . $shift->end_time);
                        if ($shiftEndDateTime->lt($shiftStartDateTime)) {
                            $shiftEndDateTime->addDay(); // Handle night shifts
                        }

                        $clockInTime = Carbon::parse( $attendance->date . ' ' . $attendance->clock_in);
                        $clockOutTime = Carbon::parse($attendance->date . ' ' . $attendance->clock_out);
                        if($clockOutTime->lt($clockInTime)) {
                            $clockOutTime->addDay();
                        }

                        // Calculate late, early leaving, and overtime
                        $attendance->late = $clockInTime->greaterThan($shiftStartDateTime->addSeconds($this->convertTimeToSeconds($gracePeriod)))
                            ? $clockInTime->diff($shiftStartDateTime)->format('%H:%I:%S')
                            : '00:00:00';

                        $attendance->early_leaving = $clockOutTime->lessThan($shiftEndDateTime)
                            ? $shiftEndDateTime->diff($clockOutTime)->format('%H:%I:%S')
                            : '00:00:00';

                        $attendance->overtime = $clockOutTime->greaterThan($shiftEndDateTime)
                            ? $clockOutTime->diff($shiftEndDateTime)->format('%H:%I:%S')
                            : '00:00:00';

                        $attendance->save();
                    }
                    else if($firstDutyInLog) {

                        $shiftStartDate = Carbon::parse($firstDutyInLog->timestamp)->format('Y-m-d');

                        // Fetch the employee's shift details
                        $employee = Employee::find($employeeId);
                        $settings = Utility::fetchSettings($employee->created_by);
                        $gracePeriod = $this->getValByName('company_grace_time', $settings);
                        $gracePeriod = $gracePeriod ?: '00:00:00';

                        $shift = Shift::find($employee->shift_id);

                        if (!$shift) {
                            $this->error("Shift not found for employee ID: $employeeId");
                            continue;
                        }

                        $isNightShift = strtotime($shift->start_time) > strtotime($shift->end_time);

                        $existingShiftAttendance = AttendanceEmployee::where('employee_id', $employee->id)
                        ->where('date', $shiftStartDate)
                        ->first();
                        $prevShiftAttendance = AttendanceEmployee::where('employee_id', $employee->id)
                        ->where('date', Carbon::parse($shiftStartDate)->subDay()->format('Y-m-d'))
                        ->first();
                        $attendanceDate= '';
                        if (!$existingShiftAttendance && $isNightShift && in_array($lastDutyOffLog->status, ['DUTY_OFF', 'LOCK_OUT']) && $prevShiftAttendance) {
                            $attendanceDate = Carbon::parse($shiftStartDate)->subDay()->format('Y-m-d');
                        }
                        else {
                            $attendanceDate = $shiftStartDate;
                        }

                        $attendance = AttendanceEmployee::updateOrCreate(
                            [
                                'employee_id' => $employeeId,
                                'date' => $attendanceDate,
                            ],
                            [
                                'clock_in' => Carbon::parse($firstDutyInLog->timestamp)->format('H:i:s'),
                            ]
                        );


                    }

                    // Mark logs as processed
                    foreach ($logEntries as $log) {
                        $log->update(['processed' => true ]);
                    }
                }
            }

            $this->info('Attendance check-in and checkout entries synced successfully.');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error syncing attendance: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    public function getValByName($key, $setting)
    {
        if (!isset($setting[$key]) || empty($setting[$key])) {
            $setting[$key] = '';
        }
        return $setting[$key];
    }

    function convertTimeToSeconds( string $time) {
        list($hours, $minutes, $seconds) = explode(':', $time);
        $totalSeconds = ($hours * 3600) + ($minutes * 60) + $seconds;
        return $totalSeconds;
    }
}
