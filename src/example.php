<?php

require 'LgdsSdk.php';

try {
    $server_url = "";
    $appid ="xxxxxx";
    $ak = "xxxxx";
    $sk = "xxxx";
    $Consumer = new DataConsumer($server_url,$appid,$ak,$sk);//必填
    $Consumer ->setFlushSize(20);//选填，默认是20条flush一次
    //初始化TA
    $ta = new LgdsSdk($Consumer);
    $ta->User("default","1234","app","ios",1,["app"=>1]);
    $ta->Track("default","1234","app","ios",1,"login",["level"=>1]);
    $ta->flush();
} catch (LgdsDataException $e) {
}