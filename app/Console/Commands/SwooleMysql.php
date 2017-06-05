<?php
/**
 * Created by PhpStorm.
 * User: regepanda
 * Date: 2017/5/19
 * Time: 17:24
 */

namespace App\Console\Commands;

use Illuminate\Foundation\Inspiring;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;
use App\Library\MysqlServer;

class SwooleMysql extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'SwooleMysql{action}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'SwooleMysql';

    /**
     * swoole_server����
     * @var
     */
    protected $serv;

    /**
     * ��ȡ����
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
                $this->info('swoole mysql started');
                $r = MysqlServer::run();

                break;
            case 'stop':
                $this->info('stoped');
                break;
            case 'restart':
                $this->info('restarted');
                break;
        }
    }
}