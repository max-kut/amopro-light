<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 26.02.18
 * Time: 21:13
 */

namespace AmoPRO\Core;

use AmoCRM\Client;
use AmoPRO\Config;
use AmoPRO\Exceptions\LibraryException;
use AmoPRO\Logger;
use AmoPRO\Order;

abstract class App
{
    use AccountTrait,
        ContactTrait,
        UsersTrait,
        LeadTrait,
        CustomFieldsTrait,
        CommentTrait,
        TaskTrait;
    
    const REQUEST_DELAY_MCS = 900000;
    
    /** @var \AmoPRO\Config объект параметров */
    protected $config;
    /** @var \AmoPRO\Order объект заявки */
    protected $order;
    /** @var \AmoCRM\Client amocrm клиент */
    protected $amo;
    /** @var \AmoPRO\Logger логгер */
    protected $log;
    /** @var array промежуточные данные */
    protected $data = [];
    
    private $_exec_file;

    /**
     * App constructor.
     *
     * @param $config
     *
     * @throws \AmoPRO\Exceptions\LibraryException
     * @throws \AmoPRO\Exceptions\ConfigException
     */
    public function __construct($config)
    {
        $this->config = new Config($config);
        
        $this->_exec_file = $this->config->conf_path . DIRECTORY_SEPARATOR . '_execute';
        
        $logsPath    = !empty($this->config->logs_path) ? $this->config->logs_path : 'logs';
        $this->log   = new Logger($logsPath, $this->config->log_level);
        
        if (!class_exists(Client::class)) {
            throw new LibraryException('Не подключена библиотека dotzero/amocrm', $this->log);
        }
        
        $this->amo = new Client(
            $this->config->getAuth('domain'),
            $this->config->getAuth('user'),
            $this->config->getAuth('hash')
        );
        
        $this->order = new Order();
        // просто разделительная черта
        $this->log->debug->debug('==================================');
    }
    
    /**
     * Сравнение двух телефонов до кода страны
     * @param string $phone1
     * @param string $phone2
     *
     * @return bool
     */
    protected static function comparePhones($phone1, $phone2){
        $filterPhone = function($phone){
            $matches = [];
            $phone = preg_replace('/[^0-9]/','',$phone);
            preg_match("/[0-9]{1,10}$/",$phone, $matches);
            return $matches[0];
        };
        return ($filterPhone($phone1) == $filterPhone($phone2));
    }

    /**
     * @return \AmoCRM\Client
     */
    public function getAmoClient()
    {
        return $this->amo;
    }

    /**
     * @param $name
     * @param $mess
     * @param array $data
     */
    public function log($name, $mess, $data = [])
    {
        $this->log->{$name}->debug($mess, $data);
    }


    /**
     * флаг что заявка отправляется
     */
    protected function execStart()
    {
        while (true) {
            if($this->isExecStart()){
                sleep(1);
                continue;
            }
            file_put_contents($this->_exec_file, "1");
            break;
        }
    }
    
    /**
     * @return bool
     */
    protected function isExecStart(){
        if(!file_exists($this->_exec_file)){
            return false;
        }
        // запрос не может выполняться более минуты
        elseif(file_exists($this->_exec_file) && (filemtime($this->_exec_file) < (time() - (60) ))){
            return false;
        }
        
        return (bool)file_get_contents($this->_exec_file);
    }
    
    protected function execStop()
    {
        $tempFile = $this->_exec_file . '_template';
        file_put_contents($tempFile, "");
        rename($tempFile, $this->_exec_file);
    }
}