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
use AmoPRO\Exceptions\LibaryException;
use AmoPRO\Logger;
use AmoPRO\Order;

//require_once '_helpers.php';

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
    
    /**
     * App constructor.
     *
     * @param $config
     *
     * @throws \AmoPRO\Exceptions\LibaryException
     */
    public function __construct($config)
    {
        $this->config = new Config($config);
        
        $logsPath    = !empty($this->config->logs_path) ? $this->config->logs_path : 'logs';
        $this->log   = new Logger($logsPath, $this->config->log_level);
        
        if (!class_exists(Client::class)) {
            throw new LibaryException('Не подключена библиотека dotzero/amocrm', $this->log);
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
}