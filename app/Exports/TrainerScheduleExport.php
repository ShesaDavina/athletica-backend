<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TrainerScheduleExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $schedules;

    public function __construct($schedules)
    {
        $this->schedules = $schedules;
    }

    public function collection()
    {
        return $this->schedules;
    }

    public function headings(): array
    {
        return [
            'No',
            'Tanggal',
            'Kelas',
            'Jam Mulai',
            'Jam Selesai',
            'Kapasitas',
            'Terisi',
            'Sisa',
        ];
    }

    public function map($row): array
    {
        static $rowNumber = 0;
        $rowNumber++;

        return [
            $rowNumber,
            Carbon::parse($row['schedule_date'])->format('d/m/Y'),
            $row['class_name'],
            $row['start_time'],
            $row['end_time'],
            $row['capacity'],
            $row['booked_count'],
            $row['capacity'] - $row['booked_count'],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                ],
            ],
        ];
    }
}
