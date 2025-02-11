<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\RemoteAttendancePermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class RemoteAttendancePermissionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (Auth::user()->can('Manage Remote Attendance')) {

            $remote_attendancePermissions = RemoteAttendancePermission::where('created_by', $this->getCreatorId())->get();

            return view('remoteAttendance.index', compact('remote_attendancePermissions'));
        } else {
            return redirect()->back()->with('error', 'Permission denied.');
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if (Auth::user()->can('Create Remote Attendance')) {
            $employees = Employee::where('created_by', $this->getCreatorId())->get()->pluck('name', 'id'); // Fetch employees to select from
            return view('remoteAttendance.create', compact('employees'));
        } else {
            return redirect()->back()->with('error', 'Permission denied.');
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (!Auth::user()->can('Create Remote Attendance')) {
            return redirect()->back()->with('error', 'Permission denied.');
        }

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'required|in:pending,approved,rejected',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        RemoteAttendancePermission::create([
            'employee_id' => $request->employee_id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'approved_by' => Auth::id(),
            'status' => $request->status,
            'created_by' => $this->getCreatorId()
        ]);

        return redirect()->route('remote-attendance.index')->with('success', 'Remote Attendance Permission granted successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(RemoteAttendancePermission $remoteAttendancePermission)
    {
        if (!Auth::user()->can('View Remote Attendance')) {
            return redirect()->back()->with('error', 'Permission denied.');
        }

        return view('remoteAttendance.show', compact('remoteAttendancePermission'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(RemoteAttendancePermission $remote_attendance)
    {
        if (!Auth::user()->can('Edit Remote Attendance')) {
            return redirect()->back()->with('error', 'Permission denied.');
        }
        $employees = Employee::where('created_by', $this->getCreatorId())->get()->pluck('name', 'id');
        return view('remoteAttendance.edit', compact('remote_attendance', 'employees'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, RemoteAttendancePermission $remote_attendance)
    {
        if (!Auth::user()->can('Edit Remote Attendance')) {
            return redirect()->back()->with('error', 'Permission denied.');
        }

        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'required|in:pending,approved,rejected',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $remote_attendance->update([
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'status' => $request->status,
        ]);

        return redirect()->route('remote-attendance.index')->with('success', 'Remote Attendance Permission updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RemoteAttendancePermission $remote_attendance)
    {
        if (!Auth::user()->can('Delete Remote Attendance')) {
            return redirect()->back()->with('error', 'Permission denied.');
        }

        $remote_attendance->delete();

        return redirect()->route('remote-attendance.index')->with('success', 'Remote Attendance Permission deleted successfully.');
    }

    private function getCreatorId() {
        if (Auth::user()->type != 'company') {
            return Auth::user()->created_by;
        }
        return Auth::user()->id;
    }
}
