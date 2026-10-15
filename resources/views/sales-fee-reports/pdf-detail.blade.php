<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Detail Performa Sales</title>
    <style>
        @page { size: A4 portrait; margin: 14mm; @bottom-right { content: "Halaman " counter(page) " dari " counter(pages); color: #687064; font-size: 8px; } }
        * { box-sizing: border-box; }
        body { color: #1d2219; font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        .header { border-bottom: 2px solid #9ccc4e; margin-bottom: 12px; padding-bottom: 8px; }
        .logo { height: 30px; width: auto; }
        h1 { font-size: 15px; margin: 7px 0 3px; }
        .meta { color: #687064; font-size: 8px; }
        .metrics { display: table; margin: 0 -3px 12px; table-layout: fixed; width: 100%; }
        .metric-cell { display: table-cell; padding: 3px; }
        .metric { border: 1px solid #d7d9d1; padding: 5px; }
        .metric-label { color: #687064; display: block; font-size: 7px; }
        .metric-value { display: block; font-size: 12px; font-weight: bold; margin-top: 3px; }
        h2 { border-bottom: 1px solid #d7d9d1; font-size: 11px; margin: 12px 0 5px; padding-bottom: 3px; }
        table { border-collapse: collapse; margin-top: 4px; width: 100%; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th, td { border: 1px solid #d7d9d1; padding: 4px; text-align: left; vertical-align: top; }
        th { background: #f4f4f1; color: #485045; font-size: 8px; }
        .empty { color: #687064; padding: 8px; text-align: center; }
        .note { color: #687064; font-size: 8px; margin-top: 12px; }
    </style>
</head>
<body>
    <header class="header">
        <img class="logo" src="{{ $logoDataUri }}" alt="Oasis Next">
        <h1>Detail Performa Sales: {{ $salesUser->name }}</h1>
        <div class="meta">Cabang: {{ $branchName }} · Proyek: {{ $project?->name ?? 'Semua proyek' }} · Periode: {{ $filters['date_from'] }} sampai {{ $filters['date_to'] }} · Dicetak oleh {{ auth()->user()->name }} · {{ now()->format('d/m/Y H:i') }}</div>
    </header>

    <div class="metrics">
        @foreach (['Total Agenda' => 'total_agenda', 'Agenda Selesai' => 'agenda_selesai', 'Total Lead' => 'total_lead', 'Tatap Muka' => 'tatap_muka', 'Cek Lokasi' => 'cek_lokasi', 'UTJ' => 'utj'] as $label => $key)
            <div class="metric-cell"><div class="metric"><span class="metric-label">{{ $label }}</span><strong class="metric-value">{{ number_format($report['totals'][$key]) }}</strong></div></div>
        @endforeach
    </div>

    <h2>Detail Lead</h2>
    <table>
        <thead><tr><th>Tanggal</th><th>Konsumen</th><th>Sumber / Kanal</th><th>Status</th></tr></thead>
        <tbody>
            @forelse ($report['leads'] as $lead)
                <tr><td>{{ $lead->tanggal_lead->format('d/m/Y') }}</td><td>{{ $lead->nama_konsumen }}<br>{{ $lead->id_lead }}</td><td>{{ $lead->sumber_lead }} / {{ $lead->kanal_masuk }}</td><td>{{ $lead->status_lead }}</td></tr>
            @empty
                <tr><td class="empty" colspan="4">Tidak ada Lead.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Detail Agenda</h2>
    <table>
        <thead><tr><th>Tanggal</th><th>Kategori</th><th>Agenda / Hasil</th><th>Status</th></tr></thead>
        <tbody>
            @forelse ($report['agendas'] as $agenda)
                <tr><td>{{ $agenda->agenda_date->format('d/m/Y') }}</td><td>{{ $agenda->activity_category }}</td><td>{{ $agenda->title }}<br>{{ $agenda->activity_result ?: '-' }}</td><td>{{ str($agenda->status)->replace('_', ' ')->title() }}</td></tr>
            @empty
                <tr><td class="empty" colspan="4">Tidak ada Agenda.</td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="note">Laporan ini merupakan rekap performa aktivitas. Nominal fee belum dihitung.</p>
</body>
</html>
