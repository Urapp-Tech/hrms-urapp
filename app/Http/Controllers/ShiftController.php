<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ShiftController extends Controller
{
    public function index()
    {
        if (Auth::user()->can('Manage Shift')) {

            $shifts = Shift::where('created_by', $this->getCreatorId())->get();

            return view('shift.index', compact('shifts'));
        } else {
            return redirect()->back()->with('error', 'Permission denied.');
        }
    }

    public function create()
    {
        if (Auth::user()->can('Create Shift')) {
            return view('shift.create');
        } else {
            return redirect()->back()->with('error', 'Permission denied.');
        }
    }

    public function store(Request $request)
    {
        if (Auth::user()->can('Create Shift')) {
            $validator = Validator::make(
                $request->all(),
                [
                    'name' => 'required|max:100|unique:shifts,name,NULL,id,created_by,' . Auth::user()->id,
                    'start_time' => 'required|date_format:H:i',
                    'end_time' => 'required|date_format:H:i',
                ]
            );

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }

            // Validate shift time logic
            if ($this->isInvalidShiftTime($request->start_time, $request->end_time)) {
                return redirect()->back()->with('error', 'End time must be after start time or cross midnight.')->withInput();
            }

            $shift = new Shift();
            $shift->name = $request->name;
            $shift->start_time = $request->start_time;
            $shift->end_time = $request->end_time;
            $shift->created_by = $this->getCreatorId();
            $shift->save();

            return redirect()->route('shift.index')->with('success', 'Shift successfully created.');
        } else {
            return redirect()->back()->with('error', 'Permission denied.');
        }
    }

    public function edit(Shift $shift)
    {
        if (Auth::user()->can('Edit Shift') && $shift->created_by == $this->getCreatorId()) {
            return view('shift.edit', compact('shift'));
        } else {
            return redirect()->back()->with('error', 'Permission denied.');
        }
    }

    public function update(Request $request, Shift $shift)
    {
        // dd($request->all());
        if (Auth::user()->can('Edit Shift') && $shift->created_by == $this->getCreatorId()) {
            $validator = Validator::make(
                $request->all(),
                [
                    'name' => 'required|max:100|unique:shifts,name,' . $shift->id . ',id,created_by,' . $this->getCreatorId(),
                    'start_time' => 'required|date_format:H:i:s',
                    'end_time' => 'required|date_format:H:i:s',
                ]
            );

            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }

            // Validate shift time logic
            if ($this->isInvalidShiftTime($request->start_time, $request->end_time)) {
                return redirect()->back()->with('error', 'End time must be after start time or cross midnight.')->withInput();
            }

            $shift->name = $request->name;
            $shift->start_time = $request->start_time;
            $shift->end_time = $request->end_time;
            $shift->save();

            return redirect()->route('shift.index')->with('success', 'Shift successfully updated.');
        } else {
            return redirect()->back()->with('error', 'Permission denied.');
        }
    }

    public function destroy(Shift $shift)
    {
        if (Auth::user()->can('Delete Shift') && $shift->created_by == $this->getCreatorId()) {
            $shift->delete();

            return redirect()->route('shift.index')->with('success', 'Shift successfully deleted.');
        } else {
            return redirect()->back()->with('error', 'Permission denied.');
        }
    }

    /**
     * Check if the shift time is valid.
     *
     * @param string $startTime
     * @param string $endTime
     * @return bool
     */
    private function isInvalidShiftTime(string $startTime, string $endTime): bool
    {
        return strtotime($startTime) > strtotime($endTime) && date('H:i', strtotime($startTime)) <= date('H:i', strtotime($endTime));
    }

    private function getCreatorId() {
        if (Auth::user()->type != 'company') {
            return Auth::user()->created_by;
        }
        return Auth::user()->id;
    }
}
