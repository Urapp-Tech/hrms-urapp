<?php

namespace App\Http\Controllers;

use App\Exports\accountstatementExport;
use App\Exports\EmployeeAttendanceSheet;
use App\Exports\LeaveExport;
use App\Exports\LeaveReportExport;
use App\Exports\MultipleEmployeeAttendanceExport;
use App\Exports\PayrollExport;
use App\Exports\TimesheetExport;
use App\Exports\TimesheetReportExport;
use App\Models\AccountList;
use App\Models\AttendanceEmployee;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Deposit;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Holiday;
use App\Models\Leave;
use App\Models\LeaveType;
use App\Models\PaySlip;
use App\Models\Shift;
use App\Models\TimeSheet;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Utility;

class ReportController extends Controller
{

    public function incomeVsExpense(Request $request)
    {

        if (\Auth::user()->can('Manage Report')) {
            $deposit = Deposit::where('created_by', \Auth::user()->creatorId());

            $labels       = $data = [];
            $expenseCount = $incomeCount = 0;
            $incomeData = [];
            $expenseData = [];
            if (!empty($request->start_month) && !empty($request->end_month)) {

                $start = strtotime($request->start_month);
                $end   = strtotime($request->end_month);

                $currentdate = $start;
                $month       = [];
                while ($currentdate <= $end) {
                    $month = date('m', $currentdate);
                    $year  = date('Y', $currentdate);

                    $depositFilter = Deposit::where('created_by', \Auth::user()->creatorId())->whereMonth('date', $month)->whereYear('date', $year)->get();

                    $depositsTotal = 0;
                    foreach ($depositFilter as $deposit) {
                        $depositsTotal += $deposit->amount;
                    }

                    $incomeData[] = $depositsTotal;
                    $incomeCount  += $depositsTotal;

                    $expenseFilter = Expense::where('created_by', \Auth::user()->creatorId())->whereMonth('date', $month)->whereYear('date', $year)->get();
                    $expenseTotal  = 0;
                    foreach ($expenseFilter as $expense) {
                        $expenseTotal += $expense->amount;
                    }
                    $expenseData[] = $expenseTotal;
                    $expenseCount  += $expenseTotal;

                    $labels[]    = date('M Y', $currentdate);
                    $currentdate = strtotime('+1 month', $currentdate);
                }

                $filter['startDateRange'] = date('M-Y', strtotime($request->start_month));
                $filter['endDateRange']   = date('M-Y', strtotime($request->end_month));
            } else {
                for ($i = 0; $i < 6; $i++) {

                    $month = date('m', strtotime("-$i month"));
                    $year  = date('Y', strtotime("-$i month"));

                    $depositFilter = Deposit::where('created_by', \Auth::user()->creatorId())->whereMonth('date', $month)->whereYear('date', $year)->get();

                    $depositTotal = 0;
                    foreach ($depositFilter as $deposit) {
                        $depositTotal += $deposit->amount;
                    }

                    $incomeData[] = $depositTotal;
                    $incomeCount  += $depositTotal;

                    $expenseFilter = Expense::where('created_by', \Auth::user()->creatorId())->whereMonth('date', $month)->whereYear('date', $year)->get();
                    $expenseTotal  = 0;
                    foreach ($expenseFilter as $expense) {
                        $expenseTotal += $expense->amount;
                    }
                    $expenseData[] = $expenseTotal;
                    $expenseCount  += $expenseTotal;

                    $labels[] = date('M Y', strtotime("-$i month"));
                }
                $filter['startDateRange'] = date('M-Y');
                $filter['endDateRange']   = date('M-Y', strtotime("-5 month"));
            }

            $incomeArr['name'] = __('Income');
            $incomeArr['data'] = $incomeData;

            $expenseArr['name'] = __('Expense');
            $expenseArr['data'] = $expenseData;

            $data[] = $incomeArr;
            $data[] = $expenseArr;



            return view('report.income_expense', compact('labels', 'data', 'incomeCount', 'expenseCount', 'filter'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function leave(Request $request)
    {
        if (\Auth::user()->can('Manage Report')) {

            $branch = Branch::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $branch->prepend('All', '');

            $department = Department::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $department->prepend('All', '');

            $filterYear['branch']        = __('All');
            $filterYear['department']    = __('All');
            $filterYear['type']          = __('Monthly');
            $filterYear['dateYearRange'] = date('M-Y');
            $employees                   = Employee::where('created_by', \Auth::user()->creatorId());
            if (!empty($request->branch)) {
                $employees->where('branch_id', $request->branch);
                $filterYear['branch'] = !empty(Branch::find($request->branch)) ? Branch::find($request->branch)->name : '';
            }
            if (!empty($request->department)) {
                $employees->where('department_id', $request->department);
                $filterYear['department'] = !empty(Department::find($request->department)) ? Department::find($request->department)->name : '';
            }

            $employees = $employees->get();

            $leaves        = [];
            $totalApproved = $totalReject = $totalPending = 0;
            foreach ($employees as $employee) {

                $employeeLeave['id']          = $employee->id;
                $employeeLeave['employee_id'] = $employee->employee_id;
                $employeeLeave['employee']    = $employee->name;

                $approved = Leave::where('employee_id', $employee->id)->where('status', 'Approved');
                $reject   = Leave::where('employee_id', $employee->id)->where('status', 'Reject');
                $pending  = Leave::where('employee_id', $employee->id)->where('status', 'Pending');

                if ($request->type == 'monthly' && !empty($request->month)) {
                    $month = date('m', strtotime($request->month));
                    $year  = date('Y', strtotime($request->month));

                    $approved->whereMonth('applied_on', $month)->whereYear('applied_on', $year);
                    $reject->whereMonth('applied_on', $month)->whereYear('applied_on', $year);
                    $pending->whereMonth('applied_on', $month)->whereYear('applied_on', $year);

                    $filterYear['dateYearRange'] = date('M-Y', strtotime($request->month));
                    $filterYear['type']          = __('Monthly');
                } elseif (!isset($request->type)) {
                    $month     = date('m');
                    $year      = date('Y');
                    $monthYear = date('Y-m');

                    $approved->whereMonth('applied_on', $month)->whereYear('applied_on', $year);
                    $reject->whereMonth('applied_on', $month)->whereYear('applied_on', $year);
                    $pending->whereMonth('applied_on', $month)->whereYear('applied_on', $year);

                    $filterYear['dateYearRange'] = date('M-Y', strtotime($monthYear));
                    $filterYear['type']          = __('Monthly');
                }

                if ($request->type == 'yearly' && !empty($request->year)) {
                    $approved->whereYear('applied_on', $request->year);
                    $reject->whereYear('applied_on', $request->year);
                    $pending->whereYear('applied_on', $request->year);


                    $filterYear['dateYearRange'] = $request->year;
                    $filterYear['type']          = __('Yearly');
                }

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

            $starting_year = date('Y', strtotime('-5 year'));
            $ending_year   = date('Y', strtotime('+5 year'));

            $filterYear['starting_year'] = $starting_year;
            $filterYear['ending_year']   = $ending_year;

            $filter['totalApproved'] = $totalApproved;
            $filter['totalReject']   = $totalReject;
            $filter['totalPending']  = $totalPending;

            return view('report.leave', compact('department', 'branch', 'leaves', 'filterYear', 'filter'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function employeeLeave(Request $request, $employee_id, $status, $type, $month, $year)
    {
        if (\Auth::user()->can('Manage Report')) {
            $leaveTypes = LeaveType::where('created_by', \Auth::user()->creatorId())->get();
            $leaves     = [];
            foreach ($leaveTypes as $leaveType) {
                $leave        = new Leave();
                $leave->title = $leaveType->title;
                $totalLeave   = Leave::where('employee_id', $employee_id)->where('status', $status)->where('leave_type_id', $leaveType->id);
                if ($type == 'yearly') {
                    $totalLeave->whereYear('applied_on', $year);
                } else {
                    $m = date('m', strtotime($month));
                    $y = date('Y', strtotime($month));

                    $totalLeave->whereMonth('applied_on', $m)->whereYear('applied_on', $y);
                }
                $totalLeave = $totalLeave->count();

                $leave->total = $totalLeave;
                $leaves[]     = $leave;
            }

            $leaveData = Leave::where('employee_id', $employee_id)->where('status', $status);
            if ($type == 'yearly') {
                $leaveData->whereYear('applied_on', $year);
            } else {
                $m = date('m', strtotime($month));
                $y = date('Y', strtotime($month));

                $leaveData->whereMonth('applied_on', $m)->whereYear('applied_on', $y);
            }

            $leaveData = $leaveData->get();


            return view('report.leaveShow', compact('leaves', 'leaveData'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function accountStatement(Request $request)
    {
        if (\Auth::user()->can('Manage Report')) {
            $accountList = AccountList::where('created_by', \Auth::user()->creatorId())->get()->pluck('account_name', 'id');
            $accountList->prepend('All', '');

            $filterYear['account'] = __('All');
            $filterYear['type']    = __('Income');


            if ($request->type == 'expense') {
                $accountData = Expense::orderBy('id');
                $accounts    = Expense::select('account_lists.id', 'account_lists.account_name')->leftjoin('account_lists', 'expenses.account_id', '=', 'account_lists.id')->groupBy('expenses.account_id')->selectRaw('sum(amount) as total');

                if (!empty($request->start_month) && !empty($request->end_month)) {
                    $start = strtotime($request->start_month);
                    $end   = strtotime($request->end_month);
                } else {
                    $start = strtotime(date('Y-m'));
                    $end   = strtotime(date('Y-m', strtotime("-5 month")));
                }

                $currentdate = $start;

                while ($currentdate <= $end) {
                    $data['month'] = date('m', $currentdate);
                    $data['year']  = date('Y', $currentdate);

                    $accountData->Orwhere(
                        function ($query) use ($data) {
                            $query->whereMonth('date', $data['month'])->whereYear('date', $data['year']);
                        }
                    );

                    $accounts->Orwhere(
                        function ($query) use ($data) {
                            $query->whereMonth('date', $data['month'])->whereYear('date', $data['year']);
                        }
                    );

                    $currentdate = strtotime('+1 month', $currentdate);
                }

                $filterYear['startDateRange'] = date('M-Y', $start);
                $filterYear['endDateRange']   = date('M-Y', $end);

                if (!empty($request->account)) {
                    $accountData->where('account_id', $request->account);
                    $accounts->where('account_lists.id', $request->account);

                    $filterYear['account'] = !empty(AccountList::find($request->account)) ? Department::find($request->account)->account_name : '';
                }

                $accounts->where('expenses.created_by', \Auth::user()->creatorId());

                $filterYear['type'] = __('Expense');
            } else {
                $accountData = Deposit::orderBy('id');
                $accounts    = Deposit::select('account_lists.id', 'account_lists.account_name')->leftjoin('account_lists', 'deposits.account_id', '=', 'account_lists.id')->groupBy('deposits.account_id')->selectRaw('sum(amount) as total');

                if (!empty($request->start_month) && !empty($request->end_month)) {

                    $start = strtotime($request->start_month);
                    $end   = strtotime($request->end_month);
                } else {
                    $start = strtotime(date('Y-m'));
                    $end   = strtotime(date('Y-m', strtotime("-5 month")));
                }

                $currentdate = $start;

                while ($currentdate <= $end) {
                    $data['month'] = date('m', $currentdate);
                    $data['year']  = date('Y', $currentdate);

                    $accountData->Orwhere(
                        function ($query) use ($data) {
                            $query->whereMonth('date', $data['month'])->whereYear('date', $data['year']);
                        }
                    );
                    $currentdate = strtotime('+1 month', $currentdate);

                    $accounts->Orwhere(
                        function ($query) use ($data) {
                            $query->whereMonth('date', $data['month'])->whereYear('date', $data['year']);
                        }
                    );
                    $currentdate = strtotime('+1 month', $currentdate);
                }

                $filterYear['startDateRange'] = date('M-Y', $start);
                $filterYear['endDateRange']   = date('M-Y', $end);

                if (!empty($request->account)) {
                    $accountData->where('account_id', $request->account);
                    $accounts->where('account_lists.id', $request->account);

                    $filterYear['account'] = !empty(AccountList::find($request->account)) ? Department::find($request->account)->account_name : '';
                }
                $accounts->where('deposits.created_by', \Auth::user()->creatorId());
            }

            $accountData->where('created_by', \Auth::user()->creatorId());
            $accountData = $accountData->get();

            $accounts = $accounts->get();


            return view('report.account_statement', compact('accountData', 'accountList', 'accounts', 'filterYear'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function payroll(Request $request)
    {
        if (\Auth::user()->can('Manage Report')) {
            $branch = Branch::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $branch->prepend('All', '');

            $department = Department::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $department->prepend('All', '');

            $filterYear['branch']     = __('All');
            $filterYear['department'] = __('All');
            $filterYear['type']       = __('Monthly');

            $payslips = PaySlip::select('pay_slips.*', 'employees.name')->leftjoin('employees', 'pay_slips.employee_id', '=', 'employees.id')->where('pay_slips.created_by', \Auth::user()->creatorId());


            if ($request->type == 'monthly' && !empty($request->month)) {

                $payslips->where('salary_month', $request->month);

                $filterYear['dateYearRange'] = date('M-Y', strtotime($request->month));
                $filterYear['type']          = __('Monthly');
            } elseif (!isset($request->type)) {
                $month = date('Y-m');

                $payslips->where('salary_month', $month);

                $filterYear['dateYearRange'] = date('M-Y', strtotime($month));
                $filterYear['type']          = __('Monthly');
            }

            if ($request->type == 'yearly' && !empty($request->year)) {
                $startMonth = $request->year . '-01';
                $endMonth   = $request->year . '-12';
                $payslips->where('salary_month', '>=', $startMonth)->where('salary_month', '<=', $endMonth);

                $filterYear['dateYearRange'] = $request->year;
                $filterYear['type']          = __('Yearly');
            }

            if (!empty($request->branch)) {
                $payslips->where('employees.branch_id', $request->branch);

                $filterYear['branch'] = !empty(Branch::find($request->branch)) ? Branch::find($request->branch)->name : '';
            }

            if (!empty($request->department)) {
                $payslips->where('employees.department_id', $request->department);

                $filterYear['department'] = !empty(Department::find($request->department)) ? Department::find($request->department)->name : '';
            }

            $payslips = $payslips->get();

            $totalBasicSalary = $totalNetSalary = $totalAllowance = $totalCommision = $totalLoan = $totalSaturationDeduction = $totalOtherPayment = $totalOverTime = 0;

            foreach ($payslips as $payslip) {
                $totalBasicSalary += $payslip->basic_salary;
                $totalNetSalary   += $payslip->net_payble;

                $allowances = json_decode($payslip->allowance);
                foreach ($allowances as $allowance) {
                    $totalAllowance += $allowance->amount;
                }

                $commisions = json_decode($payslip->commission);
                foreach ($commisions as $commision) {
                    $totalCommision += $commision->amount;
                }

                $loans = json_decode($payslip->loan);
                foreach ($loans as $loan) {
                    $totalLoan += $loan->amount;
                }

                $saturationDeductions = json_decode($payslip->saturation_deduction);
                foreach ($saturationDeductions as $saturationDeduction) {
                    $totalSaturationDeduction += $saturationDeduction->amount;
                }

                $otherPayments = json_decode($payslip->other_payment);
                foreach ($otherPayments as $otherPayment) {
                    $totalOtherPayment += $otherPayment->amount;
                }

                $overtimes = json_decode($payslip->overtime);
                foreach ($overtimes as $overtime) {
                    $days  = $overtime->number_of_days;
                    $hours = $overtime->hours;
                    $rate  = $overtime->rate;

                    $totalOverTime += ($rate * $hours) * $days;
                }
            }

            $filterData['totalBasicSalary']         = $totalBasicSalary;
            $filterData['totalNetSalary']           = $totalNetSalary;
            $filterData['totalAllowance']           = $totalAllowance;
            $filterData['totalCommision']           = $totalCommision;
            $filterData['totalLoan']                = $totalLoan;
            $filterData['totalSaturationDeduction'] = $totalSaturationDeduction;
            $filterData['totalOtherPayment']        = $totalOtherPayment;
            $filterData['totalOverTime']            = $totalOverTime;


            $starting_year = date('Y', strtotime('-5 year'));
            $ending_year   = date('Y', strtotime('+5 year'));

            $filterYear['starting_year'] = $starting_year;
            $filterYear['ending_year']   = $ending_year;

            return view('report.payroll', compact('payslips', 'filterData', 'branch', 'department', 'filterYear'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function monthlyAttendance(Request $request)
    {
        if (\Auth::user()->can('Manage Report')) {

            $branch = Branch::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $branch->prepend('All', '');

            $department = Department::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $department->prepend('All', '');

            $data['branch']     = __('All');
            $data['department'] = __('All');

            $employees = Employee::select('id', 'name');
            if (!empty($request->employee_id) && $request->employee_id[0] != 0) {
                $employees->whereIn('id', $request->employee_id);
            }

            $employees = $employees->where('created_by', \Auth::user()->creatorId());

            if (!empty($request->branch)) {
                $employees->where('branch_id', $request->branch);
                $data['branch'] = !empty(Branch::find($request->branch)) ? Branch::find($request->branch)->name : '';
            }

            if (!empty($request->department)) {
                $employees->where('department_id', $request->department);
                $data['department'] = !empty(Department::find($request->department)) ? Department::find($request->department)->name : '';
            }

            if (!empty($request->employees)) {
                $employees->where('employee_id', $request->employees);
                $data['employees'] = !empty(Employee::find($request->employees)) ? Employee::find($request->employees)->name : '';
            }

            $employeesCollection = $employees->get();
            $employees = $employeesCollection->pluck('name', 'id');


            if (!empty($request->month)) {
                $currentdate = strtotime($request->month);
                $month       = date('m', $currentdate);
                $year        = date('Y', $currentdate);
                $curMonth    = date('M-Y', strtotime($request->month));
            } else {
                $month    = date('m');
                $year     = date('Y');
                $curMonth = date('M-Y', strtotime($year . '-' . $month));
            }
            $leaves = Leave::whereIn('employee_id', $employeesCollection->pluck('id'))->whereMonth('start_date',$month)->orWhereMonth('end_date',$month)->get();
            $holidays = Holiday::with('employees')->whereMonth('start_date',$month)->orWhereMonth('end_date', $month)->get();

            //            $num_of_days = cal_days_in_month(CAL_GREGORIAN, $month, $year);
            $num_of_days = date('t', mktime(0, 0, 0, $month, 1, $year));
            for ($i = 1; $i <= $num_of_days; $i++) {
                $dates[] = str_pad($i, 2, '0', STR_PAD_LEFT);
            }

            $employeesAttendance = [];
            $totalPresent        = $totalLeave = $totalHoliday = $totalEarlyLeave = 0;
            $ovetimeHours        = $overtimeMins = $earlyleaveHours = $earlyleaveMins = $lateHours = $lateMins = 0;
            foreach ($employees as $id => $employee) {
                $attendances['name'] = $employee;

                foreach ($dates as $date) {
                    $dateFormat = $year . '-' . $month . '-' . $date;
                    $dayOfWeek = date('w', strtotime($dateFormat));
                    $employee_leaves =  $leaves->where('employee_id', $id)->where('status', 'Approved');
                    $employee_on_leave = $employee_leaves->filter(function ($item) use ($dateFormat) {
                        $targetDate = Carbon::parse($dateFormat);
                        $startDate = Carbon::parse($item['start_date']);
                        $endDate = Carbon::parse($item['end_date']);
                        return $targetDate->between($startDate, $endDate);
                    });

                    // Filter holidays specific to the employee
                    $employee_holidays = $holidays->filter(function ($holiday) use ($id) {
                        return $holiday->employees->contains('id', $id);
                    });

                    $holiday = $employee_holidays->filter(function ($item) use ($dateFormat) {
                        $targetDate = Carbon::parse($dateFormat);
                        $startDate = Carbon::parse($item['start_date']);
                        $endDate = Carbon::parse($item['end_date']);
                        return $targetDate->between($startDate, $endDate);
                    });



                    // dd($employee_on_leave, $id, $date);
                    if ($dateFormat <= date('Y-m-d')) {
                        $employeeAttendance = AttendanceEmployee::where('employee_id', $id)->where('date', $dateFormat)->first();

                        if (!empty($employeeAttendance) && $employeeAttendance->status == 'Present') {
                            $attendanceStatus[$date] = 'P';
                            $totalPresent            += 1;

                            if ($employeeAttendance->overtime > 0) {
                                $ovetimeHours += date('h', strtotime($employeeAttendance->overtime));
                                $overtimeMins += date('i', strtotime($employeeAttendance->overtime));
                            }

                            if ($employeeAttendance->early_leaving > 0) {
                                $earlyleaveHours += date('h', strtotime($employeeAttendance->early_leaving));
                                $earlyleaveMins  += date('i', strtotime($employeeAttendance->early_leaving));
                            }

                            if ($employeeAttendance->late > 0) {
                                $lateHours += date('h', strtotime($employeeAttendance->late));
                                $lateMins  += date('i', strtotime($employeeAttendance->late));
                            }
                        } elseif (!empty($employee_on_leave) && $employee_on_leave->count() > 0 &&  ($dayOfWeek != 0 && $dayOfWeek != 6) ) {
                            $attendanceStatus[$date] = 'L';
                            $totalLeave              += 1;
                        } elseif ((!empty($holiday) && $holiday->count() > 0) ) {
                            $attendanceStatus[$date] = 'H';
                            $totalHoliday              += 1;
                        } elseif (!empty($employeeAttendance) && $employeeAttendance->status == 'Leave') {
                            $attendanceStatus[$date] = 'A';
                            $totalLeave              += 1;
                        } else if (empty($employeeAttendance) && ($dayOfWeek != 0 && $dayOfWeek != 6) ) {
                            $attendanceStatus[$date] = 'A';
                        } else {
                            $attendanceStatus[$date] = '';
                        }
                    } else {
                        $attendanceStatus[$date] = '';
                    }
                }
                $attendances['status'] = $attendanceStatus;
                $employeesAttendance[] = $attendances;
            }

            $totalOverTime   = $ovetimeHours + ($overtimeMins / 60);
            $totalEarlyleave = $earlyleaveHours + ($earlyleaveMins / 60);
            $totalLate       = $lateHours + ($lateMins / 60);

            $data['totalOvertime']   = $totalOverTime;
            $data['totalEarlyLeave'] = $totalEarlyleave;
            $data['totalLate']       = $totalLate;
            $data['totalPresent']    = $totalPresent;
            $data['totalLeave']      = $totalLeave;
            $data['curMonth']        = $curMonth;

            return view('report.monthlyAttendance', compact('employeesAttendance', 'branch', 'department', 'employees', 'dates', 'data'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function timesheet(Request $request)
    {
        if (\Auth::user()->can('Manage Report')) {
            $branch = Branch::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $branch->prepend('All', '');

            $department = Department::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $department->prepend('All', '');

            $filterYear['branch']     = __('All');
            $filterYear['department'] = __('All');

            $timesheets       = TimeSheet::select('time_sheets.*', 'employees.name')->leftjoin('employees', 'time_sheets.employee_id', '=', 'employees.id')->where('time_sheets.created_by', \Auth::user()->creatorId());
            $timesheetFilters = TimeSheet::select('time_sheets.*', 'employees.name')->groupBy('employee_id')->selectRaw('sum(hours) as total')->leftjoin('employees', 'time_sheets.employee_id', '=', 'employees.id')->where('time_sheets.created_by', \Auth::user()->creatorId());

            if (!empty($request->start_date) && !empty($request->end_date)) {
                $timesheets->where('date', '>=', $request->start_date);
                $timesheets->where('date', '<=', $request->end_date);

                $timesheetFilters->where('date', '>=', $request->start_date);
                $timesheetFilters->where('date', '<=', $request->end_date);

                $filterYear['start_date'] = $request->start_date;
                $filterYear['end_date']   = $request->end_date;
            } else {

                $filterYear['start_date'] = date('Y-m-01');
                $filterYear['end_date']   = date('Y-m-t');

                $timesheets->where('date', '>=', $filterYear['start_date']);
                $timesheets->where('date', '<=', $filterYear['end_date']);

                $timesheetFilters->where('date', '>=', $filterYear['start_date']);
                $timesheetFilters->where('date', '<=', $filterYear['end_date']);
            }

            if (!empty($request->branch)) {
                $timesheets->where('branch_id', $request->branch);
                $timesheetFilters->where('branch_id', $request->branch);
                $filterYear['branch'] = !empty(Branch::find($request->branch)) ? Branch::find($request->branch)->name : '';
            }
            if (!empty($request->department)) {
                $timesheets->where('department_id', $request->department);
                $timesheetFilters->where('department_id', $request->department);
                $filterYear['department'] = !empty(Department::find($request->department)) ? Department::find($request->department)->name : '';
            }

            $timesheets = $timesheets->get();

            $timesheetFilters = $timesheetFilters->get();

            $totalHours = 0;
            foreach ($timesheetFilters as $timesheetFilter) {
                $totalHours += $timesheetFilter->hours;
            }
            $filterYear['totalHours']    = $totalHours;
            $filterYear['totalEmployee'] = count($timesheetFilters);

            return view('report.timesheet', compact('timesheets', 'branch', 'department', 'filterYear', 'timesheetFilters'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function LeaveReportExport()
    {
        $name = 'leave_' . date('Y-m-d i:h:s');
        $data = \Excel::download(new LeaveReportExport(), $name . '.xlsx');

        return $data;
    }

    public function AccountStatementReportExport(Request $request)
    {
        $name = 'Account Statement_' . date('Y-m-d i:h:s');
        $data = \Excel::download(new accountstatementExport(), $name . '.xlsx');

        return $data;
    }

    public function PayrollReportExport($month, $branch, $department)
    {
        $data = [];
        $data['branch'] = __('All');
        $data['department'] = __('All');

        if ($branch != 0) {
            $data['branch'] = !empty(Branch::find($branch)) ? Branch::find($branch)->id : '';
        }

        if ($department != 0) {
            $data['department'] = !empty(Department::find($department)) ? Department::find($department)->id : '';
        }
        $data['month'] = $month;
        $name = 'Payroll_' . date('Y-m-d i:h:s');
        $data = \Excel::download(new PayrollExport($data), $name . '.xlsx');

        return $data;
    }

    public function exportTimeshhetReport(Request $request)
    {
        $name = 'Timesheet_' . date('Y-m-d i:h:s');
        $data = \Excel::download(new TimesheetReportExport(), $name . '.xlsx');

        return $data;
    }

    public function exportCsv($filter_month, $branch, $department, $employee)
    {
        $data['branch'] = __('All');
        $data['department'] = __('All');

        $employees = Employee::select('id', 'name')->where('created_by', \Auth::user()->creatorId());
        if ($branch != 0) {
            $employees->where('branch_id', $branch);
            $data['branch'] = !empty(Branch::find($branch)) ? Branch::find($branch)->name : '';
        }

        if ($department != 0) {
            $employees->where('department_id', $department);
            $data['department'] = !empty(Department::find($department)) ? Department::find($department)->name : '';
        }
        if ($employee != 0) {
            $employeeIds = explode(',', $employee);
            $emp = Employee::whereIn('id', $employeeIds);
        } else {
            $emp = Employee::where('created_by', \Auth::user()->creatorId());
        }

        $employees = $emp->get()->pluck('name', 'id');

        $currentdate = strtotime($filter_month);
        $month       = date('m', $currentdate);
        $year        = date('Y', $currentdate);
        $data['curMonth']    = date('M-Y', strtotime($filter_month));


        $fileName = $data['branch'] . ' ' . __('Branch') . ' ' . $data['curMonth'] . ' ' . __('Attendance Report of') . ' ' . $data['department'] . ' ' . __('Department') . ' ' . '.csv';

        $employeesAttendance = [];
        $num_of_days = date('t', mktime(0, 0, 0, $month, 1, $year));
        for ($i = 1; $i <= $num_of_days; $i++) {
            $dates[] = str_pad($i, 2, '0', STR_PAD_LEFT);
        }

        foreach ($employees as $id => $employee) {
            $attendances['name'] = $employee;

            foreach ($dates as $date) {
                $dateFormat = $year . '-' . $month . '-' . $date;

                if ($dateFormat <= date('Y-m-d')) {
                    $employeeAttendance = AttendanceEmployee::where('employee_id', $id)->where('date', $dateFormat)->first();

                    if (!empty($employeeAttendance) && $employeeAttendance->status == 'Present') {
                        $attendanceStatus[$date] = 'P';
                    } elseif (!empty($employeeAttendance) && $employeeAttendance->status == 'Leave') {
                        $attendanceStatus[$date] = 'A';
                    } else {
                        $attendanceStatus[$date] = '-';
                    }
                } else {
                    $attendanceStatus[$date] = '-';
                }
                $attendances[$date] = $attendanceStatus[$date];
            }

            $employeesAttendance[] = $attendances;
        }

        $headers = array(
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0",
        );
        $emp = array(
            'employee',
        );

        $columns = array_merge($emp, $dates);

        $callback = function () use ($employeesAttendance, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($employeesAttendance as $attendance) {
                fputcsv($file, str_replace('"', '', array_values($attendance)));
            }


            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function getdepartment(Request $request)
    {
        if ($request->branch_id == 0) {
            $departments = Department::where('created_by', '=', Auth::user()->creatorId())->get()->pluck('name', 'id')->toArray();
        } else {
            $departments = Department::where('created_by', '=', Auth::user()->creatorId())->where('branch_id', $request->branch_id)->get()->pluck('name', 'id')->toArray();
        }
        return response()->json($departments);
    }

    public function getemployee(Request $request)
    {
        if (!$request->department_id) {
            $employees = Employee::where('created_by', '=', \Auth::user()->creatorId())->get()->pluck('name', 'id')->toArray();
        } else {
            $employees = Employee::where('created_by', '=', \Auth::user()->creatorId())->where('department_id', $request->department_id)->get()->pluck('name', 'id')->toArray();
        }

        return response()->json($employees);
    }

    public function exportAttendance(Request $request) {
        if (\Auth::user()->can('Manage Report')) {

            $branch = Branch::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $branch->prepend('All', '');

            $department = Department::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $department->prepend('All', '');
            return view('report.attendanceTimesheet', compact( 'branch', 'department'));

        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function exportMonthlyAttendance(Request $request)
    {
        if (\Auth::user()->can('Manage Report')) {
            // Fetch employees
            $employees = Employee::where('created_by', \Auth::user()->creatorId());

            if (!empty($request->branch)) {
                $employees->where('branch_id', $request->branch);
            }

            if (!empty($request->department)) {
                $employees->where('department_id', $request->department);
            }

            $employees = $employees->get();

            if (!empty($request->month)) {
                $currentdate = strtotime($request->month);
                $month       = date('m', $currentdate);
                $year        = date('Y', $currentdate);
            } else {
                $month = date('m');
                // $month = 11;
                $year  = date('Y');
            }

            // Grace period (in seconds, 30 minutes)
            $gracePeriod = Utility::getValByName('company_grace_time');
            if ($gracePeriod == '' ) {
                $gracePeriod = '00:00:00';
            }

            // Prepare data for each employee
            $attendanceSheets = [];
            $leaves = Leave::whereIn('employee_id', $employees->pluck('id'))->whereMonth('start_date',$month)->orWhereMonth('end_date',$month)->get();
            $holidays = Holiday::with('employees')->whereMonth('start_date',$month)->orWhereMonth('end_date', $month)->get();
            // dd($holidays);

            foreach ($employees as $employee) {
                $shift = Shift::find($employee->shift_id);
                if (!$shift) {
                    continue; // Skip if shift not found
                }

                $shiftStartTime = $shift->start_time;
                $shiftEndTime = $shift->end_time;
                $isNightShift = strtotime($shiftStartTime) > strtotime($shiftEndTime);

                $attendances = AttendanceEmployee::where('employee_id', $employee->id)
                    ->whereMonth('date', $month)
                    ->whereYear('date', $year)
                    ->where('status', '<>', 'Leave')
                    ->get();

                $attendanceData = [];
                $num_of_days = date('t', mktime(0, 0, 0, $month, 1, $year));

                $totalLate = $totalShort = $totalOvertime = $totalHalfDays = $totalAbsent = $totalWorkingHours = 0;

                for ($day = 1; $day <= $num_of_days; $day++) {
                    $date = $year . '-' . $month . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
                    $attendance = $attendances->where('date', $date)->first();
                    $metrics = [
                        'late' => '0:00:00',
                        'early_leaving' => '0:00:00',
                        'overtime' => '0:00:00',
                    ];
                    $status = 'Present';
                    $late = $earlyLeaving = $overtime = '00:00:00';
                    $employee_leaves =  $leaves->where('employee_id', $employee->id)->where('status', 'Approved');

                    $employee_on_leave = $employee_leaves->filter(function ($item) use ($date) {
                        $targetDate = Carbon::parse($date);
                        $startDate = Carbon::parse($item['start_date']);
                        $endDate = Carbon::parse($item['end_date']);
                        return $targetDate->between($startDate, $endDate);
                    });

                    // Filter holidays specific to the employee
                    $employee_holidays = $holidays->filter(function ($holiday) use ($employee) {
                        return $holiday->employees->contains('id', $employee->id);
                    });

                    $holiday = $employee_holidays->filter(function ($item) use ($date) {
                        $targetDate = Carbon::parse($date);
                        $startDate = Carbon::parse($item['start_date']);
                        $endDate = Carbon::parse($item['end_date']);
                        return $targetDate->between($startDate, $endDate);
                    });

                    $working = 0;
                    $dayOfWeek = date('w', strtotime($date));

                    if ($employee_on_leave && $employee_on_leave->count() > 0 && ($dayOfWeek != 0 && $dayOfWeek != 6)) {
                        $status = 'Leave';
                    }
                    else if ($holiday && $holiday->count() > 0) {
                        $status = 'Holiday';
                    }
                    else if (empty($attendance)) {
                        // Check if the day is a weekend (Saturday or Sunday)
                        if ($dayOfWeek == 0 || $dayOfWeek == 6) {
                            $status = 'Holiday';
                        } else if (  !empty($holiday) && $holiday->count() > 0 ) {
                            $status = 'Holiday';
                        } else if (  !empty($employee_on_leave) && $employee_on_leave->count() > 0 ) {
                            $status = 'Leave';
                        }
                         else {
                            $status = 'Absent';
                            $totalAbsent++;
                        }
                    } else {

                        $metrics = $this->calculateAttendanceMetrics($attendance, $shift, $gracePeriod );
                        $clockOutDateTime = date('Y-m-d H:i:s', strtotime($attendance->date . ' ' .  $attendance->clock_out));

                        if (strtotime( $attendance->clock_out) < strtotime($attendance->clock_in)) {
                            $clockOutDateTime = date('Y-m-d H:i:s', strtotime("+1 day " . $attendance->date . ' ' .  $attendance->clock_out));
                        }

                        // Calculate late
                        $clockInTime = strtotime($attendance->clock_in);
                        $clockOutTime = strtotime($attendance->clock_out);

                        // Calculate overtime
                        $overtimeSeconds = strtotime($metrics['overtime']) ;
                        if(!(in_array($metrics['overtime'], ['00:00:00', '0:00:00']) || strtotime($metrics['overtime']) == 0)) {
                            $status .= $status ? ', Overtime' : 'Overtime';
                            $totalShortSeconds = !in_array($metrics['early_leaving'], ['00:00:00', '0:00:00']) ? $this->convertTimeToSeconds($metrics['early_leaving']) : 0;
                            $totalOverTimeSeconds = $this->convertTimeToSeconds($metrics['overtime']);
                            if ($totalOverTimeSeconds > $totalShortSeconds) {
                                $actualOverTimeSeconds = $totalOverTimeSeconds - $totalShortSeconds;
                                $metrics['overtime'] = gmdate('H:i:s', $actualOverTimeSeconds);
                                $totalOvertime = $this->addTimes($metrics['overtime'], $totalOvertime == 0 ? '00:00:00' : $totalOvertime);
                            }

                        }

                        // Calculate half day
                        if(!(in_array($metrics['early_leaving'], ['00:00:00', '0:00:00']) || strtotime($metrics['early_leaving']) == 0)) {
                            $totalShortSeconds = $this->convertTimeToSeconds($metrics['early_leaving']);
                            if ($totalShortSeconds >  1800 && $totalShortSeconds <= 10800 ) {
                                $status .= $status ? ', Half Day' : 'Half Day';
                                $totalHalfDays++;
                            }
                            $totalShort += $totalShortSeconds;
                        }

                        // Calculate Working Hours
                        if ( strtotime($clockInTime) <  strtotime($clockOutDateTime)  ) {
                            $working = strtotime($clockOutDateTime) - $clockInTime;
                            $totalWorkingHours = $this->addTimes( gmdate('H:i:s', $working),  $totalWorkingHours == 0 ? '00:00:00' : $totalWorkingHours);
                        }



                        if(!(in_array($metrics['late'], ['00:00:00', '0:00:00']) || strtotime($metrics['late']) == 0)) {
                            $totalLate += 1;
                        }
                    }

                    $attendanceData[] = [
                        'Date' => $date,
                        'Time In' => !empty($attendance) ? date('h:i A', strtotime($attendance->clock_in)) : 'N/A',
                        'Time Out' => !empty($attendance) ? date('h:i A', strtotime($attendance->clock_out)) : 'N/A',
                        'Late' => $metrics['late'],
                        'Short' => $metrics['early_leaving'],
                        'Working Hours' => gmdate('H:i:s', $working) ,
                        'Overtime' => $metrics['overtime'],
                        'Status' => $status,
                    ];
                }


                // Add summary row
                $attendanceData[] = [
                    'Date' => 'Total',
                    'Time In' => '',
                    'Time Out' => '',
                    'Late' => $totalLate,
                    'Short' => gmdate('H:i:s', $totalShort) ,
                    'Working Hours' => $totalWorkingHours,
                    'Overtime' => $totalOvertime,
                    'Status' => '',
                    'Total Half Days' => $totalHalfDays,
                    'Total Absent' => $totalAbsent
                ];

                $attendanceSheets[] = new EmployeeAttendanceSheet($employee->name, $request->month, $attendanceData);
            }

            // Export to Excel
            return Excel::download(new MultipleEmployeeAttendanceExport($attendanceSheets), 'Monthly_Attendance_Report.xlsx');
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }


    function calculateAttendanceMetrics($attendance, $shift, $gracePeriod = '00:30:00')
    {
        $shiftStartTime = $shift->start_time;
        $shiftEndTime = $shift->end_time;
        $isNightShift = strtotime($shiftStartTime) > strtotime($shiftEndTime);

        $clockIn = $attendance->clock_in;
        $clockOut = $attendance->clock_out;
        $clockOutDateTime = date('Y-m-d H:i:s', strtotime($attendance->date . ' ' . $clockOut));

        if (strtotime($clockOut) < strtotime($clockIn)) {
            $clockOutDateTime = date('Y-m-d H:i:s', strtotime("+1 day " . $attendance->date . ' ' . $clockOut));
        }

        $shiftEndDateTime = $isNightShift
            ? date('Y-m-d H:i:s', strtotime("+1 day " . $attendance->date . ' ' . $shiftEndTime))
            : date('Y-m-d H:i:s', strtotime($attendance->date . ' ' . $shiftEndTime));

        $late = $this->calculateLate($clockIn, $shiftStartTime, $gracePeriod);
        $overtime = $this->calculateOvertime($clockOutDateTime, $shiftEndDateTime);
        $earlyLeaving = $this->calculateEarlyLeaving($clockOutDateTime, $shiftEndDateTime);

        if ($late != '00:00:00' && $overtime != '' && strtotime($overtime) > strtotime($late)) {
            $overtime = strtotime($overtime) - strtotime($late);
            $overtime = gmdate('H:i:s', $overtime);
        }

        return [
            'late' => $late,
            'early_leaving' => $earlyLeaving,
            'overtime' => $overtime,
        ];
    }

    function calculateLate($clockIn, $shiftStartTime, $gracePeriod)
    {
        list($graceHours, $graceMinutes) = explode(':', $gracePeriod);
        $graceSeconds = ($graceHours * 3600) + ($graceMinutes * 60);

        $lateSeconds = strtotime($clockIn) - strtotime($shiftStartTime);

        if ($lateSeconds > $graceSeconds) {
            return gmdate('H:i:s', $lateSeconds);
        }

        return '00:00:00';
    }

    function calculateEarlyLeaving($clockOut, $shiftEndTime)
    {
        $endTime = strtotime($shiftEndTime);
        $earlyLeavingSeconds = $endTime - strtotime($clockOut);
        return $earlyLeavingSeconds > 0 ? gmdate('H:i:s', $earlyLeavingSeconds) : '00:00:00';
    }

    function calculateOvertime($clockOut, $shiftEndTime)
    {
        $endTime = strtotime($shiftEndTime);
        $overtimeSeconds = strtotime($clockOut) - $endTime;
        return $overtimeSeconds > 0 ? gmdate('H:i:s', $overtimeSeconds) : '00:00:00';
    }

    function addTimes(string $time1, string $time2): string
    {
        // Convert time strings to seconds
        list($hours1, $minutes1, $seconds1) = explode(':', $time1);
        list($hours2, $minutes2, $seconds2) = explode(':', $time2);

        // Convert both times to total seconds
        $totalSeconds1 = ($hours1 * 3600) + ($minutes1 * 60) + $seconds1;
        $totalSeconds2 = ($hours2 * 3600) + ($minutes2 * 60) + $seconds2;

        // Add the seconds together
        $totalSeconds = $totalSeconds1 + $totalSeconds2;

        // Convert total seconds back to HH:MM:SS format
        $hours = floor($totalSeconds / 3600);
        $minutes = floor(($totalSeconds % 3600) / 60);
        $seconds = $totalSeconds % 60;

        // Format the result as HH:MM:SS
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    function convertTimeToSeconds( string $time) {
        list($hours, $minutes, $seconds) = explode(':', $time);
        $totalSeconds = ($hours * 3600) + ($minutes * 60) + $seconds;
        return $totalSeconds;
    }

}
