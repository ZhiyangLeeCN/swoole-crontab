<?php
/**
 * Created by PhpStorm.
 * User: liuzhiming
 * Date: 16-8-19
 * Time: 下午3:56
 */

namespace Lib;
use Swoole;

class CentreServer  extends Swoole\Protocol\SOAServer
{
    function onWorkerStart($server, $worker_id)
    {
        if (!$server->taskworker){
            if ($worker_id == 0 ){
                //$server->task("load");
                //准点载入任务
                $server->after((60-date("s"))*1000,function () use ($server){
                    $server->task("load");
                    $server->tick(60000, function () use ($server) {
                        $server->task("load");
                    });
                });
                //清理过期的服务器
                $server->tick(1000, function () use ($server) {
                   Robot::clean();
                });
            }
            if ($worker_id == 1){
                //定时执行任务
                $server->tick(1000, function () use ($server) {
                    $tasks = Tasks::getTasks();
                    $server->task($tasks);
                });
            }
        }
    }
    function onTask($serv, $task_id, $from_id, $data)
    {
        if ($data == "load"){
            Tasks::checkTasks();
        }else{
            $ret = [];
            foreach ($data as $id)
            {
                $task = LoadTasks::getTasks()->get($id);
                $tmp["id"] = $id;
                $tmp["execute"] = $task["execute"];
                $tmp["taskname"] = $task["taskname"];
                LoadTasks::getTasks()->set($id,["runStatus"=>LoadTasks::RunStatusStart,"runTimeStart"=>microtime()]);
                TermLog::log("task任务开始:".json_encode($tmp),$id);
                $ret[$id] = Robot::Run($tmp);
            }
            return $ret;
        }
        return true;
    }
    function onFinish($serv, $task_id, $data)
    {
        if (is_array($data)){
            foreach ($data as $id=>$v){
                if ($v){
                    $runStatus = LoadTasks::RunStatusToTaskSuccess;//发送成功
                    TermLog::log("task任务发送成功",$id);
                }else{
                    $runStatus = LoadTasks::RunStatusToTaskFailed;//发送失败
                    TermLog::log("task任务发送失败",$id);
                }
                LoadTasks::getTasks()->set($id,["runStatus"=>$runStatus,"runUpdateTime"=>microtime()]);
            }
            TermLog::flush();
        }
        return;
    }
    public function call($request, $header)
    {
        //初始化日志
        Flog::startLog($request['call']);
        Flog::log("call:".$request['call'].",params:".json_encode($request['params']));
        $ret =  parent::call($request, $header); // TODO: Change the autogenerated stub
        Flog::log($ret);
        Flog::endLog();
        Flog::flush();
        return $ret;
    }
}