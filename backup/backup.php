#!/usr/bin/env php
<?php
    require_once 'workflowy-php/src/autoload.php';
    error_reporting(E_ALL & ~E_NOTICE);

    use WorkFlowyPHP\WorkFlowy;
    use WorkFlowyPHP\WorkFlowyList;
    use WorkFlowyPHP\WorkFlowyException;

    $date = date('Ymd');

    // login - ADD PASSWORD
    $session_id = WorkFlowy::login('giulio.malventi@gmail.com', '');

    // get list
    $list_request = new WorkFlowyList($session_id);
    $list = $list_request->getList();

    // output list for backup
    $dir = '/data/';
    if (!is_dir($dir)) mkdir($dir, 0700, TRUE);
    file_put_contents($dir . $date . '.opml.gz', gzencode(
        $list->getOPML()
    ));
?>
