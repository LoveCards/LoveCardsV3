<?php

namespace app\modules\Platform\Auth\Infrastructure;

use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\Key;
use Firebase\JWT\JWT as FBJWT;
use Firebase\JWT\SignatureInvalidException;
use app\shared\Infrastructure\Cache\CacheManager;
use think\facade\Cache;
use think\facade\Config;
use UnexpectedValueException;

final class Jwt
{
    public static function sign(array $data): string { $config = Config::get('jwt'); $privateKey = file_get_contents(app()->getRootPath() . $config['privateKey']); $payload = ['iss' => $config['iss'], 'iat' => time(), 'nbf' => time(), 'exp' => time() + $config['exp'], 'data' => $data]; $token = FBJWT::encode($payload, $privateKey, $config['alg']); CacheManager::set('jwt', 'token_' . $token, time(), $config['exp'] + $config['cacheTime']); return $token; }
    public static function verify(string $token): array { $config = Config::get('jwt'); $publicKey = file_get_contents(app()->getRootPath() . $config['publicKey']); try { $decoded = FBJWT::decode($token, new Key($publicKey, $config['alg'])); return (array) $decoded->data; } catch (ExpiredException $e) { $renewed = self::renew($token); if ($renewed === null) throw new \RuntimeException('token已失效'); return $renewed; } catch (SignatureInvalidException $e) { throw new \RuntimeException('签名不正确'); } catch (BeforeValidException $e) { throw new \RuntimeException('token未生效'); } catch (UnexpectedValueException $e) { throw new \RuntimeException('未知错误:' . $e->getMessage()); } }
    private static function renew(string $token): ?array { $key = 'token_' . $token; if (Cache::tag('jwt')->get($key) === null) return null; Cache::tag('jwt')->delete($key); $segments = explode('.', $token); $payload = FBJWT::jsonDecode(FBJWT::urlsafeB64Decode($segments[1])); $data = (array) $payload->data; $data['_new_token'] = self::sign($data); return $data; }
    public static function invalidate(string $token): void { Cache::tag('jwt')->delete('token_' . $token); }
    public static function verifyRsa($publicKey, $privateKey): bool { try { $config = Config::get('jwt'); $token = FBJWT::encode(['Test' => true], $privateKey, $config['alg']); return ((FBJWT::decode($token, new Key($publicKey, $config['alg']))->Test ?? null) === true); } catch (\Throwable) { return false; } }
}
