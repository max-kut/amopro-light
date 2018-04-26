<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 27.02.18
 * Time: 4:22
 */

namespace AmoPRO\Core;


use AmoPRO\Exceptions\FieldsException;

/**
 * Trait CustomFieldsTrait
 *
 * @package AmoPRO\Core
 * @property \AmoPRO\Order $order
 * @property \AmoCRM\Client $amo
 * @property \AmoPRO\Logger $log
 */
trait CustomFieldsTrait
{
    
    /**
     * Метод проверяет есть ли доп поля в амо
     * и если полей нет, добавляет их пакетно
     *
     * @param null|string $entity lead|contact|company
     *
     * @return array
     * @throws \AmoPRO\Exceptions\FieldsException
     */
    protected function customFields($entity = null)
    {
        if (!empty($entity) &&
            $entity != 'lead' &&
            $entity != 'contact' &&
            $entity != 'company') {
            $msg = sprintf('Некорректно указана сущность доп.полей - %s',$entity);
            throw new FieldsException($msg,$this->log);
        }
        
        if (empty($this->data['custom_fields'])) {
            
            $this->data['custom_fields'] = [
                'lead'    => [],
                'contact' => [],
                'company' => [],
            ];
            
            $orderFields = $this->order->fields;
    
            foreach ($orderFields as $entityName => $fields) {
                foreach ($fields as $nameOrId => $value) {
                    $this->data['custom_fields'][$entityName][$this->getFieldId($entityName, $nameOrId)] = $value;
                }
            }
        }
    
        return !empty($entity) ? $this->data['custom_fields'][$entity] : $this->data['custom_fields'];
    }
    
    /**
     * Метод возвращает ID поля
     *
     * @param string $entity - сущность амо
     * @param string|int $nameOrId
     *
     * @return int
     * @throws \AmoPRO\Exceptions\FieldsException
     */
    protected function getFieldId($entity, $nameOrId)
    {
        // преобразуем название сущности во множественное число
        switch ($entity) {
            case 'lead':
                $entity = 'leads';
                break;
            case 'contact':
                $entity = 'contacts';
                break;
            case 'company':
                $entity = 'companies';
                break;
            default:
                throw new FieldsException('Некорректно указана сущность доп.полей',$this->log);
        }
        
        // пройдем по массиву полей, если поле найдем - вернем его ID
        foreach ($this->data['account']['custom_fields'][$entity] as $field) {
            // Если совпадение по id поля
            if ((gettype($nameOrId) == 'integer' || preg_match("/^[0-9]{5,}$/", $nameOrId)) && $nameOrId == $field['id']) {
                return (int)$field['id'];
            }
    
            // Если совпадение по имени
            $mask = preg_quote($nameOrId, '/');
            if(preg_match("/{$mask}/iu", $field['name'])){
                return (int)$field['id'];
            }
        }
        
        // Если поле не найдем - создадим его и вернем его ID
        return $this->setField($entity, $nameOrId);
    }
    
    /**
     * Метод создает доп поле в амо при его отсетствии
     *
     * @param string $entity
     *
     * @param string $name
     *
     * @return int ID
     * @throws \AmoPRO\Exceptions\FieldsException
     */
    protected function setField($entity, $name)
    {
        
        $amoField           = $this->amo->custom_field;
        $amoField['name']   = $name;
        $amoField['type']   = \AmoCRM\Models\CustomField::TYPE_TEXT;
        $amoField['origin'] = $name . '_' . $entity . '_amopro';
        
        switch ($entity) {
            case 'leads':
                $amoField['element_type'] = \AmoCRM\Models\CustomField::ENTITY_LEAD;
                break;
            case 'contacts':
                $amoField['element_type'] = \AmoCRM\Models\CustomField::ENTITY_CONTACT;
                break;
            case 'companies':
                $amoField['element_type'] = \AmoCRM\Models\CustomField::ENTITY_COMPANY;
                break;
            default:
                throw new FieldsException(
                    sprintf('Некорректная сущность %s. Должно быть leads|contacts|companies', $entity),
                    $this->log
                );
        }
        
        $fieldId = $amoField->apiAdd();
        usleep(self::REQUEST_DELAY_MCS);
        
        $this->log->debug->debug('set new custom field to ' . $entity, [
            'code' => $amoField->getLastHttpCode(),
            'body' => json_decode($amoField->getLastHttpResponse(), true),
        ]);
        
        return (int)$fieldId;
    }
    
    /**
     * Метод возвращает массив только телефона и емейла контактов
     *
     * @param string $code
     *
     * @return int
     * @throws \AmoPRO\Exceptions\FieldsException
     */
    protected function getContactFieldIdByCode($code)
    {
        foreach ($this->data['account']['custom_fields']['contacts'] as $field) {
            if (strtolower($field['code']) == strtolower($code) ||
                strtolower($field['CODE']) == strtolower($code)) {
                return (int)$field['id'];
            }
        }
        
        throw new FieldsException(sprintf('Поле с кодом %s не найдено', $code), $this->log);
    }
}