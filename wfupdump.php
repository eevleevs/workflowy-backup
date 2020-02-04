#!/usr/bin/env php
<?php
    require_once '/usr/local/lib/workflowy-php/src/autoload.php';
    error_reporting(E_ALL & ~E_NOTICE);

    use WorkFlowyPHP\WorkFlowy;
    use WorkFlowyPHP\WorkFlowyList;
    use WorkFlowyPHP\WorkFlowyException;

    $date = date('Ymd');

    $mysqli = new mysqli("localhost", "wf", "PMAxHAtrsWYX3ZPc", "wf");
    $result = $mysqli->query('SELECT * FROM `users`');
    while ($row = $result->fetch_array()) {
        try
        {
            // login
            $session_id = WorkFlowy::login($row['user'], $row['password']);

            // get list
            $list_request = new WorkFlowyList($session_id);
            $list = $list_request->getList();

            // output list for backup
            if ($row['backup']) {
                $dir = $_SERVER['HOME'] . '/backup/workflowy/' . $row['user'] . '/';
                if (!is_dir($dir)) mkdir($dir, 0700, TRUE);
                file_put_contents($dir . $date . '.opml.gz', gzencode(
                    $list->getOPML()
                ));
            }

            // manipulate **[n], ||[n] and mail expired
            if ($row['delay']) {
                $expired = '';
                function decreaseTag($list) {
                    global $date, $expired;
                    $re = '/(^|\s)(\d{8})*\|\|(\d+)($|\s)/';    // match delayed
                    if (preg_match($re, $list->getName(), $matches)) {
                        if ($matches[2] == '' || $matches[2] < $date) {
                            if ($matches[2] == '') $diff = 1;
                            else $diff = round((strtotime($date) - strtotime($matches[2])) / 86400);
                            $name = preg_replace($re, $matches[1] . "$date" . ($matches[3] - $diff < 1 ? '***' : '||' . ($matches[3] - $diff)) . $matches[4], $list->getName());
                            // print $matches[0] . ' - ' . $diff . ' - ' . $name . "\n";
                            $list->setName($name);
                            if ($matches[3] - $diff < 1) $expired .= "- " . preg_replace('/\d{8}\*\*\*/', '', $name) . "\n";
                        }
                    }
                    $re = '/(^|\s)(\d{8})*(\*{2,})($|\s)/';    // match expired
                    if (preg_match($re, $list->getName(), $matches)) {
                        if ($matches[2] == '' || $matches[2] < $date) {
                            $list->setName(preg_replace($re, $matches[1] . "$date" . $matches[3] . '*' . $matches[4], $list->getName()));
                        }
                    }
                    foreach ($list->getSublists() as $sublist) decreaseTag($sublist);
                }
                decreaseTag($list);
                if ($expired) mail($row['user'], 'WorkFlowy delayed tasks', "https://workflowy.com/#/?q=**\n\n" . $expired);
            }
            
            // manipulate dates
            
            // update calendar ISO 8601
            // !YYYY-MM-DD expiring date (appears on calendar)
            // !!YYYY-MM-DD non expiring date (appears on calendar)
            // !*YYYY-MM-DD expired date (appears on calendar and today?)
            // !* todo urgent (appears on calendar today)
            // !! todo not urgent (does not appear on calendar)
            // !., !*. !!. short for today - dot gets replaced with modification date 
            
            if ($row['calendar']) {
                $calendar = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:wfupdump\r\n";
                $expired = '';
                function update_calendar($list) {
                    global $date, $expired, $calendar;
                    $this_expired = 0;
                    $timestamp = $list->getLastModifiedTime();	
                    $re = '/!(?![\n\s]|$)([\+!\*])?(\d*)-?(\d{1,2})?-?(\d{1,2})?([*!])?(\.)?/';
                    if (preg_match($re, $list->getName(), $matches)) {
                        if ($matches[0] != '!') {
                            $name = '';
                            if ($matches[6] == '.') $name = str_replace($matches[0], substr($matches[0], 0, -1) . date('Y-m-d', $timestamp), $list->getName());    // replace dot with modification date
                            switch ($matches[5]) {    
                                case '*': 
                                    break;
                                case '!':
                                    break;
                                default:
                                    if ($matches[2].$matches[3].$matches[4] && (strlen($matches[2]) < 4 || strlen($matches[3]) < 2 || strlen($matches[4]) < 2)) {    // expand dates
                                        $m = $matches;
                                        if (!$name) $name = $list->getName();
                                        switch ($matches[1]) {
                                            case '+':
                                                if ($matches[2]) {    
                                                    // add to today
                                                }
                                            default:
                                                if ($matches[2]) { if (strlen($matches[2]) < 4) $m[2] += 2000; }    // date assuming that less than 4 digits year is in 21st century
                                                    else $m[2] = date('Y', $timestamp) + ($matches[3] && $matches[3] < date('m', $timestamp) ? 1 : ($matches[3] == date('m', $timestamp) ? ($matches[4] <= date('d', $timestamp) ? 1 : 0 ) : 0));
                                                $m[3] = sprintf('%02d', $matches[3]);
                                                $m[4] = sprintf('%02d', $matches[4]);
                                                if (!$matches[3]) $m[3] = $matches[2] ? '01' : ($matches[4] <= date('d', $timestamp) ? ($matches[3] == '12' ? '01' : sprintf('%02d', date('m', $timestamp) + 1)) : sprintf('%02d', date('m', $timestamp)));
                                                if (!$matches[4]) $m[4] = '01';
                                                if ($m[2].$m[3].$m[4] <= $date) {
                                                    $expired .= "- " . $name . "\n";
                                                    $this_expired = 1;
                                                } else {
                                                    print_r($m);
                                                }
                                                $name = preg_replace($re, 
                                                    '!' . ($this_expired ? '*' : '') . $m[2] . '-' . $m[3] . '-' . $m[4], $list->getName());
                                                for ($i = 2; $i <= 4; $i++) $matches[$i] = $m[$i];
                                        }
                                    }
                            }
                            if ($name) {
                                $expired .= $list->getName() . ' ' . $name . "\n";
                                $list->setName($name);
                            }
                            $t = ($this_expired ? date('Ymd', $timestamp) : $matches[2] . $matches[3] . $matches[4]);
                            $calendar .= "BEGIN:VEVENT\r\nUID:" . $list->getID() . "\r\nDTSTAMP:" . date('Ymd', $timestamp) . "T" . date('His', $timestamp) . "Z" . "\r\nDTSTART;VALUE=DATE:" . $t . "\r\nDTEND;VALUE=DATE:" . $t . "\r\nSUMMARY:" . $list->getName() . "\r\nURL:https://workflowy.com/#/" . $list->getID() . "\r\nDESCRIPTION:https://workflowy.com/#/" . $list->getID() . "\r\nEND:VEVENT\r\n"; 
                        }
                    }
                    foreach ($list->getSublists() as $sublist) update_calendar($sublist);    // recurse over children
                }
                update_calendar($list);    // start recursion
                if ($expired) mail($row['user'], 'WorkFlowy delayed tasks', "https://workflowy.com/#/?q=!*\n\n" . $expired);
                $calendar .= "END:VCALENDAR\r\n";
                if ($calendar) file_put_contents('/var/www/html/ivlivs.it/wfcal/' . $row['uuid'] . '.ics', $calendar);              
            }
            
        }
        catch (WorkFlowyException $e)
        {
            var_dump($e->getMessage());
        }
    }
?>
