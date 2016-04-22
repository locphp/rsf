<?php

namespace Rsf;

class Cacher {

    use Traits\Singleton;

    private $config = [];
    private $prefix = '';
    private $cacher = null;
    public $enable = false;
    public $type = '';

    public function __construct($cacher = null) {
        $this->config = getini('cache');
        $this->prefix = $this->config['prefix'];
        $cacher = $cacher ?: $this->config['cacher'];
        if (in_array($cacher, array('file', 'memcache', 'redis', 'xcache'))) {
            $class = 'Cache\\' . ucfirst($cacher);
            $this->cacher = $class::getInstance()->init(Context::dsn($cacher . '.cache'));
            $this->enable = $this->cacher->enable;
            $this->type = $cacher;
        }
        return $this;
    }

    public static function factory($cacher) {
        return new self($cacher);
    }

    public function close() {
        $this->enable && $this->cacher->close();
    }

    public function get($key) {
        $ret = null;
        if ($this->enable) {
            $json = $this->cacher->get($this->_key($key));
            if (!$json) {
                return $ret;
            } else {
                $ret = json_decode($json, true);
                return $ret[0];
            }
        }
        return $ret;
    }

    public function set($key, $value, $ttl = 0) {
        $ret = false;
        if ($this->enable) {
            $data = [$value];
            $ret = $this->cacher->set($this->_key($key), output_json($data), $ttl);
        }
        return $ret;
    }

    public function rm($key) {
        $ret = false;
        if ($this->enable) {
            $ret = $this->cacher->rm($this->_key($key));
        }
        return $ret;
    }

    public function clear() {
        $ret = false;
        if ($this->enable) {
            $ret = $this->cacher->clear();
        }
        return $ret;
    }

    private function _key($str) {
        return $this->prefix . $str;
    }

}
