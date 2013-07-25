<?php /*@charset "utf-8"*/
/**
 *********************************************************************
 * つぶやき取得用のAPIラッパ v1.0
 * @file   pw_twitter_timeline.php
 * @date   2013-06-26 21:40:50 (Wednesday)
 *********************************************************************/

// タイムラインのキャッシュを取得
require_once('Cache/Lite.php');
$key    = 'pw_twitter_timeline_key';
$option = array('lifeTime'                  => 5, // 15分に180回
                'cacheDir'                  => dirname(__FILE__) . '/cache/',
                'automaticCleaningFactor'   => 100,
                );
$cache = new Cache_Lite($option);
$data  = $cache->get($key);

if(!$data){
    // キャッシュが存在しない場合はtwitterから取得
    if(!isset($_GET['count']) || !is_numeric($count = $_GET['count'])){
        $count = 1;
    }
    $count = min(100, max(1, $count));

    require_once('./../../../wp-load.php');

    $data = PW_Twitter::getTimeLine($count);
    $cache->save($data, $key);
}

// JSONPとして返す
echo $_GET['callback'] . '(' . $data. ')';
