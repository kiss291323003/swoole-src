<?php

use Swoole\Coroutine\Http\Client;

class swoole_curl_exception extends swoole_exception
{
}


class swoole_curl_resource
{

    protected $allowOptions = [
        CURLOPT_HTTPHEADER, CURLOPT_URL, CURLOPT_PORT, CURLOPT_CONNECTTIMEOUT, CURLOPT_POSTFIELDS,
        CURLOPT_SSL_VERIFYPEER, CURLOPT_ENCODING, CURLOPT_CUSTOMREQUEST,CURLOPT_HEADERFUNCTION,CURLOPT_READFUNCTION,
        CURLOPT_FILE,CURLOPT_RETURNTRANSFER,CURLOPT_SSL_VERIFYHOST,CURLOPT_HTTP_VERSION,CURLOPT_HEADER
    ];

    protected $options = [];

    protected $result = [];

    protected $client;

    function __construct()
    {
        $this->options[CURLOPT_HTTPHEADER] = [
            "User-Agent" => 'SwooleHttpClient/0.1',
            'Accept' => 'text/html,application/xhtml+xml,application/xml',
            'Accept-Encoding' => 'gzip',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'no-cache'
        ];
    }

    public function __rest()
    {
        $this->__close();
        $this->options = [];
        $this->options[CURLOPT_HTTPHEADER] = [
            "User-Agent" => 'EasySwooleHttpClient/0.1',
            'Accept' => 'text/html,application/xhtml+xml,application/xml',
            'Accept-Encoding' => 'gzip',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'no-cache'
        ];
        $this->result = [];
    }

    public function __setOption(int $option, $val): bool
    {
        if (in_array($option, $this->options)) {
            $this->options[$option] = $val;
            return;
        } else {
            return false;
        }
    }

    public function __getOptions(): array
    {
        return $this->options;
    }

    public function __setOptions(array $options)
    {
        $this->options = $options;
    }

    public function __getResult(): array
    {
        return $this->result;
    }

    public function __exec()
    {
        if (empty($this->options[CURLOPT_URL])) {
            trigger_error('URL is empty');
            return null;
        }
        $url = $this->options[CURLOPT_URL];
        $urlInfo = parse_url($url);
        $ssl = false;
        if (isset($urlInfo['port'])) {
            $port = $urlInfo['port'];
        } else {
            if ($urlInfo['scheme'] == 'https') {
                $ssl = true;
                $port = 443;
            } else {
                $port = 80;
            }
        }
        // CURL OPTIONS PORT
        if (!empty($this->options[CURLOPT_PORT])) {
            $port = $this->options[CURLOPT_PORT];
        }

        //Path init

        if(empty($urlInfo['path'])){
            $path = '/';
        }else{
            $path = $urlInfo['path'];
        }

        if(!empty($urlInfo['fragment'])){
            $path = $path .'#'.$urlInfo['fragment'];
        }

        $client = new Client($urlInfo['host'], $port, $ssl);
        $this->client = $client;

        if (!empty($this->options[CURLOPT_CONNECTTIMEOUT])) {
            $client->set([
                'timeout' => $this->options[CURLOPT_CONNECTTIMEOUT]
            ]);
        }

        if (!empty($this->options[CURLOPT_SSL_VERIFYPEER])) {
            $client->set([
                'ssl_verify_peer' => $this->options[CURLOPT_SSL_VERIFYPEER]
            ]);
        }

        // eg:'tool=curl; fun=yes;'
        if (!empty($this->options[CURLOPT_COOKIE])) {
            $list = explode(';', $this->options[CURLOPT_COOKIE]);
            $ret = [];
            foreach ($list as $item) {
                if (!empty($item)) {
                    $item = explode('=', trim($item));
                    $ret[trim($item[0], ' ')] = trim($item[1], ' ');
                }
            }
            if (!empty($ret)) {
                $client->setCookies($ret);
            }
        }

        // check accept encoding
        if (!empty($this->options[CURLOPT_ENCODING])) {
            $old = $this->options[CURLOPT_HTTPHEADER];
            $old['Accept-Encoding'] = $this->options[CURLOPT_ENCODING];
            $this->options[CURLOPT_HTTPHEADER] = $old;
        }

        if (!empty($this->options[CURLOPT_HTTPHEADER])) {
            $client->setHeaders($this->options[CURLOPT_HTTPHEADER]);
        }

        if (!empty($this->options[CURLOPT_CUSTOMREQUEST])) {
            $client->setMethod($this->options[CURLOPT_CUSTOMREQUEST]);
        }

        if (!empty($this->options[CURLOPT_POSTFIELDS])) {
            //supprot mix form data
            if (is_array($this->options[CURLOPT_POSTFIELDS])) {
                $temp = [];
                foreach ($this->options[CURLOPT_POSTFIELDS] as $key => $item) {
                    if ($item instanceof \CURLFile) {
                        $client->addFile($item->getFilename(), $item->getPostFilename());
                    } else {
                        $temp[$key] = $item;
                    }
                }
                $client->post($path, $temp);
            } else {
                $client->post($path, $this->options[CURLOPT_POSTFIELDS]);
            }
        } else {
            $client->get($path);
        }
        $this->result = (array)$client;
        //call header func
        if(isset($this->options[CURLOPT_HEADERFUNCTION])){
            $call = $this->options[CURLOPT_HEADERFUNCTION];
            if ($client->statusCode === 200) {
                call_user_func($call,$this,"HTTP/1.1 200 OK\r\n");
            }
            foreach ($this->result['headers'] as $header => $val)
            {
                call_user_func($call,$this,"{$header}: {$val}\r\n");
            }
            call_user_func($call,$this,"");
        }
        //body rebuild
        if(!empty($this->options[CURLOPT_HEADER])){
            $buff = $client->body;
            $temp = '';
            foreach ($this->result['headers'] as $header => $val)
            {
                $temp .= "{$header}: {$val}\r\n";
            }
            $client->body = $buff."\r\n".$client->body;
        }
        //call body func
        if(isset($this->options[CURLOPT_READFUNCTION]) && !empty($client->body)){
            $call = $this->options[CURLOPT_READFUNCTION];
            $file = $this->options[CURLOPT_FILE] ?: null;
            call_user_func($call,$this,$file,strlen($client->body));
        }

        if(!empty($this->options[CURLOPT_RETURNTRANSFER])){
            return $this->result['body'] ?: null;
        }else{
            $file = $this->options[CURLOPT_FILE] ?: null;
            if ($file) {
                return fwrite($file, $client->body) === strlen($client->body);
            } else {
                echo $client->body;
            }
            return true;
        }
    }

    function __close(): bool
    {
        if ($this->client instanceof Client) {
            $this->client->close();
            $this->client = null;
            return true;
        } else {
            return false;
        }
    }
}

function swoole_curl_init(?string $url = null): swoole_curl_resource
{
    $ch = new swoole_curl_resource();
    if ($url) {
        swoole_curl_setopt($ch, CURLOPT_URL, $url);
    }
    return $ch;
}

function swoole_curl_setopt(swoole_curl_resource $ch, int $option, $value): bool
{
    return $ch->__setOption($option, $value);
}

function swoole_curl_setopt_array(swoole_curl_resource $ch, array $options): bool
{
    $back = $ch->__getOptions();
    foreach ($options as $option => $val) {
        if (!swoole_curl_setopt($ch, $option, $val)) {
            $ch->__setOptions($back);
            return false;
        }
    }
    return true;
}

function swoole_curl_exec(swoole_curl_resource $ch)
{
    return $ch->__exec();
}

function swoole_curl_getinfo(swoole_curl_resource $resource): array
{
    return $resource->__getResult();
}

function swoole_curl_close(swoole_curl_resource $resource): bool
{
    return $resource->__close();
}

function swoole_curl_error(swoole_curl_resource $resource): ?string
{
    $info = swoole_curl_getinfo($resource);
    if (isset($info['errMsg'])) {
        return $info['errMsg'];
    } else {
        return null;
    }
}

function swoole_curl_errno(swoole_curl_resource $resource): int
{
    $info = swoole_curl_getinfo($resource);
    if (isset($info['errCode'])) {
        return $info['errCode'];
    } else {
        return 0;
    }
}

function swoole_curl_rest(swoole_curl_resource $resource)
{
    $resource->__rest();
}