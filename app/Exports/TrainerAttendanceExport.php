<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;

class TrainerAttendanceExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $attendances;

    public function __construct($attendances)
    {
        $this->attendances = $attendances;
    }

    public function collection()
    {
        return $this->attendances;
    }

    public function headings(): array
    {
        return [
            'No',
            'Nama Peserta',
            'Email',
            'Kelas',
            'Tanggal Jadwal',
            'Jam Mulai',
            'Jam Selesai',
            'Tipe Booking',
            'Status Kehadiran',
        ];
    }

    public function map($row): array
    {
        static $rowNumber = 0;
        $rowNumber++;

        return [
            $rowNumber,
            $row['user_name'],
            $row['user_email'],
            $row['class_name'],
            Carbon::parse($row['schedule_date'])->format('d/m/Y'),
            $row['start_time'],
            $row['end_time'],
            $row['booking_type'] === 'membership' ? 'Membership' : 'Reguler',
            $row['status'] === 'attended' ? 'Hadir' : ($row['status'] === 'canceled' ? 'Batal' : 'Booked'),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 12]],
        ];
    }
}
