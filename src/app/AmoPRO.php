<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 25.02.18
 * Time: 14:19
 */

namespace AmoPRO;


use AmoPRO\Core\App;
use AmoPRO\Exceptions\OrderException;


/**
 * Class AmoPRO
 *
 * @package AmoPRO
 */
class AmoPRO extends App
{
    /**
     * @param string $name имя очереди
     *
     * @return $this
     * @throws \AmoPRO\Exceptions\ConfigException
     */
    public function onQueue($name)
    {
        $this->config->setQueue($name);
        
        return $this;
    }
    
    /**
     * @param array|Order $params
     *
     * @return $this
     * @throws \AmoPRO\Exceptions\OrderException
     */
    public function addOrder($params)
    {
        if ($params instanceof Order) {
            $this->order = $params;
        } else if (is_array($params)) {
            foreach ($params as $name => $param) {
                $this->order->{$name} = $param;
            }
        } else {
            throw new OrderException('Некорректные параметры заявки', $this->log);
        }
        
        $this->order->leadComment =
            'Источник: ' . $this->order->url . PHP_EOL .
            (!empty($this->order->name) ?
                'Имя: ' . $this->order->name . PHP_EOL : '') .
            (!empty($this->order->phone) ?
                'Телефон: ' . $this->order->phone . PHP_EOL : '') .
            (!empty($this->order->email) ?
                'Email: ' . $this->order->email . PHP_EOL : '') .
            $this->order->leadComment;
        
        return $this;
    }
    
    /**
     * @param string $comment
     */
    public function addCommentLine($comment)
    {
        $leadComment = $this->order->leadComment;
        $leadComment .= PHP_EOL . $comment;
        $this->order->leadComment = $leadComment;
        return $this;
    }
    
    /**
     * Добавляет тэги к сделке
     *
     * @param array $tags
     *
     * @return $this
     */
    public function addLeadTags($tags)
    {
        $_tags         = ($this->order->tags);
        $_tags['lead'] = array_merge($_tags['lead'], $tags);
        
        $this->order->tags = $_tags;
        
        return $this;
    }
    
    /**
     * Добавляет тэги к контакту
     *
     * @param array $tags
     *
     * @return $this
     */
    public function addContactTags($tags)
    {
        $_tags            = $this->order->tags;
        $_tags['contact'] = array_merge($_tags['contact'], $tags);
        
        $this->order->tags = $_tags;
        
        return $this;
    }
    
    
    /**
     * Добавляет поле сделки
     *
     * @param int|string $name id поля или его текстовое название (не рекомендуется)
     * @param int|string $value значение поля или id элемента списка
     *
     * @return $this
     */
    public function addLeadField($name, $value)
    {
        $fields = $this->order->fields;
        
        $fields['lead'][$name] = $value;
        
        $this->order->fields = $fields;
        
        return $this;
    }
    
    /**
     * Добавляет поле контакта
     *
     * @param int|string $name id поля или его текстовое название (не рекомендуется)
     * @param int|string $value значение поля или id элемента списка
     *
     * @return $this
     */
    public function addContactField($name, $value)
    {
        $fields = $this->order->fields;
        
        $fields['contact'][$name] = $value;
        
        $this->order->fields = $fields;
        
        return $this;
    }
    
    /**
     * @return void
     */
    public function execute()
    {
        try {
            $this->execStart();
            
            $this->setAmoAccount();
    
            $this->log->order->notice('Новая заявка', $this->order->toArray());
    
            $this->validateOrder();
    
            $this->searchContact();
    
            if (empty($this->data['contact'])) {
                $this->createNewLeadAndContact();
            } else {
                $this->contactExist();
            }
            $this->log->order->info('результат', [
                'lead_id'    => $this->data['lead_id'],
                'contact_id' => $this->data['contact_id'],
            ]);
        } catch (\Exception $e) {
            try {
                $server = "<pre>".print_r($_SERVER,1)."</pre>";
                $errorMess = "<pre>".$e->getMessage()."</pre>";
                $errorTrace = "<pre>".print_r($e->getTrace(),1)."</pre>";
                $headers = "MIME-Version: 1.0" . "\r\n" .
                    "Content-type: text/html; charset=\"utf-8\"" . "\r\n" .
                    "From: =?utf-8?b?". base64_encode("amopro-light error") ."?= <error@{$_SERVER['HTTP_HOST']}>" . "\r\n";
                mail(
                    "mr.mmkk@ya.ru",
                    '=?utf-8?b?'. base64_encode("Ошибка AmoProLight") .'?=',
                    "Error: <br>{$errorMess}<br> Server: <br>{$server}<br> Trace: <br>{$errorTrace}",
                    $headers
                );
            } catch (\Exception $ex) {
                $this->log->error->error($ex->getMessage());
            }
        } finally {
            $this->execStop();
        }
    }
    
    
    /**
     * @return void
     * @throws \AmoPRO\Exceptions\OrderException
     */
    private function validateOrder()
    {
        if (empty($this->order->phone) && empty($this->order->email)) {
            $this->log->order->warning('Нет телефона и емейла', $this->order->toArray());
            throw new OrderException('Нет телефона и емейла', $this->log);
        }
    }
    
    /**
     * @return void
     */
    private function createNewLeadAndContact()
    {
        $this->createLead();
        
        $this->addComment([
            'lead_id' => $this->data['lead_id'],
            'comment' => 'НОВАЯ ЗАЯВКА' . PHP_EOL . $this->order->leadComment,
        ]);
        
        $queue = $this->config->getQueue();
        if (is_array($queue) && is_array($queue['tasks']) && $queue['tasks']['new_contact_new_lead']) {
            $this->addTaskToLead($this->data['lead_id'], [
                'task_text'     => 'Новая заявка',
                'complete_till' => is_string($queue['tasks']['new_contact_new_lead']) ?
                    $queue['tasks']['new_contact_new_lead'] : 'now',
            ]);
        }
        
        $this->createContact();
    }
    
    /**
     * @return void
     */
    private function contactExist()
    {
        // установим отвественного
        $this->setResponsibleUser($this->data['contact']['id']);
        
        $activeLeads = $this->searchActiveLeads();
        // Массив добавляемых комментариев
        $comments = [];
        $this->addCommentToSimilarContacts($comments);
        
        
        /** @var bool $isNewLead создана ли новая сделка */
        $isNewLead = false;
        
        $queue = $this->config->getQueue();
        
        // Если активных сделок нет - создадим ее
        if (empty($activeLeads)) {
            $this->createLead();
            $isNewLead = true;
            $comments[] = [
                'lead_id' => $this->data['lead_id'],
                'comment' => 'НОВАЯ ЗАЯВКА' . PHP_EOL . $this->order->leadComment,
            ];
            // задача
            if (is_array($queue) && is_array($queue['tasks']) && $queue['tasks']['have_contact_new_lead']) {
                $this->addTaskToLead($this->data['lead_id'], [
                    'task_text'     => 'Новая заявка',
                    'complete_till' => is_string($queue['tasks']['have_contact_new_lead']) ?
                        $queue['tasks']['have_contact_new_lead'] : 'now',
                ]);
            }
            
            // теперь надо слинковать новую сделку с контактом
            
        } else {
            // установить активную сделку и добавть комментарии
            $this->setActiveLead($activeLeads,$comments);
            // задача
            if (is_array($queue) && is_array($queue['tasks']) && $queue['tasks']['have_contact_new_lead']) {
                $this->addTaskToLead($this->data['lead_id'], [
                    'task_text'     => 'Новая заявка',
                    'complete_till' => is_string($queue['tasks']['have_contact_new_lead']) ?
                        $queue['tasks']['have_contact_have_lead'] : 'now',
                ]);
            }
        }
        
        // обновить контакт
        $this->updateContact($isNewLead);
        
        // Добавим комментарии пакетно
        if (!empty($comments)) {
            $this->addComment($comments);
        }
    }
}