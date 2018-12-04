<?php
/**
 * Created by PhpStorm.
 * User: eastown
 * Date: 2018/12/3
 * Time: 16:15
 */

namespace Eastown\ApiToken\Models;


use Illuminate\Database\Eloquent\Model;

class Token extends Model
{
    const STATUS_ACTIVE = 200;

    const STATUS_FORBIDDEN = 400;

    protected $table = 'api_tokens';

    protected $fillable = ['token', 'expire_at', 'fingerprint'];

    protected $hidden = ['id', 'fingerprint', 'tokenable_type', 'tokenable_id', 'status', 'created_at', 'updated_at'];

    protected $dates = [
        'expire_at'
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function($model){
            $model->status or $model->status = self::STATUS_ACTIVE;
        });
    }

    public function tokenable()
    {
        return $this->morphTo();
    }
}