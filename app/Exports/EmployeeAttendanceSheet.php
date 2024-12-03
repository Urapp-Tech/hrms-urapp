<?php

namespace App\Exports;

use App\Models\Employee;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithColumnWidths;

class EmployeeAttendanceSheet implements FromCollection, WithTitle, WithHeadings, WithStyles, WithColumnWidths
{
    protected $employeeName;
    protected $month;
    protected $attendanceData;

    public function __construct($employeeName, $month, $attendanceData)
    {
        $this->employeeName = $employeeName;
        $this->month = $month;
        $this->attendanceData = $attendanceData;
    }

    public function collection()
    {
        return collect($this->attendanceData);
    }

    public function title(): string
    {
        return $this->employeeName;
    }

    public function headings(): array
    {
        return [
            'Date', 'Time In', 'Time Out', 'Late', 'Short', 'Overtime', 'Status', 'Total Half Days', 'Total Absent'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1    => ['font' => ['bold' => true, 'size' => 12], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FFFFE599']]], // Header row
            $sheet->getHighestRow() => ['font' => ['bold' => true, 'color' => ['argb' => 'FF000000']]], // Last row (summary)
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15,
            'B' => 12,
            'C' => 12,
            'D' => 10,
            'E' => 10,
            'F' => 12,
            'G' => 20,
            'H' => 15,
            'I' => 15,
        ];
    }
}
