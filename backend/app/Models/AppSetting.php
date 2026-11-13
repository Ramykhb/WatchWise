<?php

namespace App\Models;

use Database\Factories\AppSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The stored value of one app-wide setting. Only the Setting module's repository writes it; the value is whatever
 * was stored, so reading it goes through `SettingKey::resolve()`, which falls back to the default for garbage.
 */
class AppSetting extends Model
{
    /** @use HasFactory<AppSettingFactory> */
    use HasFactory;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    /** The admin who last changed it, or null once their account is deleted. */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
