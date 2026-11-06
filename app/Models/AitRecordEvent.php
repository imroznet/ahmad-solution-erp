<?php

namespace App\Models;

use App\Models\Concerns\RejectsHistoryChanges;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['ait_record_id', 'event', 'actor_id', 'remarks'])]
class AitRecordEvent extends Model
{
    use RejectsHistoryChanges;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<AitRecord, $this>
     */
    public function record(): BelongsTo
    {
        return $this->belongsTo(AitRecord::class, 'ait_record_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
