<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 26.02.18
 * Time: 0:55
 */

/**
 * @param $data
 * @return void
 */
function dump($data){
    $args = func_get_args();
    foreach ($args as $arg){
        echo '<pre style="padding: 5px; border:1px solid #ccc; font-size: 10px;">'.print_r($arg,1).'</pre>';
    }
}

/**
 * @param $data
 */
function dd($data){
    $args = func_get_args();
    foreach ($args as $arg){
        echo '<pre style="padding: 5px; border:1px solid #ccc; font-size: 10px;">'.print_r($arg,1).'</pre>';
    }
    die();
}