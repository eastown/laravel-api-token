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

    private $setting = [];

    private $user = null;

    private $token = null;

    private function __construct($settingKey)
    {
        $this->setSetting($settingKey);
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

    private function setSetting($settingKey)
    {
        $setting = config('api_token.' . $settingKey);

        if (!$setting) {
            throw new TokenAuthException(TokenAuthException::AUTH_SETTING);
        }

        $this->setting = $setting;

        return $this;
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
        return (new $this->setting['model'])->with($this->setting['with']);
    }

    private function findUser($credential)
    {
        $builder = $this->getModel();
        $user = null;

        if (!$user) {
            foreach ($this->setting['credentials'] as $credentialField) {

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
        if (isset($this->setting['password_validator']) && is_callable($this->setting['password_validator'])) {
            $valid = call_user_func_array($this->setting['password_validator'], [$password, $user]);
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

        return $this->user();
    }

    public function generate($user, $life, $token = null)
    {
        is_null($token) and $token = uniqid(dechex(mt_rand()));

        $attributes = [
            'fingerprint' => $this->fingerprint(),
            'token' => $token,
            'expire_at' => Carbon::now()->addMinutes($life)
        ];

        $apiToken = $user->apiToken()->firstOrNew([]);

        $apiToken->fill($attributes)->save();

        return $apiToken;
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

        if ($apiToken->expire_at < Carbon::now()) {
            throw new TokenAuthException(TokenAuthException::TOKEN_EXPIRED);
        }

        if ($this->setting['verify_fingerprint'] && $apiToken->fingerprint != $this->fingerprint()) {
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