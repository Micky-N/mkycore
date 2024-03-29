<?php

namespace MkyCore\Api;

use Exception;
use MkyCore\Abstracts\Entity;
use MkyCore\Database;
use MkyCore\Exceptions\Container\FailedToResolveContainerException;
use MkyCore\Exceptions\Container\NotInstantiableContainerException;
use MkyCore\Traits\HasJwToken;
use ReflectionException;
use RuntimeException;

class JWT
{

    private const HEADER = ['typ' => 'Jwt', 'alg' => 'HS256'];

    /**
     * Create token in database
     *
     * @throws ReflectionException
     */
    public static function createJwt(Entity $entity, string $name): NewJsonWebToken
    {
        if (!in_array(HasJwToken::class, class_uses($entity))) {
            throw new RuntimeException(sprintf('The class %s must use HasJwt trait', get_class($entity)));
        }
        $expireTime = time() + (60 * (float)config('jwt.lifetime', 1));
        $payload = self::makePayload($entity, $expireTime);

        $base64UrlHeader = self::toBase64Url(json_encode(self::HEADER));
        $base64UrlPayload = self::toBase64Url(json_encode($payload));

        $signature = self::makeSignature($base64UrlHeader, $base64UrlPayload, env('API_SECRET', $expireTime));

        $base64UrlSecurity = self::toBase64Url($signature);

        $jsonWebToken = $entity->tokens()->add(new JsonWebToken([
            'entity' => Database::stringifyEntity($entity),
            'name' => $name,
            'token' => $base64UrlSecurity,
            'expiresAt' => $expireTime,
            'createdAt' => now()->format('Y-m-d H:i:s')
        ]));

        return new NewJsonWebToken($jsonWebToken, $base64UrlPayload);
    }

    /**
     * Set the payload
     *
     * @param Entity $entity
     * @param int $expireTime
     * @return array
     * @throws ReflectionException
     */
    private static function makePayload(Entity $entity, int $expireTime): array
    {
        $primaryKey = $entity->getPrimaryKey();
        $defaultPayload = [
            'iat' => time(),
            'entity' => Database::stringifyEntity($entity),
            'id' => $entity->{$primaryKey}(),
            'expiresAt' => $expireTime,
        ];
        $customPayload = [];

        if (method_exists($entity, 'payload')) {
            $customPayload = $entity->payload();
        }

        return array_replace_recursive($defaultPayload, $customPayload);
    }

    /**
     * Encode input to Base64Url
     *
     * @param string $input
     * @return string
     */
    private static function toBase64Url(string $input): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($input));
    }

    /**
     * Make signature
     *
     * @param string $base64UrlHeader
     * @param string $base64UrlPayload
     * @param string $secret
     * @return string
     */
    private static function makeSignature(string $base64UrlHeader, string $base64UrlPayload, string $secret): string
    {
        return hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, base64_encode($secret), true);
    }

    /**
     * Check if token is valid
     *
     * @param string $jwt
     * @return bool
     * @throws Exception
     */
    public static function verifyJwt(string $jwt): bool
    {
        if (!($jwtEntity = self::retrieveJwt($jwt))) {
            return false;
        }
        return ($jwtEntity->expiresAt() - time()) > 0;
    }

    /**
     * Retrieve token entity
     *
     * @param string $jwt
     * @return JsonWebToken|false
     * @throws Exception
     */
    public static function retrieveJwt(string $jwt): JsonWebToken|false
    {
        // split the jwt
        $payload = json_decode(base64_decode($jwt));
        $secret = env('API_SECRET', $payload->expireAt);

        $base64UrlHeader = self::toBase64Url(json_encode(self::HEADER));
        $signature = self::makeSignature($base64UrlHeader, $jwt, $secret);

        $base64UrlSecurity = self::toBase64Url($signature);

        return self::jsonWebTokenManager()->where('token', $base64UrlSecurity)->first();
    }

    /**
     * Get JsonWebTokenManager
     *
     * @return JsonWebTokenManager
     * @throws ReflectionException
     * @throws FailedToResolveContainerException
     * @throws NotInstantiableContainerException
     */
    private static function jsonWebTokenManager(): JsonWebTokenManager
    {
        return app()->get(JsonWebTokenManager::class);
    }

    /**
     * Delete token
     *
     * @param string $jwt
     * @return JsonWebToken|false
     * @throws Exception
     */
    public static function revokeJwt(string $jwt): JsonWebToken|false
    {
        $jwtEntity = self::retrieveJwt($jwt);
        if (!$jwtEntity) {
            return false;
        }
        try {
            /** @var JsonWebToken $res */
            $res = self::jsonWebTokenManager()->delete($jwtEntity);
            return $res;
        } catch (Exception $exception) {
            return false;
        }
    }
}