<?php
/**
 * sysPass
 *
 * @author    nuxsmin
 * @link      https://syspass.org
 * @copyright 2012-2018, Rubén Domínguez nuxsmin@$syspass.org
 *
 * This file is part of sysPass.
 *
 * sysPass is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * sysPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 *  along with sysPass.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace SP\Util;

use Defuse\Crypto\Core;
use Defuse\Crypto\Encoding;
use SP\Bootstrap;
use SP\Config\ConfigData;
use SP\Core\Exceptions\SPException;
use SP\Core\PhpExtensionChecker;

defined('APP_ROOT') || die();

/**
 * Clase con utilizades para la aplicación
 */
final class Util
{
    /**
     * Generar una clave aleatoria
     *
     * @param int  $length     Longitud de la clave
     * @param bool $useNumbers Usar números
     * @param bool $useSpecial Usar carácteres especiales
     * @param bool $checKStrength
     *
     * @return string
     */
    public static function randomPassword($length = 16, $useNumbers = true, $useSpecial = true, $checKStrength = true)
    {
        $charsLower = 'abcdefghijklmnopqrstuwxyz';
        $charsUpper = 'ABCDEFGHIJKLMNOPQRSTUWXYZ';

        $alphabet = $charsLower . $charsUpper;

        if ($useSpecial === true) {
            $charsSpecial = '@$%&/()!_:.;{}^';
            $alphabet .= $charsSpecial;
        }

        if ($useNumbers === true) {
            $charsNumbers = '0123456789';
            $alphabet .= $charsNumbers;
        }

        /**
         * @return array
         */
        $passGen = function () use ($alphabet, $length) {
            $pass = [];
            $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache

            for ($i = 0; $i < $length; $i++) {
                $n = mt_rand(0, $alphaLength);
                $pass[] = $alphabet[$n];
            }

            return $pass;
        };

        if ($checKStrength === true) {
            do {
                $pass = $passGen();
                $strength = ['lower' => 0, 'upper' => 0, 'special' => 0, 'number' => 0];

                foreach ($pass as $char) {
                    if (strpos($charsLower, $char) !== false) {
                        $strength['lower']++;
                    } elseif (strpos($charsUpper, $char) !== false) {
                        $strength['upper']++;
                    } elseif ($useSpecial === true && strpos($charsSpecial, $char) !== false) {
                        $strength['special']++;
                    } elseif ($useNumbers === true && strpos($charsNumbers, $char) !== false) {
                        $strength['number']++;
                    }
                }

                if ($useSpecial === false) {
                    unset($strength['special']);
                }

                if ($useNumbers === false) {
                    unset($strength['number']);
                }
            } while (in_array(0, $strength, true));

            return implode($pass);
        }

        return implode($passGen());
    }

    /**
     * Generar una cadena aleatoria usuando criptografía.
     *
     * @param int $length opcional, con la longitud de la cadena
     *
     * @return string
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     */
    public static function generateRandomBytes($length = 30)
    {
        return Encoding::binToHex(Core::secureRandom($length));
    }

    /**
     * Obtener datos desde una URL usando CURL
     *
     * @param string    $url
     * @param array     $data
     * @param bool|null $useCookie
     * @param bool      $weak
     *
     * @return bool|string
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     *
     * TODO: Use Guzzle
     *
     * @throws \SP\Core\Exceptions\CheckException
     * @throws SPException
     */
    public static function getDataFromUrl($url, array $data = null, $useCookie = false, $weak = false)
    {
        /** @var ConfigData $ConfigData */
        $ConfigData = Bootstrap::getContainer()->get(ConfigData::class);

        Bootstrap::getContainer()->get(PhpExtensionChecker::class)->checkCurlAvailable(true);

        $ch = curl_init($url);

        if ($ConfigData->isProxyEnabled()) {
            curl_setopt($ch, CURLOPT_PROXY, $ConfigData->getProxyServer());
            curl_setopt($ch, CURLOPT_PROXYPORT, $ConfigData->getProxyPort());
            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);

            $proxyUser = $ConfigData->getProxyUser();

            if ($proxyUser) {
                $proxyAuth = $proxyUser . ':' . $ConfigData->getProxyPass();
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxyAuth);
            }
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'sysPass-App');
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        if ($weak === true) {
            // Trust SSL enabled server
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        }

        if (null !== $data) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $data['type']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data['data']);
        }

        if ($useCookie) {
            $cookie = self::getUserCookieFile();

            if ($cookie) {
                if (!SessionFactory::getCurlCookieSession()) {
                    curl_setopt($ch, CURLOPT_COOKIESESSION, true);
                    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);

                    SessionFactory::setCurlCookieSession(true);
                }

                curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
            }
        }

        $data = curl_exec($ch);

        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($data === false || $httpStatus !== 200) {
            throw new SPException(curl_error($ch), SPException::WARNING);
        }

        return $data;
    }

    /**
     * Devuelve el nombre de archivo a utilizar para las cookies del usuario
     *
     * @return string|false
     */
    public static function getUserCookieFile()
    {
        $tempDir = self::getTempDir();

        return $tempDir ? $tempDir . DIRECTORY_SEPARATOR . md5('syspass-' . SessionFactory::getUserData()->getLogin()) : false;
    }

    /**
     * Comprueba y devuelve un directorio temporal válido
     *
     * @return bool|string
     */
    public static function getTempDir()
    {
        $sysTmp = sys_get_temp_dir();
        $appTmp = APP_PATH . DIRECTORY_SEPARATOR . 'temp';
        $file = 'syspass.test';

        $checkDir = function ($dir) use ($file) {
            if (file_exists($dir . DIRECTORY_SEPARATOR . $file)) {
                return $dir;
            }

            if (is_dir($dir) || @mkdir($dir)) {
                if (touch($dir . DIRECTORY_SEPARATOR . $file)) {
                    return $dir;
                }
            }

            return false;
        };

        if ($checkDir($appTmp)) {
            return $appTmp;
        }

        return $checkDir($sysTmp);
    }

    /**
     * Devuelve información sobre la aplicación.
     *
     * @param string $index con la key a devolver
     *
     * @return array|string con las propiedades de la aplicación
     */
    public static function getAppInfo($index = null)
    {
        $appinfo = [
            'appname' => 'sysPass',
            'appdesc' => 'Systems Password Manager',
            'appalias' => 'SPM',
            'appwebsite' => 'https://www.syspass.org',
            'appblog' => 'https://www.cygnux.org',
            'appdoc' => 'https://doc.syspass.org',
            'appupdates' => 'https://api.github.com/repos/nuxsmin/sysPass/releases/latest',
            'appnotices' => 'https://api.github.com/repos/nuxsmin/sysPass/issues?milestone=none&state=open&labels=Notices',
            'apphelp' => 'https://github.com/nuxsmin/sysPass/issues',
            'appchangelog' => 'https://github.com/nuxsmin/sysPass/blob/master/CHANGELOG'
        ];

        if (null !== $index && isset($appinfo[$index])) {
            return $appinfo[$index];
        }

        return $appinfo;
    }

    /**
     * Realiza el proceso de logout.
     *
     * FIXME
     */
    public static function logout()
    {
        exit('<script>sysPassApp.actions().main.logout();</script>');
    }

    /**
     * Obtener el tamaño máximo de subida de PHP.
     */
    public static function getMaxUpload()
    {
        $max_upload = (int)ini_get('upload_max_filesize');
        $max_post = (int)ini_get('post_max_size');
        $memory_limit = (int)ini_get('memory_limit');

        return min($max_upload, $max_post, $memory_limit);
    }

    /**
     * Checks a variable to see if it should be considered a boolean true or false.
     * Also takes into account some text-based representations of true of false,
     * such as 'false','N','yes','on','off', etc.
     *
     * @author Samuel Levy <sam+nospam@samuellevy.com>
     *
     * @param mixed $in     The variable to check
     * @param bool  $strict If set to false, consider everything that is not false to
     *                      be true.
     *
     * @return bool The boolean equivalent or null (if strict, and no exact equivalent)
     */
    public static function boolval($in, $strict = false)
    {
        $in = is_string($in) ? strtolower($in) : $in;

        // if not strict, we only have to check if something is false
        if (in_array($in, ['false', 'no', 'n', '0', 'off', false, 0], true) || !$in) {
            return false;
        }

        if ($strict && in_array($in, ['true', 'yes', 'y', '1', 'on', true, 1], true)) {
            return true;
        }

        // not strict? let the regular php bool check figure it out (will
        // largely default to true)
        return ($in ? true : false);
    }

    /**
     * Cast an object to another class, keeping the properties, but changing the methods
     *
     * @param string        $dstClass Class name
     * @param string|object $serialized
     * @param string        $srcClass Nombre de la clase serializada
     *
     * @return mixed
     * @link http://blog.jasny.net/articles/a-dark-corner-of-php-class-casting/
     */
    public static function unserialize($dstClass, $serialized, $srcClass = null)
    {
        if (!is_object($serialized)) {
            preg_match('/^O:\d+:"(?P<class>[^"]++)"/', $serialized, $matches);

            if (class_exists($matches['class'])
                && $matches['class'] === $dstClass
            ) {
                return unserialize($serialized);
            }

            // Si se indica la clase origen, se elimina el nombre
            // de la clase en los métodos privados
            if ($srcClass !== null) {
                $serializedOut = preg_replace_callback(
                    '/:\d+:"\x00' . preg_quote($srcClass, '/') . '\x00(\w+)"/',
                    function ($matches) {
                        return ':' . strlen($matches[1]) . ':"' . $matches[1] . '"';
                    },
                    $serialized);

                return self::castToClass($serializedOut, $dstClass);
            }

            if ($matches['class'] !== $dstClass) {
                return self::castToClass($serialized, $dstClass);
            }
        }

        return $serialized;
    }

    /**
     * Cast an object to another class
     *
     * @param $object
     * @param $class
     *
     * @return mixed
     */
    public static function castToClass($object, $class)
    {
        // should avoid '__PHP_Incomplete_Class'?

        if (is_object($object)) {
            return unserialize(preg_replace('/^O:\d+:"[^"]++"/', 'O:' . strlen($class) . ':"' . $class . '"', serialize($object)));
        }

        return unserialize(preg_replace('/^O:\d+:"[^"]++"/', 'O:' . strlen($class) . ':"' . $class . '"', $object));
    }

    /**
     * Bloquear la aplicación
     *
     * @param int    $userId
     * @param string $subject
     *
     * @return bool
     */
    public static function lockApp($userId, $subject)
    {
        $data = ['time' => time(), 'userId' => (int)$userId, 'subject' => $subject];

        return file_put_contents(LOCK_FILE, json_encode($data));
    }

    /**
     * Desbloquear la aplicación
     *
     * @return bool
     */
    public static function unlockApp()
    {
        return @unlink(LOCK_FILE);
    }

    /**
     * Comprueba si la aplicación está bloqueada
     *
     * @return int
     */
    public static function getAppLock()
    {
        if (file_exists(LOCK_FILE)
            && ($data = file_get_contents(LOCK_FILE)) !== false
        ) {
            return json_decode($data) ?: false;
        }

        return false;
    }

    /**
     * Devolver el tiempo aproximado en segundos de una operación
     *
     * @param $startTime
     * @param $numItems
     * @param $totalItems
     *
     * @return array Con el tiempo estimado y los elementos por segundo
     */
    public static function getETA($startTime, $numItems, $totalItems)
    {
        if ($numItems > 0 && $totalItems > 0) {
            $runtime = time() - $startTime;
            $eta = (int)((($totalItems * $runtime) / $numItems) - $runtime);

            return [$eta, $numItems / $runtime];
        }

        return [0, 0];
    }
}
