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
    protected $table = 'api_tokens';

    protected $fillable = ['token', 'expire_at', 'fingerprint'];

    protected $hidden = ['id', 'fingerprint', 'tokenable_type', 'tokenable_id', 'created_at', 'updated_at'];

    protected $dates = [
        'expire_at'
    ];

    public function tokenable()
    {
        return $this->morphTo();
    }
}