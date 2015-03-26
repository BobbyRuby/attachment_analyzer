<?php
/**
 * Created by PhpStorm.
 * User: Bobby
 * Date: 3/17/2015
 * Time: 2:47 AM
 */


function rfd_debugger($debugItem, $die = 0)
{
    echo '<pre>';
    print_r($debugItem);
    echo '</pre>';
    if ($die == 1) {
        die();
    }
}