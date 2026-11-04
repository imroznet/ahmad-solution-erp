<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'party_id',
    'name',
    'designation',
    'department',
    'email',
    'phone',
    'alternative_phone',
    'whatsapp_number',
    'is_primary',
    'is_active',
    'notes',
    'created_by',
    'updated_by',
])]
class PartyContact extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function auditSnapshot(): array
    {
        return [
            'name' => $this->text('name'),
            'designation' => $this->text('designation'),
            'department' => $this->text('department'),
            'email' => $this->text('email'),
            'phone' => $this->text('phone'),
            'alternative_phone' => $this->text('alternative_phone'),
            'whatsapp_number' => $this->text('whatsapp_number'),
            'is_primary' => (bool) $this->is_primary,
            'is_active' => (bool) $this->is_active,
            'notes' => $this->text('notes'),
        ];
    }

    private function text(string $key): ?string
    {
        $value = $this->getAttribute($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
