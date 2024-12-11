<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceEmployee;
use App\Models\Employee;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    public function markAttendances(Request $request, $companyId)
    {
        // Validate the request
        $validated = $request->validate([
            '*.enrollmentNumber' => 'required|exists:employees,id',
            '*.machineNumber' => 'required|integer',
            '*.time' => 'required|date_format:Y-m-d\TH:i:s\Z',
            '*.status' => 'required|integer|min:0|max:5',
        ]);

        try {
            foreach ($validated as $attendance) {
                // Get employee by enrollment number and company
                $employee = $this->getEmployeeByEnrollmentNumberAndCompany($attendance['enrollmentNumber'], $companyId);

                // Get employee shift details
                $shift = Shift::find($employee->shift_id);
                if (!$shift) {
                    throw new \Exception("Shift not assigned for employee ID {$employee->id}.");
                }

                $shiftStartTime = $shift->start_time;
                $shiftEndTime = $shift->end_time;
                $isNightShift = strtotime($shiftStartTime) > strtotime($shiftEndTime);

                // Calculate the shift's start and end date-times
                $attendanceDate = date('Y-m-d', strtotime($attendance['time']));
                $shiftStartDateTime = date('Y-m-d H:i:s', strtotime($attendance['time'] . ' ' . $shiftStartTime));
                $shiftEndDateTime = $isNightShift
                    ? date('Y-m-d H:i:s', strtotime("+1 day " . $attendanceDate . ' ' . $shiftEndTime))
                    : date('Y-m-d H:i:s', strtotime($attendanceDate . ' ' . $shiftEndTime));

                // Attendance data initialization
                $existingAttendance = AttendanceEmployee::where('employee_id', $employee->id)
                    ->where('date', $attendanceDate)
                    ->first();

                $data = [
                    'machine_number' => $attendance['machineNumber'],
                    'status' => 'Present',
                ];

                if ($this->mapStatusToAttendance($attendance['status']) == 'DUTY_ON') {
                    // Clock-In (DUTY_ON)
                    $data['clock_in'] = date('H:i:s', strtotime($attendance['time']));
                } elseif ($this->mapStatusToAttendance($attendance['status']) == 'DUTY_OFF') {
                    // Clock-Out (DUTY_OFF)
                    if ($existingAttendance) {
                        $data['clock_out'] = date('H:i:s', strtotime($attendance['time']));

                        // Handle cross-day shifts if the clock-out time is less than clock-in time
                        $clockInTime = strtotime($existingAttendance->clock_in);
                        $clockOutTime = strtotime($data['clock_out']);
                        if ($clockOutTime < $clockInTime) {
                            $clockOutTime = strtotime("+1 day " . $data['clock_out']);
                        }
                        // dd($clockOutTime, gmdate('H:i:s', $clockOutTime), $shiftEndDateTime,$attendanceDate );
                        // Calculate late, early leaving, and overtime
                        $data['late'] = $this->calculateLate($existingAttendance->clock_in, $shiftStartTime, '00:15:00');
                        $data['early_leaving'] = $this->calculateEarlyLeaving($clockOutTime, strtotime($shiftEndDateTime));
                        $data['overtime'] = $this->calculateOvertime($clockOutTime, strtotime($shiftEndDateTime));
                    }
                }

                // Store attendance data
                AttendanceEmployee::updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'date' => $attendanceDate,
                    ],
                    $data
                );
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
    private function getEmployeeByEnrollmentNumberAndCompany(int $enrollmentNumber, int $companyId): Employee
    {
        $employee = Employee::where('id', $enrollmentNumber)
            ->where('created_by', $companyId)
            ->first();

        if (!$employee) {
            throw new \Exception("Employee with enrollment number {$enrollmentNumber} not found for company ID {$companyId}.");
        }

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
    private function calculateEarlyLeaving(int $clockOutTime, int $shiftEndTime): string
    {
        if ($clockOutTime < $shiftEndTime) {
            return gmdate('H:i:s', $shiftEndTime - $clockOutTime);
        }

        return '00:00:00';
    }

    /**
     * Calculate overtime.
     */
    private function calculateOvertime(int $clockOutTime, int $shiftEndTime): string
    {
        if ($clockOutTime > $shiftEndTime) {
            return gmdate('H:i:s', $clockOutTime - $shiftEndTime);
        }

        return '00:00:00';
    }
}
