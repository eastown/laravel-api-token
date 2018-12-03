<?php

namespace Eastown\ApiToken;

use Eastown\ApiToken\Models\Token;

trait TokenTrait
{
    public function apiToken()
    {
        return $this->morphOne(Token::class, 'tokenable');
    }
}