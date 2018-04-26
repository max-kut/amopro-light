<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 25.02.18
 * Time: 23:20
 */

namespace AmoPRO;

use AmoPRO\Exceptions\OrderException;

/**
 * Class Order
 *
 * @package AmoPRO
 *
 * @property string $amoVisitorUid
 * @property string $leadName
 * @property float $leadPrice
 * @property string $name
 * @property string $defaultName
 * @property string $phone
 * @property string $email
 * @property string $url
 * @property string $leadComment
 *
 * @property array $tags
 * @property array $fields
 */
class Order
{
    /** @var array */
    private $params = [];
    
    /**
     * Order constructor.
     */
    public function __construct()
    {
        $this->params['tags']   = [
            'lead'    => [],
            'contact' => [],
            'company' => [],
        ];
        $this->params['fields'] = [
            'lead'    => [],
            'contact' => [],
            'company' => [],
        ];
        
        $this->defaultName = 'Заявка с сайта';
        $this->leadName    = 'Заявка с сайта';
        $this->url         = $_SERVER['HTTP_HOST'];
    }
    
    /**
     * @param $name
     *
     * @return mixed
     */
    public function __get($name)
    {
        return isset($this->params[$name]) ? $this->params[$name] : null;
    }
    
    /**
     * @param $name
     * @param $value
     *
     * @throws \AmoPRO\Exceptions\OrderException
     */
    public function __set($name, $value)
    {
        // валидация
        switch ($name) {
            case 'tags':
            case 'fields':
                if (!is_array($value)) {
                    throw new OrderException("параметр {$name} должен быть ассоциативным массивом");
                }
                if (!isset($value['lead']) && !isset($value['contact']) && !isset($value['company'])) {
                    throw new OrderException(
                        "параметр {$name} не содержит ключей основных сущностей - lead|contact|company"
                    );
                }
                
                if($name == 'tags'){
                    foreach ($value as $key => $val){
                        $value[$key] = array_unique($val);
                    }
                }
                
                $this->params[$name] = $value;
                break;
            
            case 'url':
                $this->params[$name] = preg_replace("/^www\./i", '', $value);
                break;
            
            case 'phone':
                $this->params[$name] = preg_replace("/[^0-9+]/", '', $value);
                break;
            
            default:
                $this->params[$name] = trim($value);
        }
    }
    
    /**
     * @param $name
     *
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->params[$name]);
    }
    
    /**
     * @return string
     */
    public function __toString()
    {
        return json_encode($this->toArray());
    }
    
    /**
     * @return array
     */
    public function toArray()
    {
        return $this->params;
    }
    
    
}