<?php

namespace App\Exports;

use App\Models\Payment;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Carbon\Carbon;

class PaymentsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    protected $startDate;
    protected $endDate;
    protected $status;

    public function __construct($startDate = null, $endDate = null, $status = null)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->status = $status;
    }

    public function query()
    {
        $query = Payment::with(['user', 'booking.schedule.class', 'userMembership.membership']);

        if ($this->startDate) {
            $query->whereDate('created_at', '>=', $this->startDate);
        }

        if ($this->endDate) {
            $query->whereDate('created_at', '<=', $this->endDate);
        }

        if ($this->status) {
            $query->where('status', $this->status);
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'No',
            'Payment ID',
            'User',
            'Email',
            'Tipe',
            'Item Name',
            'Amount',
            'Payment Method',
            'Status',
            'Payment Date',
            'Created At',
        ];
    }

    public function map($payment): array
    {
        static $rowNumber = 0;
        $rowNumber++;

        $type = $payment->booking_id ? 'Booking Kelas' : 'Beli Membership';
        $itemName = '';

        if ($payment->booking) {
            $itemName = $payment->booking->schedule->class->class_name ?? '-';
        } elseif ($payment->userMembership) {
            $itemName = $payment->userMembership->membership->name ?? '-';
        }

        return [
            $rowNumber,
            $payment->payment_id,
            $payment->user->name,
            $payment->user->email,
            $type,
            $itemName,
            'Rp ' . number_format($payment->amount, 0, ',', '.'),
            strtoupper($payment->payment_method),
            ucfirst($payment->status),
            $payment->payment_date ? Carbon::parse($payment->payment_date)->format('d/m/Y H:i') : '-',
            Carbon::parse($payment->created_at)->format('d/m/Y H:i'),
        ];
    }
}
