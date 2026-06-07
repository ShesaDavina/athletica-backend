<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Tiket Booking Athletica</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', 'Segoe UI', Arial, sans-serif;
            background: #f4f4f9;
            padding: 40px 20px;
        }

        .ticket {
            max-width: 500px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 35px rgba(0, 0, 0, 0.1);
        }

        .ticket-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }

        .ticket-header h1 {
            font-size: 28px;
            margin-bottom: 8px;
        }

        .ticket-header p {
            font-size: 14px;
            opacity: 0.9;
        }

        .ticket-body {
            padding: 30px;
        }

        .section {
            margin-bottom: 25px;
            border-bottom: 1px dashed #e0e0e0;
            padding-bottom: 15px;
        }

        .section-title {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #999;
            margin-bottom: 12px;
        }

        .section-content {
            font-size: 16px;
            color: #333;
        }

        .section-content strong {
            font-size: 18px;
            color: #667eea;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .info-label {
            font-weight: 600;
            color: #555;
        }

        .info-value {
            color: #222;
        }

        .qr-code {
            text-align: center;
            margin: 20px 0;
        }

        .qr-code img {
            width: 120px;
            height: 120px;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            background: #10b981;
            color: white;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
        }

        .ticket-footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #777;
            border-top: 1px solid #eee;
        }

        .barcode {
            text-align: center;
            margin-top: 15px;
            font-family: 'Courier New', monospace;
            letter-spacing: 2px;
            font-size: 14px;
        }
    </style>
</head>

<body>
    <div class="ticket">
        <div class="ticket-header">
            <h1>🏋️ ATHLETICA</h1>
            <p>Fitness Class Ticket</p>
        </div>

        <div class="ticket-body">
            <div class="section" style="text-align: center;">
                <span
                    class="status-badge">{{ $booking->status === 'booked' ? 'CONFIRMED' : strtoupper($booking->status) }}</span>
            </div>

            <div class="section">
                <div class="section-title">INFORMASI KELAS</div>
                <div class="section-content">
                    <strong>{{ $class->class_name }}</strong>
                </div>
                <div style="margin-top: 12px;">
                    <div class="info-row">
                        <span class="info-label">Tanggal</span>
                        <span
                            class="info-value">{{ \Carbon\Carbon::parse($schedule->schedule_date)->format('l, d F Y') }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Waktu</span>
                        <span class="info-value">{{ $schedule->start_time }} - {{ $schedule->end_time }} WIB</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Trainer</span>
                        <span class="info-value">{{ $trainer->name }}</span>
                    </div>
                </div>
            </div>

            <div class="section">
                <div class="section-title">INFORMASI PESERTA</div>
                <div class="info-row">
                    <span class="info-label">Nama</span>
                    <span class="info-value">{{ $user->name }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email</span>
                    <span class="info-value">{{ $user->email }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Booking ID</span>
                    <span class="info-value">#{{ $booking->booking_id }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Tipe Booking</span>
                    <span
                        class="info-value">{{ $booking->booking_type === 'membership' ? 'Membership' : 'Reguler' }}</span>
                </div>
            </div>

            @if ($payment && $payment->status === 'paid')
                <div class="section">
                    <div class="section-title">DETAIL PEMBAYARAN</div>
                    <div class="info-row">
                        <span class="info-label">Total Bayar</span>
                        <span class="info-value"><strong>Rp
                                {{ number_format($payment->amount, 0, ',', '.') }}</strong></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Metode</span>
                        <span class="info-value">{{ strtoupper($payment->payment_method) }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Tanggal Bayar</span>
                        <span
                            class="info-value">{{ \Carbon\Carbon::parse($payment->payment_date)->format('d/m/Y H:i') }}</span>
                    </div>
                </div>
            @endif

            <div class="barcode">
                {{ $booking->booking_id }}-{{ $user->user_id }}-{{ \Carbon\Carbon::parse($schedule->schedule_date)->format('Ymd') }}
            </div>
        </div>

        <div class="ticket-footer">
            <p>Tunjukkan tiket ini saat check-in di resepsionis</p>
            <p style="margin-top: 8px;">Datang 15 menit sebelum kelas dimulai</p>
            <p style="margin-top: 12px; font-size: 10px;">Generated on {{ $date }}</p>
        </div>
    </div>
</body>

</html>
