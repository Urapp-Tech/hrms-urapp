<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceEmployee;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\Utility;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    public function markAttendances(Request $request, $companyId)
    {
        // Validate the request
        $validated = $request->validate([
            '*.enrollmentNumber' => 'required',
            '*.machineNumber' => 'required|integer',
            '*.time' => 'required|date_format:Y-m-d\TH:i:s.v\Z',
            '*.status' => 'required|integer|min:0|max:5',
        ]);

        try {
            // Grace period (in seconds, 30 minutes)
            $settings = Utility::fetchSettings($companyId);
            $gracePeriod = $this->getValByName('company_grace_time', $settings);
            $gracePeriod = $gracePeriod ?: '00:00:00';

            foreach ($validated as $attendance) {
                // Get employee by enrollment number and company
                $employee = $this->getEmployeeByEnrollmentNumberAndCompany($attendance['enrollmentNumber'], $companyId);

                if(!$employee) {
                    continue;
                }
                // Log the attendance status
                AttendanceLog::create([
                    'employee_id' => $employee->id,
                    'status' => $this->mapStatusToAttendance($attendance['status']),
                    'timestamp' => $attendance['time'],
                ]);

                // Fetch shift details
                $shift = Shift::find($employee->shift_id);
                if (!$shift) {
                    throw new \Exception("Shift not assigned for employee ID {$employee->id}.");
                }

                $shiftStartTime = $shift->start_time;
                $shiftEndTime = $shift->end_time; // 01:00:00
                $isNightShift = strtotime($shiftStartTime) > strtotime($shiftEndTime);

                $shiftStartDate = Carbon::parse($attendance['time'])->format('Y-m-d'); // 2024-12-19
                $clockOutTimestamp = Carbon::parse($attendance['time']); // 2024-12-19 20:00:00

                // Determine the correct attendance date
                $existingShiftAttendance = AttendanceEmployee::where('employee_id', $employee->id)
                    ->where('date', $shiftStartDate)
                    ->first();
                $prevShiftAttendance = AttendanceEmployee::where('employee_id', $employee->id)
                    ->where('date', Carbon::parse($shiftStartDate)->subDay()->format('Y-m-d'))
                    ->first();
                if (!$existingShiftAttendance && $isNightShift && in_array($this->mapStatusToAttendance($attendance['status']), ['DUTY_OFF']) && $prevShiftAttendance) {
                    $attendanceDate = Carbon::parse($shiftStartDate)->subDay()->format('Y-m-d');
                }
                else {
                    $attendanceDate = $shiftStartDate;
                }


                $shiftEndDateTime = Carbon::parse($attendanceDate . ' ' . $shiftEndTime);
                if ($shiftEndDateTime->lt(Carbon::parse($attendanceDate . ' ' . $shiftStartTime))) {
                    $shiftEndDateTime->addDay(); // Adjust for night shift
                }

                $existingAttendance = AttendanceEmployee::where('employee_id', $employee->id)
                    ->where('date', $attendanceDate)
                    ->first();

                $data = [
                    'machine_number' => $attendance['machineNumber'],
                    'status' => 'Present',
                ];

                // Map clock-in and clock-out based on status
                if (in_array($this->mapStatusToAttendance($attendance['status']), ['DUTY_ON'])) {
                    $data['clock_in'] = Carbon::parse($attendance['time'])->format('H:i:s');
                } elseif (in_array($this->mapStatusToAttendance($attendance['status']), ['DUTY_OFF'])) {
                    if ($existingAttendance) {
                        $data['clock_out'] = date('H:i:s', strtotime($attendance['time']));

                        // Handle cross-day shifts if the clock-out time is less than clock-in time
                        $clockInTime = strtotime( $existingAttendance->date .' '. $existingAttendance->clock_in);
                        $clockOutTime = strtotime($existingAttendance->date .' '.  $data['clock_out']);
                        if ($clockOutTime < $clockInTime) {
                            $clockOutTime = strtotime("+1 day " . $data['clock_out']);
                        }
                        $working = 0;
                        $shiftDuration = strtotime($shiftEndDateTime) - strtotime( date('Y-m-d H:i:s', strtotime($attendanceDate . ' ' . $shiftStartTime)));
                        if ( $clockInTime <  $clockOutTime  ) {
                            $working = $clockOutTime - $clockInTime;
                        }

                        // Calculate late, early leaving, and overtime
                        $data['late'] = $this->calculateLate($existingAttendance->clock_in, $shiftStartTime, $gracePeriod);
                        $data['early_leaving'] = $this->calculateEarlyLeaving($working, $shiftDuration);
                        $data['overtime'] = $this->calculateOvertime($working, $shiftDuration);
                    }
                }

                if (in_array($this->mapStatusToAttendance($attendance['status']), ['DUTY_ON', 'DUTY_OFF'])) {
                    // Update or create attendance entry
                    AttendanceEmployee::updateOrCreate(
                        [
                            'employee_id' => $employee->id,
                            'date' => $attendanceDate,
                        ],
                        $data
                    );
                }
            }

            return response()->json([
                'statusCode' => 200,
                'message' => 'Attendances marked successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'statusCode' => 500,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Map status numbers to attendance status.
     */
    private function mapStatusToAttendance(int $status): string
    {
        $statuses = [
            0 => 'DUTY_ON',
            1 => 'DUTY_OFF',
            2 => 'OVERTIME_BEGIN',
            3 => 'OVERTIME_END',
            4 => 'LOCK_OUT',
            5 => 'LOCK_IN',
        ];

        return $statuses[$status] ?? 'UNKNOWN';
    }

    /**
     * Get employee by enrollment number and company ID.
     */
    private function getEmployeeByEnrollmentNumberAndCompany(int $enrollmentNumber, int $companyId): Employee|null
    {
        $employee = Employee::where('biometric_emp_id', $enrollmentNumber)
            ->where('created_by', $companyId)
            ->first();

        // if (!$employee) {
        //     throw new \Exception("Employee with enrollment number {$enrollmentNumber} not found for company ID {$companyId}.");
        // }

        return $employee;
    }

    /**
     * Calculate late time.
     */
    private function calculateLate(string $clockIn, string $shiftStart, string $gracePeriod = '00:30:00'): string
    {
        $shiftStartTime = strtotime($shiftStart);
        $clockInTime = strtotime($clockIn);
        $graceSeconds = strtotime($gracePeriod) - strtotime('TODAY');

        if ($clockInTime > ($shiftStartTime + $graceSeconds)) {
            return gmdate('H:i:s', $clockInTime - $shiftStartTime);
        }

        return '00:00:00';
    }

    /**
     * Calculate early leaving time.
     */
    private function calculateEarlyLeaving($workingDuration, $shiftDuration)
    {
        return  $workingDuration < $shiftDuration   ?  gmdate('H:i:s', $shiftDuration - $workingDuration  )  :  '00:00:00';
    }

    /**
     * Calculate overtime.
     */
    private function calculateOvertime($workingDuration, $shiftDuration)
    {
        return  $workingDuration > $shiftDuration   ?  gmdate('H:i:s',  $workingDuration - $shiftDuration )  :  '00:00:00';
    }


    public function getValByName($key, $setting)
    {
        if (!isset($setting[$key]) || empty($setting[$key])) {
            $setting[$key] = '';
        }
        return $setting[$key];
    }
}
