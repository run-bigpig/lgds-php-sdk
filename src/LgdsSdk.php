<?php
/**
 * Date: 2022/12/8
 * Time: 17:14
 */
const SDK_VERSION = '1.0.0';

/**
 * 数据格式错误异常
 */
class LgdsDataException extends \Exception
{
}

/**
 * 网络异常
 */
class LgdsNetWorkException extends \Exception
{
}

class LgdsSdk
{
    private $consumer;
    private $publicProperties;

    function __construct($consumer)
    {
        $this->consumer = $consumer;
        $this->clear_public_properties();
    }

    public function User($device_id, $user_id, $app_name, $platform, $server, $properties)
    {
        return $this->add($device_id, $user_id, $app_name, $platform, $server, "user", "user", "insert", $properties);
    }

    public function UserUpdate($device_id, $user_id, $app_name, $platform, $server, $properties)
    {
        return $this->add($device_id, $user_id, $app_name, $platform, $server, "user", "user", "update", $properties);
    }

    public function Track($device_id, $user_id, $app_name, $platform, $server, $event_name, $properties)
    {
        return $this->add($device_id, $user_id, $app_name, $platform, $server, $event_name, "track", "insert", $properties);
    }

    private function add($device_id, $user_id, $app_name, $platform, $server, $event_name, $data_type, $action, $properties)
    {
        $event = ["#device_id" => "default", "#user_id" => "default", "#app_name" => "default", "#platform" => "ios", "#server" => 1];
        if (!is_null($event_name) && !is_string($event_name)) {
            throw new LgdsDataException("event_name必须是一个字符串");
        }
        if (!in_array($data_type, ["user", "track"])) {
            throw new LgdsDataException("data_type必须是 user或者track");
        }
        if (!in_array($action, ["insert", "update"])) {
            throw new LgdsDataException("action必须是 insert或者update");
        }
        if (!$device_id && !$user_id) {
            throw new LgdsDataException("device_id 和 user_id 不能同时为空");
        }
        if ($device_id) {
            $event['#device_id'] = $device_id;
        }
        if ($user_id) {
            $event['#user_id'] = $user_id;
        }
        if ($app_name) {
            $event['#app_name'] = $app_name;
        }
        if ($platform) {
            $event['##platform'] = $platform;
        }
        if ($server) {
            $event['#server'] = $server;
        }
        if ($event_name) {
            $event['#event_name'] = $event_name;
        }
        if ($data_type) {
            $event["#type"] = $data_type;
        }
        if ($action) {
            $event["#action"] = $action;
        }
        $properties = $this->merge_public_properties($properties);
        $event['#time'] = $this->getUtcTime();

        //检查properties
        $properties = $this->assertProperties($properties);
        if (count($properties) > 0) {
            $event['#properties'] = $properties;
        }
        return $this->consumer->send($event);
    }

    private function assertProperties(&$properties)
    {
        // 检查 properties
        if (is_array($properties)) {
            $name_pattern = "/^(#|[a-z])[a-z0-9_]{0,49}$/i";
            if (!$properties) {
                return $properties;
            }
            foreach ($properties as $key => &$value) {
                if (is_null($value)) {
                    continue;
                }
                if (!is_string($key)) {
                    throw new LgdsDataException("property key must be a str. [key=$key]");
                }
                if (strlen($key) > 50) {
                    throw new LgdsDataException("the max length of property key is 50. [key=$key]");
                }
                if (!preg_match($name_pattern, $key)) {
                    throw new LgdsDataException("property key must be a valid variable name. [key='$key']]");
                }
                if (!is_scalar($value) && !is_array($value)) {
                    throw new LgdsDataException("property value must be a str/int/float/array. [key='$key']");
                }
            }
        } else {
            throw new LgdsDataException("property must be an array.");
        }
        return $properties;
    }

    private function getUtcTime()
    {
        date_default_timezone_set("UTC");
        return date("Y-m-d H:i:s", time());
    }

    private function extractStringProperty($key, &$properties = array())
    {
        if (array_key_exists($key, $properties)) {
            $value = $properties[$key];
            unset($properties[$key]);
            return $value;
        }
        return '';
    }

    /**
     * 清空公共属性
     */
    public function clear_public_properties()
    {
        $this->publicProperties = array();
    }

    /**
     * 设置每个事件都带有的一些公共属性
     *
     * @param array $super_properties 公共属性
     */
    public function register_public_properties($super_properties)
    {
        $this->publicProperties = array_merge($this->publicProperties, $super_properties);
    }

    public function merge_public_properties($properties)
    {
        foreach ($this->publicProperties as $key => $value) {
            if (!isset($properties[$key])) {
                $properties[$key] = $value;
            }
        }
        return $properties;
    }


    /**
     * 立即刷新
     */
    public function flush()
    {
        $this->consumer->flush();
    }

    /**
     * 关闭 sdk 接口
     */
    public function close()
    {
        $this->consumer->close();
    }

}

abstract class AbstractConsumer
{
    /**
     * 发送一条消息, 返回true为send成功。
     * @param string $message 发送的消息体
     * @return bool
     */
    public abstract function send($message);

    /**
     * 立即发送所有未发出的数据。
     * @return bool
     */
    public function flush()
    {
        return true;
    }

    /**
     * 关闭 Consumer 并释放资源。
     * @return bool
     */
    public abstract function close();
}

class DataConsumer extends AbstractConsumer
{
    private $url;
    private $appid;
    private $accessKey;
    private $secretKey;
    private $buffers;
    private $maxSize;
    private $requestTimeout;
    private $retryTimes;
    private $isThrowException = false;
    private $cacheBuffers;
    private $cacheCapacity;

    /**
     * 创建给定配置的 BatchConsumer 对象
     * @param string $server_url 接收端 url
     * @param string $appid 项目 APP ID
     * @param int $max_size 最大的 flush 值，默认为 20
     * @param int $retryTimes 因网络问题发生失败时重试次数，默认为 3次
     * @param int $request_timeout http 的 timeout，默认 1000s
     * @param int $cache_capacity 最大缓存倍数，实际存储量为$max_size * $cache_multiple
     */
    function __construct($server_url, $appid, $accessKey, $secretKey, $max_size = 20, $retryTimes = 3, $request_timeout = 1000, $cache_capacity = 50)
    {
        $this->buffers = array();
        $this->appid = $appid;
        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;
        $this->maxSize = $max_size;
        $this->retryTimes = $retryTimes;
        $this->requestTimeout = $request_timeout;
        $parsed_url = parse_url($server_url);
        $this->cacheBuffers = array();
        $this->cacheCapacity = $cache_capacity;
        if ($parsed_url === false) {
            throw new LgdsDataException("Invalid server url");
        }
        $this->url = $parsed_url['scheme'] . "://" . $parsed_url['host']
            . ((isset($parsed_url['port'])) ? ':' . $parsed_url['port'] : '')
            . '/logagent';
    }

    public function __destruct()
    {
        $this->flush();
    }

    private function signature($salt)
    {
        date_default_timezone_set("UTC");
        $timestr = date("Y-m-d H:i", time());
        return hash("sha256", sprintf("%s%s%s%s", $this->accessKey, $this->secretKey, $salt, $timestr));
    }

    private function salt($len = 25)
    {
        $str = "";
        $keys = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
        $max = strlen($keys) - 1;
        for ($i = 0; $i < $len; $i++) {
            $str .= $keys[rand(0, $max)];
        }
        return $str;
    }

    public function send($message)
    {
        $this->buffers[] = $message;
        if (count($this->buffers) >= $this->maxSize) {
            Logger::log("触发数据上报");
            return $this->flush();
        } else {
            Logger::log("加入缓存：{$message}");
            return true;
        }
    }

    public function flush($flag = false)
    {
        if (empty($this->buffers) && empty($this->cacheBuffers)) {
            return true;
        }
        if ($flag || count($this->buffers) >= $this->maxSize || count($this->cacheBuffers) == 0) {
            $sendBuffers = $this->buffers;
            $this->buffers = array();
            $this->cacheBuffers[] = $sendBuffers;
        }
        while (count($this->cacheBuffers) > 0) {
            $sendBuffers = $this->cacheBuffers[0];
            try {
                $this->doRequest($sendBuffers);
                array_shift($this->cacheBuffers);
                if ($flag) {
                    continue;
                }
                break;
            } catch (LgdsNetWorkException $netWorkException) {
                if (count($this->cacheBuffers) > $this->cacheCapacity) {
                    array_shift($this->cacheBuffers);
                }

                if ($this->isThrowException) {
                    throw $netWorkException;
                }
                return false;
            } catch (LgdsDataException $dataException) {
                array_shift($this->cacheBuffers);
                if ($this->isThrowException) {
                    throw $dataException;
                }
                return false;
            }
        }

        return true;
    }

    public function close()
    {
        $this->flush(true);
    }


    public function setFlushSize($max_size = 20)
    {
        $this->maxSize = $max_size;
    }

    public function openThrowException()
    {
        $this->isThrowException = true;
    }

    private function doRequest($message_array)
    {
        $ch = curl_init($this->url);
        //参数设置
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6000);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->requestTimeout);
        $data = json_encode(["data" => $message_array]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        //headers
        $salt = $this->salt();
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("lgds-sdk-type:php", "lgds-sdk-version:" . SDK_VERSION, "AppId:" . $this->appid, "Salt:" . $salt, "Signature:" . $this->signature($salt), 'Content-Type: application/json'));

        //https
        $pos = strpos($this->url, "https");
        if ($pos === 0) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }

        //发送请求
        $curreyRetryTimes = 0;
        while ($curreyRetryTimes++ < $this->retryTimes) {
            $result = curl_exec($ch);
            Logger::log("返回值：{$result}");
            if (!$result) {
                echo new LgdsNetWorkException("Cannot post message to server , error --> " . curl_error(($ch)));
                continue;
            }
            $curl_info = curl_getinfo($ch);

            curl_close($ch);
            if ($curl_info['http_code'] == 200) {
                return;
            } else {
                //解析返回值
                $json = json_decode($result, true);
                echo new LgdsNetWorkException("传输数据失败  message: " . $json["message"]);
            }
        }
        throw new LgdsNetWorkException("传输数据重试" . $this->retryTimes . "次后仍然失败！");
    }
}

class Logger
{
    static function log()
    {
        $params = implode("", func_get_args());
        $time = date("Y-m-d H:i:s", time());
        echo "[LGDS][{$time}]: ", $params, PHP_EOL;
    }
}