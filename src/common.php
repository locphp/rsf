<?php

//系统级别函数

/**
 * @param $variable
 * @param null $defval
 * @param string $runfunc
 * @param bool $emptyrun
 * @return null
 */
function getgpc($variable, $defval = null, $runfunc = '', $emptyrun = false)
{
    if (1 == strpos($variable, '.')) {
        $tmp = strtoupper(substr($variable, 0, 1));
        $var = substr($variable, 2);
    } else {
        $tmp = false;
        $var = $variable;
    }
    $value = '';
    if ($tmp) {
        switch ($tmp) {
            case 'G':
                $type = 'GET';
                if (!isset($_GET[$var])) {
                    return $defval;
                }
                $value = $_GET[$var];
                break;
            case 'P':
                $type = 'POST';
                if (!isset($_POST[$var])) {
                    return $defval;
                }
                $value = $_POST[$var];
                break;
            case 'C':
                $type = 'COOKIE';
                if (!isset($_COOKIE[$var])) {
                    return $defval;
                }
                $value = $_COOKIE[$var];
                break;
            case 'S' :
                $type = 'SERVER';
                break;
            default:
                return $defval;
        }
    } else {
        if (isset($_GET[$var])) {
            $type = 'GET';
            if (!isset($_GET[$var])) {
                return $defval;
            }
            $value = $_GET[$var];
        } elseif (isset($_POST[$var])) {
            $type = 'POST';
            if (!isset($_POST[$var])) {
                return $defval;
            }
            $value = $_POST[$var];
        } else {
            return $defval;
        }
    }
    if (in_array($type, ['GET', 'POST', 'COOKIE'])) {
        return gpc_val($value, $runfunc, $emptyrun);
    } elseif ('SERVER' == $type) {
        return isset($_SERVER[$var]) ? $_SERVER[$var] : $defval;
    } else {
        return $defval;
    }
}

/**
 * @param $val
 * @param $runfunc
 * @param $emptyrun
 * @return string
 */
function gpc_val($val, $runfunc, $emptyrun)
{
    if ('' == $val) {
        return $emptyrun ? $runfunc($val) : '';
    }
    if ($runfunc && strpos($runfunc, '|')) {
        $funcs = explode('|', $runfunc);
        foreach ($funcs as $run) {
            if ('xss' == $run) {
                $val = \Rsf\Helper\Xss::getInstance()->clean($val);
            } else {
                $val = $run($val);
            }
        }
        return $val;
    }
    if ('xss' == $runfunc) {
        return \Rsf\Helper\Xss::getInstance()->clean($val);
    }
    if ($runfunc) {
        return $runfunc($val);
    }
    return $val;
}

//keypath  path1/path2/path3
function getini($key)
{
    $_CFG = \Rsf\App::mergeVars('cfg');
    $k = explode('/', $key);
    switch (count($k)) {
        case 1:
            return isset($_CFG[$k[0]]) ? $_CFG[$k[0]] : null;
        case 2:
            return isset($_CFG[$k[0]][$k[1]]) ? $_CFG[$k[0]][$k[1]] : null;
        case 3:
            return isset($_CFG[$k[0]][$k[1]][$k[2]]) ? $_CFG[$k[0]][$k[1]][$k[2]] : null;
        case 4:
            return isset($_CFG[$k[0]][$k[1]][$k[2]][$k[3]]) ? $_CFG[$k[0]][$k[1]][$k[2]][$k[3]] : null;
        case 5:
            return isset($_CFG[$k[0]][$k[1]][$k[2]][$k[3]][$k[4]]) ? $_CFG[$k[0]][$k[1]][$k[2]][$k[3]][$k[4]] : null;
        default:
            return null;
    }
}

/**
 * @param $udi
 * @param $param
 * @return string
 */
function url($udi, $param = [])
{
    $_udi = explode('/', $udi);
    $url = '?' . \Rsf\App::_dCTL . '=' . $_udi[0] . '&' . \Rsf\App::_dACT . '=' . $_udi[1];

    if (!empty($param)) {
        foreach ($param as $key => $val) {
            $url .= '&' . $key . '=' . $val;
        }
    }
    return $url;
}

/* qutotes get post cookie by \'
 * return string
 */
function daddslashes($string)
{
    if (empty($string)) {
        return $string;
    }
    if (is_numeric($string)) {
        return $string;
    }
    if (is_array($string)) {
        return array_map('daddslashes', $string);
    }
    return addslashes($string);
}

/*
 * it's paire to daddslashes
 */
function dstripslashes($value)
{
    if (empty($value)) {
        return $value;
    }
    if (is_numeric($value)) {
        return $value;
    }
    if (is_array($value)) {
        return array_map('dstripslashes', $value);
    }
    return stripslashes($value);
}

/*
 *
 * 屏蔽单双引号等
 * 提供给数据库搜索
 */
function input_char($text)
{
    if (empty($text)) {
        return $text;
    }
    if (is_numeric($text)) {
        return $text;
    }
    return htmlspecialchars(addslashes($text), ENT_QUOTES, 'UTF-8');
}

/*
*  屏蔽单双引号等
*  提供给html显示 或者 input输入框
*/
function output_char($text)
{
    if (empty($text)) {
        return $text;
    }
    if (is_numeric($text)) {
        return $text;
    }
    return htmlspecialchars(stripslashes($text), ENT_QUOTES, 'UTF-8');
}


/**
 * @param $utimeoffset
 * @return array
 */
function loctime($utimeoffset)
{
    static $dtformat = null, $timeoffset = 8;
    if (is_null($dtformat)) {
        $dtformat = [
            'd' => getini('settings/dateformat') ?: 'Y-m-d',
            't' => getini('settings/timeformat') ?: 'H:i:s'
        ];
        $dtformat['dt'] = $dtformat['d'] . ' ' . $dtformat['t'];
        $timeoffset = getini('settings/timezone') ?: $timeoffset; //defualt is Asia/Shanghai
    }
    $offset = $utimeoffset == 999 ? $timeoffset : $utimeoffset;
    return [$offset, $dtformat];
}

/**
 * @param $timestamp
 * @param string $format
 * @param int $utimeoffset
 * @param string $uformat
 * @return string
 */
function dgmdate($timestamp, $format = 'dt', $utimeoffset = 999, $uformat = '')
{
    if (!$timestamp) {
        return '';
    }
    $loctime = loctime($utimeoffset);
    $offset = $loctime[0];
    $dtformat = $loctime[1];
    $timestamp += $offset * 3600;
    if ('u' == $format) {
        $nowtime = time() + $offset * 3600;
        $todaytimestamp = $nowtime - $nowtime % 86400;
        $format = !$uformat ? $dtformat['dt'] : $uformat;
        $s = gmdate($format, $timestamp);
        $time = $nowtime - $timestamp;
        if ($timestamp >= $todaytimestamp) {
            if ($time > 3600) {
                return '<span title="' . $s . '">' . intval($time / 3600) . '&nbsp;小时前</span>';
            } elseif ($time > 1800) {
                return '<span title="' . $s . '">半小时前</span>';
            } elseif ($time > 60) {
                return '<span title="' . $s . '">' . intval($time / 60) . '&nbsp;分钟前</span>';
            } elseif ($time > 0) {
                return '<span title="' . $s . '">' . $time . '&nbsp;秒前</span>';
            } elseif (0 == $time) {
                return '<span title="' . $s . '">刚才</span>';
            } else {
                return $s;
            }
        } elseif (($days = intval(($todaytimestamp - $timestamp) / 86400)) >= 0 && $days < 7) {
            if (0 == $days) {
                return '<span title="' . $s . '">昨天&nbsp;' . gmdate('H:i', $timestamp) . '</span>';
            } elseif (1 == $days) {
                return '<span title="' . $s . '">前天&nbsp;' . gmdate('H:i', $timestamp) . '</span>';
            } else {
                return '<span title="' . $s . '">' . ($days + 1) . '&nbsp;天前</span>';
            }
        } elseif (gmdate('Y', $timestamp) == gmdate('Y', $nowtime)) {
            return '<span title="' . $s . '">' . gmdate('m-d H:i', $timestamp) . '</span>';
        } else {
            return $s;
        }
    }
    $format = isset($dtformat[$format]) ? $dtformat[$format] : $format;
    return gmdate($format, $timestamp);
}

/**
 * @param $var
 * @param int $halt
 * @param string $func
 */
function dump($var, $halt = 0, $func = 'p')
{
    echo '<style>.track {
      font-family:Verdana, Arial, Helvetica, sans-serif;
      font-size: 12px;
      background-color: #FFFFCC;
      padding: 10px;
      border: 1px solid #FF9900;
      }</style>';
    echo "<div class=\"track\">";
    echo '<pre>';
    if ('p' == $func) {
        print_r($var);
    } else {
        var_dump($var);
    }
    echo '</pre>';
    echo "</div>";
    if ($halt) {
        exit;
    }
}