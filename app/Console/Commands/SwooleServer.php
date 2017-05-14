<?php
/**
 * Created by PhpStorm.
 * User: regepanda
 * Date: 2017/5/16
 * Time: 10:25
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Mail;

class SwooleServer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'SwooleServer{action}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'SwooleServer';

    /**
     * swoole_server对象
     * @var
     */
    protected $serv;

    /**
     * 获取参数
     *
     * @return array
     */
    public function getArguments()
    {
        return array(
            array('action', InputArgument::REQUIRED, 'start|stop|restart')
        );
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->comment(PHP_EOL.Inspiring::quote().PHP_EOL);
        $arg = $this->argument('action');
        switch ($arg) {
            case 'start':
                $this->info('swoole observer started');
                $this->start();
                break;
            case 'stop':
                $this->info('stoped');
                break;
            case 'restart':
                $this->info('restarted');
                break;
        }
    }

    /**
     * swoole_server监听服务
     */
    private function start()
    {
        $this->serv = new \swoole_server("0.0.0.0", 9501);
        $this->serv->set(
            array(
                'work_num' => 4,
                'daemonize' => false,
                'max_request' => 10000,
                'dispatch_mode' => 2,
                'debug_mode' => 1
            )
        );

        $this->serv->on('Start', [$this ,'onStart']);
        $this->serv->on('Connect', [$this ,'onConnect']);
        $this->serv->on('Receive', [$this ,'onReceive']);
        $this->serv->on('Close', [$this ,'onClose']);

        $this->serv->start();
    }

    public function onStart()
    {

    }

    public function onConnect($server, $descriptors, $fromId)
    {

    }

    public function onReceive(\swoole_server $server, $descriptors, $fromId, $data)
    {
        $sent = $this->send($data);
        if (!is_array($sent)) {
            dump("success");
        } else {
            dump("fail");
        }
    }

    public function onClose($server, $descriptors, $fromId)
    {

    }

    public function send($data)
    {
        $businessAdr = isset($data['businessAdr']) ? $data['businessAdr'] : null;
        $userAdr = isset($data['userAdr']) ? $data['userAdr'] : null;
        $recvTitle = isset($data['recvTitle']) ? $data['recvTitle'] : null;
        $recvContent = isset($data['recvContent']) ? $data['recvContent'] : null;
        $template = isset($data['template']) ? $data['template'] : [];
        try {
            Mail::send('email.comment', ["recvTitle" => $recvTitle, "recvContent" => $recvContent],
                function ($message) use ($businessAdr, $userAdr, $recvTitle, $recvContent, $template) {
                    //是否需要发送附件
                    if (!empty($template)) {
                        foreach ($template as $path) {
                            $message->attach($path);
                        }
                    }

                    //发送给买家
                    $message->to($userAdr);
                    $message->subject($recvTitle);
                    //是否需要抄送
                    if ($businessAdr) {
                        $message->cc($businessAdr);
                    }
                });
            return true;
        } catch (\Exception $e) {
            $data['email_error'] = $e->getMessage();
            return $data;
        }
    }

}