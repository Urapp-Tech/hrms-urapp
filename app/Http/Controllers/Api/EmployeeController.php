<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BiometricData;
use App\Models\Employee;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function enrollEmployee(Request $request, $companyId)
    {
        $validated = $request->validate([
            'id' => 'required|exists:employees,id',
            'machineNumber' => 'required|integer',
            'fingerprintIndex' => 'required|integer',
            'fingerprintData' => 'required|string',
        ]);

        try {
            // Find the employee scoped by company (created_by)
            $employee = Employee::where('id', $validated['id'])
                ->where('created_by', $companyId)
                ->firstOrFail();

            // Update employee's biometric enrollment status
            $employee->update(['is_fingerprint_enrolled' => true]);

            // Store biometric data
            BiometricData::updateOrCreate(
                ['employee_id' => $employee->id],
                [
                    'machine_number' => $validated['machineNumber'],
                    'fingerprint_index' => $validated['fingerprintIndex'],
                    'fingerprint_data' => $validated['fingerprintData'],
                ]
            );

            return response()->json([
                'statusCode' => 200,
                'message' => 'Employee enrolled successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'statusCode' => 500,
                'message' => $e->getMessage(),
            ]);
        }
    }


    public function getNotEnrolledEmployees($companyId)
    {
        try {
            // Fetch employees where is_fingerprint_enrolled is false and created_by matches companyId
            $employees = Employee::where('is_fingerprint_enrolled', false)
                ->where('created_by', $companyId)
                ->select('id', 'name', 'email', 'phone', 'is_active')
                ->get();

            return response()->json([
                'statusCode' => 200,
                'message' => 'Not enrolled employees fetched successfully.',
                'data' => $employees,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'statusCode' => 500,
                'message' => $e->getMessage(),
            ]);
        }
    }


    public function synchronizeEmployees(Request $request, $companyId)
    {
        $validated = $request->validate([
            '*.id' => 'required|exists:employees,id',
            '*.enrolled' => 'required|boolean',
            '*.data.machineNumber' => 'nullable|integer',
            '*.data.fingerprintIndex' => 'nullable|integer',
            '*.data.fingerprintData' => 'nullable|string',
        ]);

        try {
            foreach ($validated as $employeeData) {
                // Ensure employee belongs to the specified company
                $employee = Employee::where('id', $employeeData['id'])
                    ->where('created_by', $companyId)
                    ->firstOrFail();

                // Update enrollment status
                $employee->update([
                    'is_fingerprint_enrolled' => $employeeData['enrolled'],
                ]);

                // Update or insert biometric data if provided
                if (!empty($employeeData['data'])) {
                    BiometricData::updateOrCreate(
                        ['employee_id' => $employee->id],
                        [
                            'machine_number' => $employeeData['data']['machineNumber'],
                            'fingerprint_index' => $employeeData['data']['fingerprintIndex'],
                            'fingerprint_data' => $employeeData['data']['fingerprintData'],
                        ]
                    );
                }
            }

            return response()->json([
                'statusCode' => 200,
                'message' => 'Employees synchronized successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'statusCode' => 500,
                'message' => $e->getMessage(),
            ]);
        }
    }


    public function getEnrolledEmployees($companyId)
    {
        try {
            // Fetch employees where is_fingerprint_enrolled is true and created_by matches companyId
            $employees = Employee::where('is_fingerprint_enrolled', true)
                ->where('created_by', $companyId)
                ->select('id', 'name', 'email', 'phone', 'is_active')
                ->get();

            return response()->json([
                'statusCode' => 200,
                'message' => 'Enrolled employees fetched successfully.',
                'data' => $employees,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'statusCode' => 500,
                'message' => $e->getMessage(),
            ]);
        }
    }

}
