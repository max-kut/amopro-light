<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 27.02.18
 * Time: 4:11
 */

namespace AmoPRO\Core;

/**
 * Trait LeadTrait
 *
 * @package AmoPRO\Core
 * @property \AmoPRO\Config $config
 * @property \AmoCRM\Client $amo
 * @property \AmoPRO\Order $order
 * @property \AmoPRO\Logger $log
 */
trait LeadTrait
{
    /**
     * @return mixed
     */
    protected function createLead()
    {
        /** @var array|null $queue */
        $queue = $this->config->getQueue();
        
        $lead         = $this->amo->lead;
        
        $lead['name'] = $this->order->leadName;
        
        if (!empty($queue)) {
            $lead['status_id'] = $queue['status_id'];
        }
        if (!empty($this->order->leadPrice)) {
            $lead['price'] = $this->order->leadPrice;
        }
        $orderTags = $this->order->tags;
        $lead['tags'] = $orderTags['lead'];
    
        $responsibleUser = $this->getResponsibleUser();
        $lead['responsible_user_id'] = $responsibleUser['id'];
        
        if (!empty($this->order->amoVisitorUid)) {
            $lead['visitor_uid'] = $this->order->amoVisitorUid;
        }
        
        $customFields = $this->customFields('lead');
        if (!empty($customFields)) {
            foreach ($customFields as $fieldId => $fieldValue) {
                $lead->addCustomField((int)$fieldId, $fieldValue);
            }
        }
    
        $this->log->debug->debug('add new lead request', $lead->getValues());
        
        $this->data['lead_id'] = $lead->apiAdd();
        usleep(self::REQUEST_DELAY_MCS);
        
        $this->log->debug->debug('add new lead response', [
            'code' => $lead->getLastHttpCode(),
            'body' => json_decode($lead->getLastHttpResponse(), true),
        ]);
        
        return $this->data['lead_id'];
    }
    
    /**
     * @return array
     */
    protected function searchActiveLeads()
    {
        if (empty($this->data['contact']['linked_leads_id'])) {
            return [];
        }
        // активные сделки
        $activeLeads = [];
        
        $lead          = $this->amo->lead;
        
        $requestParams = [
            'id' => $this->data['contact']['linked_leads_id'],
        ];
        $this->log->debug->debug('get contact leads request', $requestParams);
        $linkedLeads = $lead->apiList($requestParams);
        usleep(self::REQUEST_DELAY_MCS);
        
        $this->log->debug->debug('get contact leads response', [
            'code' => $lead->getLastHttpCode(),
            'body' => json_decode($lead->getLastHttpResponse(), true),
        ]);
        
        // отфильтруем только активные сделки
        for ($k = 0; $k < count($linkedLeads); $k++) {
            if (empty($linkedLeads[$k]['date_close'])) {
                $activeLeads[] = $linkedLeads[$k];
            }
        }
        
        return $activeLeads;
    }
    
    /**
     * Метод перебирает активные сделки и устанавливает одну активную сделку
     * Если сделок больше одной, то в сделки добавит комментарий с ссылкой на другие сделки
     *
     * @param array $activeLeads
     * @param array $comments
     */
    protected function setActiveLead($activeLeads, &$comments)
    {
        if (count($activeLeads) == 1) {
            $this->data['lead_id'] = $activeLeads[0]['id'];
        } else {
            /** @var array массив дат редактирования контактов */
            $last_modified_leads = [];
            foreach ($activeLeads as $lead) {
                $last_modified_leads[$lead['id']] = $lead['last_modified'];
            }
            
            // Выбираем максимальное число временной метки редактирования сделки
            $this->data['lead_id'] = array_flip($last_modified_leads)[max($last_modified_leads)];
        }
        $comments[] = [
            'lead_id' => $this->data['lead_id'],
            'comment' => 'ПОВТОРНАЯ ЗАЯВКА' . PHP_EOL . $this->order->leadComment,
        ];
        // Если нужно перелинковать сделки
        if ($this->config->link_other_leads && count($activeLeads) > 1) {
            $comment     = 'Другие сделки контакта:' . PHP_EOL;
            $leadBaseUrl = 'https://' . $this->config->getAuth('domain') . '.amocrm.ru/leads/detail/';
            foreach ($activeLeads as $lead) {
                if ($lead['id'] == $this->data['lead_id']) continue;
                
                $comment .= $leadBaseUrl . $lead['id'] . PHP_EOL;
                
                $otherLeadComment = 'Другие сделки контакта:' . PHP_EOL;
                foreach ($activeLeads as $activeLead) {
                    if ($lead['id'] == $activeLead['id']) continue;
                    $otherLeadComment .= $leadBaseUrl . $activeLead['id'] . PHP_EOL;
                }
                $comments[] = [
                    'lead_id' => $lead['id'],
                    'comment' => $otherLeadComment,
                ];
            }
            $comments[] = [
                'lead_id' => $this->data['lead_id'],
                'comment' => $comment,
            ];
        }
    }
}