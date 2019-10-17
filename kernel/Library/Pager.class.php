<?php

namespace Library;

/**
 * 分页处理类
 * @package Library
 */
class Pager
{
    /**
     * 分页配置
     */
    private $config = array(
        'previous' => '<li><a href="[url]">上一页</a></li>',
        'next' => '<li><a href="[url]">下一页</a></li>',
        'no' => '<li><a href="[url]">[no]</a></li>',
        'current' => '<li class="disabled"><span>[no]</span></li>',
        'dot' => '<li class="disabled"><span>..</span></li>',
        'first' => '<li><a href="[url]">[no]</a></li>',
        'last' => '<li><a href="[url]">[no]</a></li>',
        'info' => '显示[total]条记录中的[start]-[end]条',
        'none' => '没有记录信息！',
        'only' => '共[total]条记录',
    );

    /**
     * 设置分页配置
     * @param string|array $key 分页配置数组或配置单项
     * @param string|null $value 配置值
     */
    public function setConfig($key, $value = null)
    {
        if (is_array($key)) {
            $this->config = array_merge($this->config, $key);
        } else {
            $this->config[$key] = $value;
        }
    }

    /**
     * 获取列表分页
     * @param array $config 分页信息参数配置
     * @param int $setPages 显示页数（可选），默认：10
     * @param string $urlRule 包含变量的URL规则模板（可选），默认：{type}={page}
     * @param array $array 附加的参数（可选）
     * @return array 分页配置，包括html和info字段
     * 
     * 分页信息参数范例：
     * 		[
     *          'total'=> $totalrows,  //记录总数
     *          'page'=> $currentpage,  //当前分页，支持例如：“3, 5”（当前第3页，分页大小为5）
     *          'size'=> $pagesize,  //每页大小（可选），默认：15
     *          'url'=> $curl, //分页URL（可选），默认使用当前页
     * 			'type'=>'page',  //分页参数（可选），默认:page
     * 			'callback'=>'showPage(\'?\')', //js回调函数（可选）
     * 		]
     */
    function getPager($config, $setPages = 10, $urlRule = '', $array = array())
    {
        $defaults = array('type' => 'page', 'size' => 15, 'count' => 1);
        $addUrl = '';
        $configs = array_merge($defaults, $config);
        if (!isset($configs['url'])) {
            $configs['url'] = $this->getUrl();
        }
        if (is_string($configs['page']) && strpos($configs['page'], ',') !== false) {
            $pagesizes = explode($configs['page'], ',');
            $configs['page'] = intval(trim($pagesizes[0]));
            $configs['size'] = intval(trim($pagesizes[1]));
        }
        $callback = isset($config['callback']) ? $config['callback'] : '';
        if (isset($GLOBALS['URL_RULE']) && $urlRule == '') {
            $urlRule = $GLOBALS['URL_RULE'];
            $array = $GLOBALS['URL_ARRAY'];
        } else if ($urlRule == '') {
            $urlRule = $this->urlParam($configs['type'] . '={$page}', $configs['url'], $configs['type']);
        }
        unset($config);


        $info = $configs['total'] == 0 ? $this->config['none'] : str_replace('[total]', $configs['total'], $this->config['only']);
        $html = '';
        if ($configs['total'] > $configs['size']) {
            $configs['page'] = max(intval($configs['page']), 1);
            $configs['start'] = ($configs['page'] - 1) * $configs['size'] + 1;
            $configs['end'] = min($configs['page'] * $configs['size'], $configs['total']);

            $page = $setPages + 1;
            $offset = ceil($setPages / 2 - 1);
            $configs['count'] = $pages = ceil($configs['total'] / $configs['size']);
            $from = $configs['page'] - $offset;
            $to = $configs['page'] + $offset;
            $more = 0;
            if ($page >= $pages) {
                $from = 2;
                $to = $pages - 1;
            } else {
                if ($from <= 1) {
                    $to = $page - 1;
                    $from = 2;
                } elseif ($to >= $pages) {
                    $from = $pages - ($page - 2);
                    $to = $pages - 1;
                }
                $more = 1;
            }

            $page_previous = $this->config['previous'];
            $page_next = $this->config['next'];
            $page_no = $this->config['no'];
            $page_current = $this->config['current'];
            $page_dot = $this->config['dot'];
            $page_first = $this->config['first'];
            $page_last = $this->config['last'];
            $page_info = $this->config['info'];

            foreach ($configs as $k => $v) {
                $page_info = str_replace('[' . $k . ']', $v, $page_info);
            }
            $info = $page_info;

            if ($configs['page'] > 0) {
                if ($configs['page'] == 1) {
                    $html .= str_replace('[no]', 1, $page_current);
                } else {
                    $html .= str_replace('[url]', $this->pageUrl($configs['page'] - 1, $urlRule, $array), $page_previous);
                    $html .= str_replace(array('[url]', '[no]'), array($this->pageUrl(1, $urlRule, $array), 1), $page_first);

                    if ($configs['page'] > 6 && $more) {
                        $html .= $page_dot;
                    }
                }
            }

            for ($i = $from; $i <= $to; $i++) {
                if ($i != $configs['page']) {
                    $html .= str_replace(array('[url]', '[no]'), array($this->pageUrl($i, $urlRule, $array), $i), $page_no);
                } else {
                    $html .= str_replace('[no]', $i, $page_current);
                }
            }

            if ($configs['page'] < $pages) {
                if ($configs['page'] < $pages - 5 && $more) {
                    $html .= $page_dot;
                }
                $html .= str_replace(array('[url]', '[no]'), array($this->pageUrl($pages, $urlRule, $array), $pages), $page_last);
                $html .= str_replace(array('[url]', '[no]'), array($this->pageUrl($configs['page'] + 1, $urlRule, $array), $configs['page'] + 1), $page_next);
            } elseif ($configs['page'] == $pages) {
                $html .= str_replace('[no]', $pages, $page_current);
            } else {
                $html .= str_replace(array('[url]', '[no]'), array($this->pageUrl($pages, $urlRule, $array), $pages), $page_last);
            }
        }
        return array_merge($configs, array('html' => $html, 'info' => $info));
    }

    /**
     * 生成分页URL
     *
     * @param int $page 页数
     * @param string $urlRule 包含变量的URL规则模板参数
     * @param array $array 字符变量数组
     * @return string 生成的URL
     */
    private function pageUrl($page, $urlRule, $array)
    {
        if (strpos($urlRule, '~')) {
            $urlRules = explode('~', $urlRule);
            $urlRule = $page < 2 ? $urlRules[0] : $urlRules[1];
        }
        $findme = array('{$page}');
        $replaceme = array($page);
        if (is_array($array)) {
            foreach ($array as $k => $v) {
                $findme[] = '{$' . $k . '}';
                $replaceme[] = $v;
            }
        }
        $url = str_replace($findme, $replaceme, $urlRule);
        $url = str_replace(array('http://', '//', '~'), array('~', '/', 'http://'), $url);
        return $url;
    }

    /**
     * 根据原始URL及新增参数重新URL
     *
     * @param array|string $par 新增的参数
     * @param string $url 原始URL
     * @param string $key 需要排除的URL参数
     * @return string 重新设置的URL
     */
    private function urlParam($par, $url = '', $key = 'page')
    {
        if ($url === '') {
            $url = $this->getUrl();
        }
        $pos = strpos($url, '?');
        if ($pos === false) {
            $url .= '?' . (is_array($par) ? http_build_query($par) : $par);
        } else {
            $querystring = substr(strstr($url, '?'), 1);
            $pars = explode('&', $querystring);
            $querystring = '';
            foreach ($pars as $kv) {
                if (strpos($kv, '=') === false) {
                    $k = $kv;
                    $v = false;
                } else {
                    $k = substr($kv, 0, strpos($kv, '='));
                    $v = substr($kv, strpos($kv, '=') + 1);
                }
                if ($k != $key) {
                    $querystring .= $k . ($v === false ? '' : '=' . $v) . '&';
                }
            }

            $querystring = ($querystring ? $querystring : '') . (is_array($par) ? http_build_query($par) : $par);
            $url = substr($url, 0, $pos) . (empty($querystring) ? '' : '?' . $querystring);
        }
        return $url;
    }

    /**
     * 获取当前页面URL地址
     *
     * @param int $type 需要获取的类型（取值及返回值意义：0-相对地址；1-绝对地址；2-不带参数相对地址；3-不带参数绝对地址；）
     * @return string 获取的URL，类型由$type决定
     */
    private function getUrl($type = 0)
    {
        $sys_protocal = env('SITE_PROTOCOL');
        $php_self = $_SERVER['PHP_SELF'] ? $_SERVER['PHP_SELF'] : $_SERVER['SCRIPT_NAME'];
        $path_info = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
        $relate_url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $php_self . (isset($_SERVER['QUERY_STRING']) ? '?' . safe_replace($_SERVER['QUERY_STRING']) : $path_info);
        if (strpos($relate_url, '?') === false && !empty($_SERVER['QUERY_STRING'])) {
            $relate_url .= '?' . $_SERVER['QUERY_STRING'];
        }
        $relate_url_nopara = strpos($relate_url, '?') === false ? $relate_url : substr($relate_url, 0, strpos($relate_url, '?'));
        switch ($type) {
            case 0: //相对地址
                return $relate_url;
            case 1: //绝对地址
                return $sys_protocal . env('SITE_HOST') . $relate_url;
            case 2: //不带参数相对地址
                return $relate_url_nopara;
            case 3: //不带参数绝对地址
                return $sys_protocal . env('SITE_HOST') . $relate_url_nopara;
        }
    }
}
