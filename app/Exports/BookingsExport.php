<?php

namespace App\Exports;

use App\Models\Booking;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;

class BookingsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $startDate;
    protected $endDate;
    protected $status;
    protected $search;

    public function __construct($startDate = null, $endDate = null, $status = null, $search = null)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->status = $status;
        $this->search = $search;
    }

    public function query()
    {
        $query = Booking::with(['user', 'schedule.class', 'schedule.trainer', 'payment']);

        if ($this->startDate) {
            $query->whereDate('created_at', '>=', $this->startDate);
        }

        if ($this->endDate) {
            $query->whereDate('created_at', '<=', $this->endDate);
        }

        if ($this->status) {
            $query->where('status', $this->status);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->whereHas('user', function ($q2) {
                    $q2->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%');
                })->orWhereHas('schedule.class', function ($q2) {
                    $q2->where('class_name', 'like', '%' . $this->search . '%');
                })->orWhereHas('schedule.trainer', function ($q2) {
                    $q2->where('name', 'like', '%' . $this->search . '%');
                });
            });
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'No',
            'Booking ID',
            'User',
            'Email',
            'Kelas',
            'Trainer',
            'Tanggal Kelas',
            'Jam Mulai',
            'Jam Selesai',
            'Tipe Booking',
            'Status Booking',
            'Harga',
            'Status Payment',
            'Tanggal Booking',
        ];
    }

    public function map($booking): array
    {
        static $rowNumber = 0;
        $rowNumber++;

        return [
            $rowNumber,
            $booking->booking_id,
            $booking->user->name,
            $booking->user->email,
            $booking->schedule->class->class_name ?? '-',
            $booking->schedule->trainer->name ?? '-',
            Carbon::parse($booking->schedule->schedule_date)->format('d/m/Y'),
            $booking->schedule->start_time,
            $booking->schedule->end_time,
            ucfirst($booking->booking_type),
            ucfirst($booking->status),
            $booking->payment ? 'Rp ' . number_format($booking->payment->amount, 0, ',', '.') : '-',
            $booking->payment ? ucfirst($booking->payment->status) : '-',
            Carbon::parse($booking->created_at)->format('d/m/Y H:i'),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 12]],
        ];
    }
}
