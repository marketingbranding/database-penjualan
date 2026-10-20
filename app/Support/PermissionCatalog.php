<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;

final class PermissionCatalog
{
    /**
     * @return array<int, array{label: string, permissions: array<int, array{name: string, label: string}>}>
     */
    public static function groups(Collection $permissions): array
    {
        $definitions = [
            'Dashboard & Workspace' => [
                'dashboard.view' => 'Lihat dashboard',
                'spatial-workspace.view' => 'Lihat Spatial Workspace',
            ],
            'Buku Saku Sales' => [
                'sales.view-own' => 'Lihat aktivitas sales — Milik saya',
                'sales.view-team' => 'Lihat aktivitas sales — Tim saya',
                'sales.view-all' => 'Lihat aktivitas sales — Semua cabang',
                'sales.create' => 'Buat agenda sales',
                'sales.update' => 'Ubah agenda sales',
                'sales.delete' => 'Hapus agenda sales',
                'sales-targets.view-own' => 'Lihat target lead — Milik saya',
                'sales-targets.view-team' => 'Lihat target lead — Tim saya',
                'sales-targets.view-all' => 'Lihat target lead — Semua cabang',
                'sales-targets.manage' => 'Kelola target lead sales',
                'leads.view-own' => 'Lihat lead — Milik saya',
                'leads.view-team' => 'Lihat lead — Tim saya',
                'leads.view-branch' => 'Lihat lead — Cabang saya',
                'leads.view-all' => 'Lihat lead — Semua cabang',
                'leads.create' => 'Buat lead',
                'leads.update-own' => 'Ubah lead — Milik saya',
                'leads.update-team' => 'Ubah lead — Tim saya',
                'leads.delete' => 'Hapus lead',
                'leads.monitor' => 'Buka monitoring lead',
                'sales-fee-reports.view' => 'Lihat laporan sales fee',
                'sales-fee-reports.print' => 'Cetak laporan sales fee',
                'sales-fee-reports.export' => 'Export laporan sales fee',
            ],
            'Data Konsumen' => [
                'consumers.view' => 'Lihat data konsumen',
                'consumers.create' => 'Tambah data konsumen',
                'consumers.update' => 'Ubah data konsumen',
                'consumers.cancel' => 'Batalkan data konsumen',
                'consumers.export' => 'Export data konsumen',
            ],
            'Proses Penjualan' => [
                'sales-process.view' => 'Lihat proses penjualan',
                'sales-process.update' => 'Ubah proses penjualan',
                'psjb-utj.view' => 'Lihat PSJB / UTJ',
                'psjb-utj.create' => 'Tambah PSJB / UTJ',
                'psjb-utj.update' => 'Ubah PSJB / UTJ',
                'bi-checking.view' => 'Lihat BI Checking',
                'bi-checking.create' => 'Tambah BI Checking',
                'bi-checking.update' => 'Ubah BI Checking',
                'filing.view' => 'Lihat pemberkasan',
                'filing.create' => 'Tambah pemberkasan',
                'filing.update' => 'Ubah pemberkasan',
                'bank-process.view' => 'Lihat proses bank',
                'bank-process.create' => 'Tambah proses bank',
                'bank-process.update' => 'Ubah proses bank',
                'ppjb.view' => 'Lihat PPJB Developer',
                'ppjb.create' => 'Tambah PPJB Developer',
                'ppjb.update' => 'Ubah PPJB Developer',
                'akad.view' => 'Lihat Akad',
                'akad.create' => 'Tambah Akad',
                'akad.update' => 'Ubah Akad',
                'bast.view' => 'Lihat BAST',
                'bast.create' => 'Tambah BAST',
                'bast.update' => 'Ubah BAST',
                'warranties.view' => 'Lihat garansi',
                'warranties.create' => 'Tambah garansi',
                'warranties.update' => 'Ubah garansi',
            ],
            'Master Data' => [
                'branches.view' => 'Lihat cabang',
                'branches.create' => 'Tambah cabang',
                'branches.update' => 'Ubah cabang',
                'branches.delete' => 'Hapus cabang',
                'projects.view' => 'Lihat proyek',
                'projects.create' => 'Tambah proyek',
                'projects.update' => 'Ubah proyek',
                'projects.delete' => 'Hapus proyek',
                'lots.view' => 'Lihat kavling',
                'lots.update' => 'Ubah status kavling',
                'banks.view' => 'Lihat bank',
                'banks.create' => 'Tambah bank',
                'banks.update' => 'Ubah bank',
                'banks.delete' => 'Hapus bank',
                'promotions.view' => 'Lihat promo',
                'promotions.create' => 'Tambah promo',
                'promotions.update' => 'Ubah promo',
                'promotions.delete' => 'Hapus promo',
                'lead-time.view' => 'Lihat target lead time',
                'lead-time.update' => 'Ubah target lead time',
            ],
            'Pengguna & Akses' => [
                'users.view' => 'Lihat pengguna',
                'users.create' => 'Tambah pengguna',
                'users.update' => 'Ubah pengguna',
                'users.delete' => 'Hapus pengguna',
                'users.assign-roles' => 'Atur cabang, tim, dan akses pengguna',
                'roles.view' => 'Lihat role',
                'roles.create' => 'Tambah role',
                'roles.update' => 'Ubah role dan permission',
                'roles.delete' => 'Hapus role',
                'teams.view' => 'Lihat tim',
                'teams.create' => 'Tambah tim',
                'teams.update' => 'Ubah tim',
                'teams.delete' => 'Hapus tim',
            ],
            'Laporan & Pengaturan' => [
                'reports.view' => 'Lihat laporan',
                'reports.export' => 'Export laporan',
                'reports.team-performance' => 'Lihat performa tim',
                'audit-logs.view' => 'Lihat audit log',
                'settings.view' => 'Lihat pengaturan',
            ],
        ];

        $knownPermissions = [];
        $groups = [];

        foreach ($definitions as $label => $labels) {
            $groupPermissions = $permissions
                ->filter(fn (Permission $permission): bool => array_key_exists($permission->name, $labels))
                ->sortBy('name')
                ->map(fn (Permission $permission): array => [
                    'name' => $permission->name,
                    'label' => $labels[$permission->name],
                ])
                ->values()
                ->all();

            if ($groupPermissions !== []) {
                $groups[] = [
                    'label' => $label,
                    'permissions' => $groupPermissions,
                ];
            }

            $knownPermissions = [...$knownPermissions, ...array_keys($labels)];
        }

        $otherPermissions = $permissions
            ->reject(fn (Permission $permission): bool => in_array($permission->name, $knownPermissions, true))
            ->sortBy('name')
            ->map(fn (Permission $permission): array => [
                'name' => $permission->name,
                'label' => self::humanize($permission->name),
            ])
            ->values()
            ->all();

        if ($otherPermissions !== []) {
            $groups[] = [
                'label' => 'Permission lainnya',
                'permissions' => $otherPermissions,
            ];
        }

        return $groups;
    }

    private static function humanize(string $permission): string
    {
        [$resource, $action] = array_pad(explode('.', $permission, 2), 2, '');
        $resource = str($resource)->replace(['-', '_'], ' ')->title();

        $actionLabel = match ($action) {
            'view' => 'Lihat',
            'create' => 'Tambah',
            'update' => 'Ubah',
            'delete' => 'Hapus',
            'export' => 'Export',
            'print' => 'Cetak',
            default => str($action)->replace(['-', '_'], ' ')->title()->toString(),
        };

        return trim("{$actionLabel} {$resource}");
    }
}
