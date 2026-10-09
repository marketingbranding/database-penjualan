<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'id_lead', 'id_promo', 'nama_promo', 'tanggal_lead', 'sumber_lead', 'kanal_masuk',
    'aktivitas_lead', 'nama_konsumen', 'no_hp', 'project_id', 'branch_id', 'team_id',
    'sales_pic_id', 'created_by', 'status_lead', 'keterangan',
])]
class Lead extends Model
{
    public const SOURCES = ['Online', 'Offline', 'Referral', 'Freelance', 'Lead Cabang', 'Lead Pusat'];

    public const CHANNELS = [
        'TikTok', 'Facebook', 'Instagram', 'WhatsApp', 'Website', 'Marketplace', 'Canvassing',
        'Pameran', 'Event', 'Telepon', 'Datang Langsung', 'Tidak Diketahui',
    ];

    public const ACTIVITIES = [
        'Live', 'Konten Organik', 'Story', 'Reel', 'Iklan Berbayar', 'Marketplace',
        'Broadcast WhatsApp', 'Follow Up Data Lama', 'Sebar Brosur', 'Pameran', 'Event',
        'Referral', 'Freelance', 'Datang Langsung', 'Tidak Diketahui',
    ];

    public const STATUSES = [
        'No Respon', 'Diskusi', 'Tatap Muka', 'Cek Lokasi', 'Jadi Freelance', 'UTJ',
        'Cek SLIK', 'Tidak Lolos BI Checking', 'Akad',
    ];

    public const ACTIVITY_TYPES = [
        'Lead Baru', 'Follow Up', 'Telepon/WhatsApp', 'Tatap Muka', 'Cek Lokasi', 'Catatan', 'Status Update',
    ];

    /** @use HasFactory<LeadFactory> */
    use HasFactory;

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function salesPic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_pic_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class)->latest('happened_at');
    }

    public function latestActivity(): HasOne
    {
        return $this->hasOne(LeadActivity::class)->latestOfMany('happened_at');
    }

    public function salesAgendas(): HasMany
    {
        return $this->hasMany(SalesAgenda::class);
    }

    public function scopeForBranch(Builder $query, ?int $branchId): Builder
    {
        return $query->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId));
    }

    public static function generateLeadId(string $date, string $source, string $channel): string
    {
        $leadDate = Carbon::parse($date);
        $prefix = $leadDate->format('ymd').'-'.self::sourceCode($source).'-'.self::channelCode($channel);
        $lastId = self::query()
            ->whereDate('tanggal_lead', $leadDate->toDateString())
            ->where('sumber_lead', $source)
            ->where('kanal_masuk', $channel)
            ->latest('id')
            ->value('id_lead');
        $sequence = $lastId && preg_match('/-(\d+)$/', $lastId, $matches) ? ((int) $matches[1]) + 1 : 1;

        do {
            $leadId = $prefix.'-'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);
            $sequence++;
        } while (self::query()->where('id_lead', $leadId)->exists());

        return $leadId;
    }

    protected function casts(): array
    {
        return ['tanggal_lead' => 'date'];
    }

    private static function sourceCode(string $source): string
    {
        return match ($source) {
            'Online' => 'ON',
            'Offline' => 'OF',
            'Referral' => 'RE',
            'Freelance' => 'FR',
            'Lead Cabang' => 'LC',
            'Lead Pusat' => 'LP',
            default => 'XX',
        };
    }

    private static function channelCode(string $channel): string
    {
        return match ($channel) {
            'TikTok' => 'TI',
            'Facebook' => 'FB',
            'Instagram' => 'IG',
            'WhatsApp' => 'WA',
            'Website' => 'WE',
            'Marketplace' => 'MP',
            'Canvassing' => 'CA',
            'Pameran' => 'PA',
            'Event' => 'EV',
            'Telepon' => 'TL',
            'Datang Langsung' => 'DL',
            'Tidak Diketahui' => 'TD',
            default => 'XX',
        };
    }
}
