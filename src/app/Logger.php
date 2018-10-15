<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 25.02.18
 * Time: 15:49
 */

namespace AmoPRO;


use AmoPRO\Exceptions\LibraryException;
use Monolog\Handler\StreamHandler;
use Monolog\Logger as MonologLogger;

/**
 * Class Logger
 *
 * @package AmoPRO
 *
 * @property MonologLogger $order
 * @property MonologLogger $debug
 * @property MonologLogger $error
 */
class Logger
{
    /** @var array каналы */
    private $channels = [];
    /** @var string путь к директории с логами */
    private $logsPath;
    /** @var int уровень логирования */
    private $logLevel;
    
    /**
     * Logger constructor.
     *
     * @param string $logsPath
     *
     * @throws \AmoPRO\Exceptions\LibraryException
     */
    public function __construct($logsPath,$logLevel)
    {
        if (!class_exists(MonologLogger::class)) {
            throw new LibraryException('Не подключена библиотека monolog/monolog');
        }
        if (!file_exists($logsPath)) {
            @mkdir($logsPath, 0777, true);
        }
        // помесячные папки с логами
        $this->logsPath = $logsPath . DIRECTORY_SEPARATOR . date('Y-m');
        if (!file_exists($this->logsPath)) {
            @mkdir($this->logsPath, 0777, true);
        }
        
        switch ($logLevel){
            case 'DEBUG':
                $this->logLevel = MonologLogger::DEBUG;
                break;
            case 'INFO':
                $this->logLevel = MonologLogger::INFO;
                break;
            case 'NOTICE':
                $this->logLevel = MonologLogger::NOTICE;
                break;
            case 'WARNING':
                $this->logLevel = MonologLogger::WARNING;
                break;
            case 'ERROR':
                $this->logLevel = MonologLogger::ERROR;
                break;
            case 'CRITICAL':
                $this->logLevel = MonologLogger::CRITICAL;
                break;
            case 'ALERT':
                $this->logLevel = MonologLogger::ALERT;
                break;
        }
    }

    /**
     * @param $name
     * @return mixed
     * @throws \Exception
     */
    public function __get($name)
    {
        if(!isset($this->channels[$name])){
            $this->channels[$name] = new MonologLogger($name);
            $this->channels[$name]->pushHandler(
                new StreamHandler(
                    $this->logsPath . DIRECTORY_SEPARATOR.$name.'_'.date('Y-m-d').'.log',
                    $this->logLevel,
                    true,
                    0777
                )
            );
        }
        return $this->channels[$name];
    }
    
    /**
     * @param $name
     *
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->channels[$name]);
    }
}