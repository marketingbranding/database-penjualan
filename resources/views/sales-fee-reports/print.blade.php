<!doctype html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <title>Laporan Performa Sales</title>
        <style>
            @page { size: A4; margin: 14mm; }
            * { box-sizing: border-box; }
            body { color: #1d2219; font-family: Arial, sans-serif; font-size: 10px; }
            h1 { margin: 0 0 4px; text-align: center; font-size: 18px; }
            h2 { margin: 14px 0 5px; font-size: 12px; }
            .meta { margin-bottom: 16px; color: #687064; text-align: center; }
            .brand { margin-bottom: 10px; text-align: center; }
            .brand img { width: 180px; height: auto; }
            .summary { display: grid; grid-template-columns: repeat(6, 1fr); gap: 5px; }
            .metric { border: 1px solid #d7d9d1; border-radius: 5px; padding: 7px; }
            .metric span { display: block; color: #687064; font-size: 9px; }
            .metric strong { display: block; margin-top: 3px; font-size: 15px; }
            table { width: 100%; margin-top: 5px; border-collapse: collapse; }
            th, td { border: 1px solid #d7d9d1; padding: 4px; text-align: left; vertical-align: top; }
            th { background: #f4f4f1; color: #485045; font-size: 9px; }
            td.number { text-align: center; }
            .note { margin-top: 14px; color: #687064; font-size: 9px; }
            @media print { .no-print { display: none; } }
        </style>
    </head>
    <body>
        <div class="brand"><img src="{{ asset('brand/oasis-next-logo.svg') }}" alt="Oasis Next"></div>
        <h1>LAPORAN PERFORMA SALES</h1>
        <div class="meta">Periode {{ $filters['date_from'] }} sampai {{ $filters['date_to'] }}</div>

        <div class="summary">
            @foreach ([
                'Total Agenda' => 'total_agenda',
                'Agenda Selesai' => 'agenda_selesai',
                'Total Lead' => 'total_lead',
                'Tatap Muka' => 'tatap_muka',
                'Cek Lokasi' => 'cek_lokasi',
                'UTJ' => 'utj',
            ] as $label => $key)
                <div class="metric"><span>{{ $label }}</span><strong>{{ $report['totals'][$key] }}</strong></div>
            @endforeach
        </div>

        <h2>REKAP SALES</h2>
        <table>
            <thead><tr><th>Sales</th><th>Koordinator</th><th>Proyek</th><th>Total Agenda</th><th>Agenda Selesai</th><th>Total Lead</th></tr></thead>
            <tbody>
                @forelse ($report['rows'] as $row)
                    <tr><td>{{ $row['sales_name'] }}</td><td>{{ $row['coordinator_name'] }}</td><td>{{ $row['project_name'] }}</td><td class="number">{{ $row['total_agenda'] }}</td><td class="number">{{ $row['agenda_selesai'] }}</td><td class="number">{{ $row['total_lead'] }}</td></tr>
                @empty
                    <tr><td colspan="6">Tidak ada data.</td></tr>
                @endforelse
            </tbody>
        </table>

        <h2>DETAIL LEAD</h2>
        <table>
            <thead><tr><th>Tanggal</th><th>ID Lead</th><th>Konsumen</th><th>Sales</th><th>Sumber / Kanal</th><th>Status</th></tr></thead>
            <tbody>
                @forelse ($report['leads'] as $lead)
                    <tr><td>{{ $lead->tanggal_lead->format('d/m/Y') }}</td><td>{{ $lead->id_lead }}</td><td>{{ $lead->nama_konsumen }}</td><td>{{ $lead->salesPic->name }}</td><td>{{ $lead->sumber_lead }} / {{ $lead->kanal_masuk }}</td><td>{{ $lead->status_lead }}</td></tr>
                @empty
                    <tr><td colspan="6">Tidak ada Lead.</td></tr>
                @endforelse
            </tbody>
        </table>

        <h2>DETAIL AGENDA</h2>
        <table>
            <thead><tr><th>Tanggal</th><th>Sales</th><th>Proyek</th><th>Kategori</th><th>Agenda / Hasil</th><th>Status</th></tr></thead>
            <tbody>
                @forelse ($report['agendas'] as $agenda)
                    <tr><td>{{ $agenda->agenda_date->format('d/m/Y') }}</td><td>{{ $agenda->creator->name }}</td><td>{{ $agenda->lead?->project?->name ?? $agenda->salesCase?->lot?->project?->name ?? '-' }}</td><td>{{ $agenda->activity_category }}</td><td>{{ $agenda->title }}<br>{{ $agenda->activity_result ?: '-' }}</td><td>{{ $agenda->status }}</td></tr>
                @empty
                    <tr><td colspan="6">Tidak ada Agenda.</td></tr>
                @endforelse
            </tbody>
        </table>

        <p class="note">Laporan ini merupakan rekap performa aktivitas. Nominal fee belum dihitung.</p>
        <script>window.addEventListener('load', () => window.print());</script>
    </body>
</html>
