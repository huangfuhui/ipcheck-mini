<?php
require dirname(__FILE__) . '/AccessDenied.php';

/*
 * Obtain and rendering data
 */

class DataRender
{
    public $redis = '';

    public function __construct()
    {
        // connect the Redis Server
        $this->redis = new Redis();
        $res = $this->redis->connect('127.0.0.1', '6379');
        $res || exit;

        // set the default timezone
        date_default_timezone_set('Asia/Shanghai');
    }

    /**
     * Return the HTML code of the recent record
     * @param string $page
     * @return string
     */
    public function recentRecord($page = '1')
    {
        $html_body = <<<HTML
<table>
    <tr>
        <th>NO.</th>
        <th>IP ADDRESS</th>
        <th>LAST ACCESS TIME</th>
    </tr>
HTML;

        $list_count = $this->redis->lLen('ips:access_record');
        if (empty($page) || !is_numeric($page) || $page <= 0) {
            $page = 0;
        } else {
            $page -= 1;
        }

        $recent_record = $this->redis->lRange('ips:access_record', $page * 15, ($page + 1) * 15 - 1);

        // loop output the rows of table
        foreach ($recent_record as $key => $value) {
            if (++$key % 2 == 0) {
                $tr_class = 'double_tr';
            } else {
                $tr_class = 'single_tr';
            }

            $id = $key + $page * 15;

            // get the access time for IP
            $ip_info = json_decode($this->redis->hGet('ips:info', $value), true);
            $time = date('Y-m-d H:i:s', $ip_info['REQUEST_TIME']);

            $html_body .= <<<HTML
    <tr class="{$tr_class}">
        <td>{$id}</td>
        <td>{$value}</td>
        <td>{$time}</td>
    </tr>
HTML;
        }

        $html_body .= <<<HTML
</table>
HTML;

        return $html_body . $this->getPageSelector($page + 1, ceil($list_count / 15), 'admin.php?menu=recent_record');
    }

    /**
     * Return the HTML code of the total record
     * @param string $page
     * @return string
     */
    public function totalRecord($page = '1')
    {
        $html_body = <<<HTML
<table>
    <tr>
        <th>NO.</th>
        <th>IP ADDRESS</th>
        <th>ACCESS TIMES</th>
        <th>LAST ACCESS TIME</th>
        <th>LAST REQUEST FILE</th>
    </tr>
HTML;

        $record_count = $this->redis->zCard('ips:access_times');
        if (empty($page) || !is_numeric($page) || $page <= 0) {
            $page = 0;
        } else {
            $page -= 1;
        }

        $access_times = $this->redis->zRevRange('ips:access_times', $page * 15, ($page + 1) * 15 - 1, true);
        $count = count($access_times);

        for ($i = 0; $i < $count; $i++) {
            $id = $i + 1 + $page * 15;
            if ($id % 2 == 0) {
                $tr_class = 'double_tr';
            } else {
                $tr_class = 'single_tr';
            }

            $ip_address = key($access_times);
            $ip_access_times = $access_times[$ip_address];
            next($access_times);

            $ip_info = json_decode($this->redis->hGet('ips:info', $ip_address), true);
            $last_access_time = date('Y-m-d H:i:s', $ip_info['REQUEST_TIME']);
            $last_request_script = $ip_info['SCRIPT_NAME'];

            $html_body .= <<<HTML
    <tr class="{$tr_class}">
        <td>{$id}</td>
        <td>{$ip_address}</td>
        <td>{$ip_access_times}</td>
        <td>{$last_access_time}</td>
        <td>{$last_request_script}</td>
    </tr>
HTML;
        }

        $html_body .= <<<HTML
</table>
HTML;

        return $html_body . $this->getPageSelector($page + 1, ceil($record_count / 15), 'admin.php?menu=total_record');
    }

    /**
     * Return the HTML code of the ban record
     * @return string
     */
    public function banRecord()
    {
        if (!empty($_POST['ips'])) {
            $ips = (new AccessDenied($this->redis))->updateBanIPs($_POST['ips']);
        } else {
            $ips = (new AccessDenied($this->redis))->getBanIps();
        }

        $html_body = <<<HTML
<div class="ban_record_example">
Access Denied Example :<br />
    <div>
        127.0.0.1<br />
        10.0.0.2<br />
        172.16.0.1<br />
        192.168.0.1<br />
    </div>
</div>
<div class="ban_record_text">
    <form action="" method="post">
        <textarea name="ips" rows="22" cols="40">{$ips}</textarea>
        <input type="submit" value="submit" />
    </form>
</div>
HTML;

        return $html_body;
    }

    /**
     * Rendering the output of Page-Selector
     * @param $current_page
     * @param $total_page
     * @param $href
     * @return string
     */
    public function getPageSelector($current_page, $total_page, $href)
    {
        $page_selector_html = <<<HTML
<div class="page_selector">
    <ul>
HTML;

        for ($i = 1; $i <= $total_page; $i++) {
            if ($i == $current_page) {
                $select_class = 'class="page_select"';
            } else {
                $select_class = '';
            }

            $next_href = $href . '&page=' . $i;

            $page_selector_html .= <<<HTML
        <li {$select_class}><a href="{$next_href}">$i</a></li>
HTML;
        }

        $page_selector_html .= <<<HTML
        <li><span>total {$total_page} pages</span></li>
    </ul>
</div>
HTML;

        return $page_selector_html;
    }

    public function __destruct()
    {
        $this->redis->close();
    }
}