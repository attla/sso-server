<?php

namespace Attla\SSO;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Attla\SSO\Models\ClientProvider;
use Illuminate\Contracts\Auth\Authenticatable;

class Resolver extends \Attla\Encrypter
{
    /**
     * Detect the provider from request
     *
     * @return ClientProvider|false
     */
    public static function resolveClientProvider(Request $request)
    {
        $host = static::host($request->header('referer') ?: $request->get('client'));

        if ($host and $clientProvider = ClientProvider::where('host', $host)->first()) {
            return $clientProvider;
        }

        return false;
    }

    /**
     * Format a referer host
     *
     * @param string $host
     * @return string
     */
    public static function host($host)
    {
        if (!Str::startsWith($host, 'http')) {
            $host = 'http://' . $host;
        }

        $parseUrl = parse_url($host);
        $host = '';

        !empty($parseUrl['host']) && $host .= $parseUrl['host'];

        return $host;
    }

    /**
     * Get client provider token
     *
     * @return string|false
     */
    public static function getClientProviderToken(Request $request)
    {
        $clientProvider = static::resolveClientProvider($request);

        if ($clientProvider) {
            return static::token([
                'secret' => $clientProvider->secret,
                'callback' => $clientProvider->callback,
            ]);
        }

        return false;
    }

    /**
     * Create a sso sign token
     *
     * @param array $credentials
     * @return string
     */
    protected static function token(array $credentials)
    {
        return \Jwt::sign($credentials, 7200);
    }

    /**
     * Get client provider callback
     *
     * @return array
     */
    public static function getClientProviderCallback(Request $request)
    {
        $clientProvider = static::resolveClientProvider($request);

        if ($clientProvider) {
            return $clientProvider->host;
        }

        return false;
    }

    /**
     * Resolve callback url
     *
     * @param string $token
     * @param Authenticatable $user
     * @return string
     */
    public static function callback($token, Authenticatable $user)
    {
        $token = \Jwt::decode($token);

        if (
            !$token
            || empty($token->callback)
            || empty($token->secret)
        ) {
            return false;
        }

        return rtrim($token->callback, '/') . '?token=' . static::userToken($user, $token->secret);
    }

    /**
     * Create a sso user token
     *
     * @param Authenticatable $user
     * @param string $clientSecret
     * @return string
     */
    protected static function userToken(Authenticatable $user, string $clientSecret)
    {
        $config = config();
        $oldSecret = $config->get('encrypt.secret');
        $config->set('encrypt.secret', $clientSecret);

        $user = $user->toArray();

        if (!empty($userData['password'])) {
            unset($userData['password']);
        }

        $token = \Jwt::encode($user);
        $config->set('encrypt.secret', $oldSecret);

        return $token;
    }
}
