<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 25.02.18
 * Time: 14:39
 */

namespace AmoPRO;


use AmoPRO\Exceptions\ConfigException;

/**
 * Class Config
 *
 * @package AmoPRO
 *
 * @property array $auth - массив параметров авторизации
 * @property array $queues - ассоциативный массив очередей
 * @property string $conf_path - путь к дитерктории данных
 * @property string $logs_path - путь к дитерктории логов
 * @property string $log_level - уровень логирования
 * @property bool link_other_leads - надо ли делать перелинковку сделок
 */
class Config
{
    /** @var array уровни логирования */
    private $logLevels = [
        'DEBUG',
        'INFO',
        'NOTICE',
        'WARNING',
        'ERROR',
        'CRITICAL',
        'ALERT',
    ];
    /** @var array массив настроек */
    private $config = [];
    
    /**
     * Config constructor.
     *
     * @param $config
     *
     * @throws \AmoPRO\Exceptions\ConfigException
     */
    public function __construct($config)
    {
        if (empty($config) && empty($config['auth'])) {
            throw new ConfigException('Нет авторизационных параметров');
        } else if (empty($config['auth']['domain'])) {
            throw new ConfigException('Не указан домен amocrm');
        } else if (empty($config['auth']['user'])) {
            throw new ConfigException('Не указан пользователь amocrm');
        } else if (empty($config['auth']['hash'])) {
            throw new ConfigException('Не указан API ключ amocrm');
        }
        
        if (empty($config['log_level']) || !in_array($config['log_level'], $this->logLevels)) {
            throw new ConfigException('Не указан или неверный уровень логирования [log_level]');
        }
        
        if (empty($config['conf_path'])) {
            $config['conf_path'] = 'amo_data';
        }
        if(!file_exists($config['conf_path'])){
            mkdir($config['conf_path'], 0777, true);
        }
        
        $this->config = $config;
    }
    
    /**
     * @param $name
     *
     * @return mixed|null
     */
    public function __get($name)
    {
        return isset($this->config[$name]) ? $this->config[$name] : null;
    }
    
    /**
     * @param $name
     *
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->config[$name]);
    }
    
    /**
     * Возвращает параметры авторизации
     *
     * @param string|null $name domain|user|hash
     *
     * @return array|string
     */
    public function getAuth($name = null)
    {
        if (!is_null($name)) {
            return isset($this->config['auth'][$name]) ? $this->config['auth'][$name] : null;
        }
        
        return $this->config['auth'];
    }
    
    /**
     * @return array|null
     */
    public function getQueue()
    {
        if (!is_array($this->config['queues']) || empty($this->config['queues'])) {
            return null;
        }
        if (isset($this->config['current_queue'])) {
            return $this->config['current_queue'];
        } else if (isset($this->config['queues']['default'])) {
            return $this->config['queues']['default'];
        }
        
        return null;
    }
    
    /**
     * @param string $name
     *
     * @return void
     * @throws \AmoPRO\Exceptions\ConfigException
     */
    public function setQueue($name)
    {
        if (!is_array($this->config['queues']) || empty($this->config['queues'])) {
            throw new ConfigException("Массив очередей не определен");
        }
        if (!isset($this->config['queues'][$name])) {
            throw new ConfigException("Очередь с именем {$name} не определена!");
        }
        
        $this->config['current_queue'] = $this->config['queues'][$name];
    }
    
    
    
    
}