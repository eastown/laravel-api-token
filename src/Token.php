<?php
/**
 * Created by PhpStorm.
 * User: eastown
 * Date: 2018/12/3
 * Time: 15:31
 */

namespace Eastown\ApiToken;


use Carbon\Carbon;
use Eastown\ApiToken\Models\Token as TokenModel;
use Eastown\ApiToken\Exceptions\TokenAuthException;

class Token
{
    private static $instances = [];

    private $settings = [];

    private $user = null;

    private $token = null;

    private function __construct($settingKey)
    {
        $this->setSettings($settingKey);
    }

    private function setUser($user)
    {
        $this->user = $user;
    }

    private function setToken($token)
    {
        $this->token = $token;
    }

    public function token()
    {
        return $this->token;
    }

    private function setSettings($settingKey)
    {
        $settings = config('api_token.' . $settingKey);

        if (!$settings) {
            throw new TokenAuthException(TokenAuthException::AUTH_SETTING);
        }

        $this->settings = $settings;

        return $this;
    }

    private function getSetting($key, $default = null)
    {
        return array_get($this->settings, $key, $default);
    }

    /**
     * @param $settingKey
     * @return Token
     */
    public static function guard($settingKey)
    {
        if (!array_get(static::$instances, $settingKey)) {
            array_set(static::$instances, $settingKey, new static($settingKey));
        }
        return array_get(static::$instances, $settingKey);
    }

    private function getModel()
    {
        $modelClass = $this->getSetting('model');
        return (new $modelClass)->with($this->getSetting('with', []));
    }

    private function findUser($credential)
    {
        $builder = $this->getModel();
        $user = null;

        if (!$user) {
            foreach ($this->getSetting('credentials', []) as $credentialField) {

                $users = (clone $builder)
                    ->where($credentialField, $credential)
                    ->get();

                if ($users->count() != 1) {
                    continue;
                }

                $user = $users->first();
                break;
            }
        }

        return $user;
    }

    private function verifyPassword($password, $user)
    {
        if (is_callable($this->getSetting('password_validator'))) {
            $valid = call_user_func_array($this->getSetting('password_validator'), [$password, $user]);
        } else {
            $valid = \Hash::check($password, $user->password);
        }

        return $valid;
    }

    public static function fingerprint()
    {
        return md5(request()->header('user-agent') . (ip2long(request()->getClientIp()) & ip2long('255.255.0.0')));
    }

    public function attempt($credential, $password, \Closure $permissionCheck = null, $life = 30)
    {
        $user = $this->findUser($credential);

        if (!$user) {
            throw new TokenAuthException(TokenAuthException::USER);
        }

        if (!$this->verifyPassword($password, $user)) {
            throw new TokenAuthException(TokenAuthException::PWD);
        }

        if ($permissionCheck && !call_user_func_array($permissionCheck, [$user])) {
            throw new TokenAuthException(TokenAuthException::PERMISSION);
        }

        $this->setToken($this->generate($user, $life));
        $this->setUser($user);
        $this->deleteInvalidToken(Carbon::now()->subDays(3));
        return $this->user();
    }

    public function generate($user, $life, $token = null)
    {
        is_null($token) and $token = uniqid(dechex(mt_rand()));

        if ($this->getSetting('sso')) {
            $user->apiToken()->whereStatus(TokenModel::STATUS_ACTIVE)
                ->update([
                    'status' => TokenModel::STATUS_FORBIDDEN
                ]);
        }

        $apiToken = $user->apiToken()->create([
            'fingerprint' => $this->fingerprint(),
            'token' => $token,
            'expire_at' => Carbon::now()->addMinutes($life)
        ]);

        return $apiToken;
    }

    public function deleteInvalidToken(Carbon $limitTime)
    {
        $this->user()->apiToken()
            ->where('created_at', '<=', $limitTime)
            ->where(function ($query) {
                $query->where('expire_at', '<=', Carbon::now())
                    ->orWhere('status', TokenModel::STATUS_FORBIDDEN);
            })->delete();
    }

    public function forget()
    {
        return $this->user()->apiToken()->update([
            'expire_at' => Carbon::now()
        ]);
    }

    public function auth($token)
    {
        $apiToken = TokenModel::whereToken($token)->first();

        if (!$apiToken) {
            throw new TokenAuthException(TokenAuthException::TOKEN);
        }

        if ($apiToken->status == TokenModel::STATUS_FORBIDDEN) {
            throw new TokenAuthException(TokenAuthException::SINGLE_TOKEN);
        }

        if ($apiToken->expire_at < Carbon::now()) {
            throw new TokenAuthException(TokenAuthException::TOKEN_EXPIRED);
        }

        if ($this->getSetting('verify_fingerprint') && $apiToken->fingerprint != $this->fingerprint()) {
            throw new TokenAuthException(TokenAuthException::FINGERPRINT);
        }

        $user = $this->getModel()->find($apiToken->tokenable_id);

        if (!$user) {
            throw new TokenAuthException(TokenAuthException::USER);
        }

        $this->setToken($apiToken);
        $this->setUser($user);

        return $user;
    }

    public function user()
    {
        if (!$this->user) {
            throw new TokenAuthException(TokenAuthException::NOT_AUTH);
        }

        return $this->user;
    }

    public function extendLife($life)
    {
        $this->user()->apiToken()->update([
            'expire_at' => Carbon::now()->addMinutes($life)
        ]);
    }
}