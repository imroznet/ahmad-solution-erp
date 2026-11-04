<?php

namespace App\Models;

use App\Company\VerificationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

#[Fillable([
    'company_profile_id',
    'document_category_id',
    'title',
    'document_number',
    'issuing_authority',
    'issued_on',
    'expires_on',
    'verification_status',
    'remarks',
    'created_by',
    'updated_by',
])]
class CompanyDocument extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'expires_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<CompanyProfile, $this>
     */
    public function companyProfile(): BelongsTo
    {
        return $this->belongsTo(CompanyProfile::class);
    }

    /**
     * @return BelongsTo<DocumentCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'document_category_id');
    }

    /**
     * @return HasMany<CompanyDocumentVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(CompanyDocumentVersion::class)->orderBy('version_number');
    }

    /**
     * @return HasOne<CompanyDocumentVersion, $this>
     */
    public function currentVersion(): HasOne
    {
        return $this->hasOne(CompanyDocumentVersion::class)->latestOfMany('version_number');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function statusLabel(): string
    {
        return VerificationStatus::label($this->verification_status);
    }

    public function expiryLabel(): string
    {
        $expires = $this->asDateValue('expires_on');

        if ($expires === null) {
            return 'No expiry';
        }

        $today = now()->timezone((string) config('app.timezone'))->startOfDay();

        if ($expires->lt($today)) {
            return 'Expired '.$expires->format('d M Y');
        }

        if ($expires->lte($today->copy()->addDays(60))) {
            return 'Expiring '.$expires->format('d M Y');
        }

        return 'Valid until '.$expires->format('d M Y');
    }

    private function asDateValue(string $attribute): ?Carbon
    {
        $value = $this->getAttribute($attribute);

        if ($value instanceof Carbon) {
            return $value->copy()->startOfDay();
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::parse($value)->startOfDay();
        }

        if (is_string($value) && $value !== '') {
            return Carbon::parse($value)->startOfDay();
        }

        return null;
    }
}
