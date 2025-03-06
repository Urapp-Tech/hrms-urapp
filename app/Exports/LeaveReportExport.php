<?php

namespace App\Exports;

use App\Models\Employee;
use App\Models\Leave;
use App\Models\LeaveType;
use App\Models\User;
use App\Models\Utility;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;

class LeaveReportExport implements FromCollection, WithHeadings, WithEvents
{
    /**
     * @return \Illuminate\Support\Collection
     */

     public function collection()
    {
        $data = [];
        // Get all leave types for the current creator
        $leaveTypes = LeaveType::where('created_by', Auth::user()->creatorId())->get();

        // Get all employees for the current creator
        $employees = Employee::where('created_by', Auth::user()->creatorId())->get();

        foreach ($employees as $employee) {
            // Determine the current leave cycle for this employee
            $date = Utility::AnnualLeaveCycle($employee);

            $row = [];
            $row['Employee ID']   = $employee->employee_id;
            $row['Employee Name'] = $employee->name;
            $row['Cycle Start']   = $date['start_date'];
            $row['Cycle End']     = $date['end_date'];

            // For each leave type, calculate the approved (availed) leave days within the cycle
            foreach ($leaveTypes as $leaveType) {
                $availed = Leave::where('employee_id', $employee->id)
                    ->where('status', 'Approved')
                    ->where('leave_type_id', $leaveType->id)
                    ->where(function($query) use ($date) {
                        $query->whereBetween('start_date', [$date['start_date'], $date['end_date']])
                              ->orWhereBetween('end_date', [$date['start_date'], $date['end_date']]);
                    })
                    ->sum('total_leave_days');

                $allowed = $leaveType->days;
                // Format as "availed/allowed" (for example, "5/10")
                $row[$leaveType->title] = $availed . '/' . $allowed;
            }
            $data[] = $row;
        }
        return collect($data);
    }

    public function headings(): array
    {
        $headings = ['Employee ID', 'Employee Name', 'Cycle Start', 'Cycle End'];
        // Append a heading for each leave type
        $leaveTypes = LeaveType::where('created_by', Auth::user()->creatorId())->get();
        foreach ($leaveTypes as $leaveType) {
            $headings[] = $leaveType->title;
        }
        return $headings;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                // Determine the last column letter dynamically
                $highestColumn = $event->sheet->getDelegate()->getHighestColumn();
                $cellRange = 'A1:' . $highestColumn . '1'; // Header row

                // Style the header row
                $event->sheet->getDelegate()->getStyle($cellRange)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['argb' => 'FFFFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FF538DD5'],
                    ],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    ]
                ]);
            },
        ];
    }


    public function collection_old()
    {
        $data    = Leave::where('created_by',\Auth::user()->creatorId())->get();
        $employees = Employee::where('created_by', \Auth::user()->creatorId())->get();
        // $employees = $employees->get();

        foreach ($employees as $employee) {

            $approved = Leave::where('employee_id', $employee->id)->where('status', 'Approved');
            $reject   = Leave::where('employee_id', $employee->id)->where('status', 'Reject');
            $pending  = Leave::where('employee_id', $employee->id)->where('status', 'Pending');
            $totalApproved = $totalReject = $totalPending = 0;

            $approved = $approved->count();
            $reject   = $reject->count();
            $pending  = $pending->count();

            $totalApproved += $approved;
            $totalReject   += $reject;
            $totalPending  += $pending;

            $employeeLeave['approved'] = $approved;
            $employeeLeave['reject']   = $reject;
            $employeeLeave['pending']  = $pending;


            $leaves[] = $employeeLeave;
        }
        foreach ($data as $k => $leave) {

            $user_id = $leave->employees->user_id;
            // $user_id = $leave->employees != null ? $leave->employees->user_id : 0;
            $user = User::where('id', $user_id)->first();
            $data[$k]["employee_id"] = !empty($leave->employees) ? User::employeeIdFormat($leave->employees->employee_id) : '';
            $data[$k]["employee"] = (!empty($leave->employees->name)) ? $leave->employees->name : '';
            $data[$k]["approved_leaves"] = $leaves[$k]['approved'] == 0 ? '0' : $leaves[$k]['approved'];
            $data[$k]["rejected_leaves"] = $leaves[$k]['reject'] == 0 ? '0' : $leaves[$k]['reject'];
            $data[$k]["pending_leaves"] = $leaves[$k]['pending'] == 0 ? '0' : $leaves[$k]['pending'];


            unset($data[$k]['id'], $data[$k]['leave_type_id'], $data[$k]['start_date'], $data[$k]['end_date'], $data[$k]['applied_on'], $data[$k]['total_leave_days'], $data[$k]['leave_reason'], $data[$k]['created_at'], $data[$k]['created_by'], $data[$k]['remark'], $data[$k]['status'], $data[$k]['updated_at'], $data[$k]['account_id']);
        }

        return $data;
    }


    public function headings_old(): array
    {
        return [
            "Employee ID",
            "Employee",
            "Approved Leaves ",
            "Rejected Leaves",
            "Pending Leaves",
        ];
    }
}
