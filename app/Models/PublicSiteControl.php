<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicSiteControl extends Model
{
    protected $fillable = [
        'maintenance_enabled',
        'contact_form_enabled',
        'recaptcha_enabled',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'maintenance_enabled' => 'boolean',
            'contact_form_enabled' => 'boolean',
            'recaptcha_enabled' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
