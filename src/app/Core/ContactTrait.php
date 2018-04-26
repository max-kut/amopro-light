<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 26.02.18
 * Time: 20:34
 */

namespace AmoPRO\Core;


use AmoCRM\Models\Contact;

/**
 * Trait ContactTrait
 *
 * @package AmoPRO\Core
 *
 * @property \AmoPRO\Config $config
 * @property \AmoCRM\Client $amo
 * @property \AmoPRO\Order $order
 * @property \AmoPRO\Logger $log
 */
trait ContactTrait
{
    /**
     * @return void
     */
    protected function searchContact()
    {
        $this->data['contact'] = [];
        $contactsByPhone       = [];
        $contactsByEmail       = [];
        
        $contact = $this->amo->contact;
        
        if (!empty($this->order->phone)) {
            $contactsByPhone = $this->searchContactsByPhone($contact);
        }
        if (!empty($this->order->email)) {
            $contactsByEmail = $this->searchContactsByEmail($contact);
        }
        
        // ищем совпадения одновременно по телефону и емейлу
        $this->data['contact'] = $this->filterDuplicateContactsByPhoneAndEmail($contactsByPhone, $contactsByEmail);
        
        if (empty($this->data['contact'])) {
            if (!empty($contactsByPhone)) {
                $this->data['contact'] = $this->filterSearchedContacts($contactsByPhone);
            } else if (!empty($contactsByEmail)) {
                $this->data['contact'] = $this->filterSearchedContacts($contactsByEmail);
            } else {
                return;
            }
        }
        
        // Здесь мы добавим ссылки на похожие контакты в массив
        $this->data['contact']['similar_contacts'] = [];
        
        $contacts = array_merge($contactsByPhone, $contactsByEmail);
        foreach ($contacts as $cont) {
            if ($cont['id'] != $this->data['contact']['id']) {
                $this->data['contact']['similar_contacts'][] = $cont['id'];
            }
        }
        $this->data['contact_id'] = $this->data['contact']['id'];
    }
    
    /**
     * Поиск контактов по телефону
     *
     * @param \AmoCRM\Models\Contact $client
     *
     * @return array
     */
    private function searchContactsByPhone(Contact $client)
    {
        $matches = [];
        preg_match("/[0-9]{1,10}$/", $this->order->phone, $matches);
        $query = [
            'limit_rows' => 50,
            'query'      => $matches[0],
        ];
        
        $this->log->debug->debug('contact search by phone request', $query);
        
        $contacts = $client->apiList($query, '-400 days');
        usleep(self::REQUEST_DELAY_MCS);
        
        $this->log->debug->debug('contact search by phone response', [
            'code' => $client->getLastHttpCode(),
            'body' => json_decode($client->getLastHttpResponse(), true),
        ]);
        
        return $contacts;
    }
    
    /**
     * Поиск по емейлу
     *
     * @param \AmoCRM\Models\Contact $client
     *
     * @return array
     */
    private function searchContactsByEmail(Contact $client)
    {
        
        $query = [
            'limit_rows' => 50,
            'query'      => $this->order->email,
        ];
        
        $this->log->debug->debug('contact search by email request', $query);
        
        $result   = [];
        $contacts = $client->apiList($query, '-400 DAYS');
        usleep(self::REQUEST_DELAY_MCS);
        
        $this->log->debug->debug('contact search by email response', [
            'code' => $client->getLastHttpCode(),
            'body' => json_decode($client->getLastHttpResponse(), true),
        ]);
        
        // по емейлу амо ищет только вхождения в строку, но не полный емейл
        // поэтому нужно дополнительно фильтровать
        if (!empty($contacts)) {
            foreach ($contacts as $contact) {
                if (!empty($contact['custom_fields'])) {
                    foreach ($contact['custom_fields'] as $cf) {
                        if ((isset($cf['code']) && strtolower($cf['code']) == 'email') ||
                            (isset($cf['CODE']) && strtolower($cf['CODE']) == 'email')) {
                            foreach ($cf['values'] as $val) {
                                if (strtolower($this->order->email) == strtolower($val['value'])) {
                                    $result[] = $contact;
                                }
                            }
                        }
                    }
                }
            }
        }
        
        return $result;
    }
    
    /**
     * @param $contactsByPhone
     * @param $contactsByEmail
     *
     * @return array
     */
    private function filterDuplicateContactsByPhoneAndEmail($contactsByPhone, $contactsByEmail)
    {
        if (empty($contactsByPhone) || empty($contactsByEmail)) {
            return [];
        }
        $contacts = [];
        
        foreach ($contactsByPhone as $contByPhone) {
            foreach ($contactsByEmail as $contByEmail) {
                if ($contByPhone['id'] == $contByEmail['id']) {
                    $contacts[] = $contByPhone;
                }
            }
        }
        
        if (empty($contacts)) {
            return [];
        }
        
        return $this->filterSearchedContacts($contacts);
    }
    
    /**
     * @param $contacts
     *
     * @return array|mixed
     */
    private function filterSearchedContacts($contacts)
    {
        if (count($contacts) == 1) {
            return $contacts[0];
        }
        
        // дублей много
        // Приоритеты актуального контакта
        // 1) похожее имя
        // 2) самая недавняя дата редактирования
        
        
        // Если имя заявки нормальное и реальное
        if (!empty($this->order->name) && !preg_match("/заявка/iu", $this->order->name)) {
            
            $contactsByName = $this->filterSearchedContactsByName($contacts);
            
            if (count($contactsByName) == 1) {
                return $contactsByName[0];
            } else if (count($contactsByName) > 1) {
                $contacts = $contactsByName;
            }
        }
        
        return $this->filterSearchedContactsByLastModifiedTime($contacts);
    }
    
    /**
     * Метод вернет список контактов, у которых имя будет совпадать
     *
     * @param array $contacts
     *
     * @return array
     */
    private function filterSearchedContactsByName($contacts)
    {
        /** @var int $count количество найденных контактов */
        $count = count($contacts);
        
        /** @var array $contactsByName Контакты со сходим именем */
        $contactsByName = [];
        
        for ($d = 0; $d < $count; $d++) {
            
            $nameArr = explode(' ', $this->order->name);
            
            foreach ($nameArr as $name) {
                // не учитываем предлоги (вдруг попадутся)
                $mask = preg_quote($name, '/');
                if (strlen($name) > 2 &&
                    preg_match("/{$mask}/imu", $contacts[$d]['name'])) {
                    $contactsByName[] = $contacts[$d];
                    break;
                }
            }
        }
        
        return $contactsByName;
    }
    
    /**
     * Метод возвращает контакт с самой последней датой редактирования
     *
     * @param $contacts
     *
     * @return array
     */
    private function filterSearchedContactsByLastModifiedTime($contacts)
    {
        $contact = [];
        
        /** @var array массив дат редактирования контактов */
        $last_modified_contacts = [];
        
        $count = count($contacts);
        
        for ($c = 0; $c < $count; $c++) {
            $last_modified_contacts[$contacts[$c]['id']] = $contacts[$c]['last_modified'];
        }
        
        // Выбираем максимальное число временной метки редактирования контпкта
        $contact_id = array_flip($last_modified_contacts)[max($last_modified_contacts)];
        
        for ($c = 0; $c < $count; $c++) {
            if ($contacts[$c]['id'] == $contact_id) {
                return $contacts[$c];
            }
        }
        
        return $contact;
    }
    
    /**
     * @param array $comments link
     */
    protected function addCommentToSimilarContacts(&$comments)
    {
        if (empty($this->data['contact']['similar_contacts'])) {
            return;
        }
        
        $comment        = 'Возможные дубликаты контакта: ' . PHP_EOL;
        $contactBaseUrl = 'https://' . $this->config->getAuth('domain') .
            '.amocrm.ru/contacts/detail/';
        
        foreach ($this->data['contact']['similar_contacts'] as $similarContactId) {
            $comment .= $contactBaseUrl . $similarContactId . PHP_EOL;
            
            $commentSimilarContact = 'Возможные дубликаты контакта: ' . PHP_EOL;
            $commentSimilarContact .= $contactBaseUrl . $this->data['contact']['id'] . PHP_EOL;
            foreach ($this->data['contact']['similar_contacts'] as $_similarContactId) {
                if ($similarContactId == $_similarContactId) continue;
                $commentSimilarContact .= $contactBaseUrl . $_similarContactId . PHP_EOL;
            }
            $comments[] = [
                'contact_id' => $similarContactId,
                'comment'    => $commentSimilarContact,
            ];
        }
        $comments[] = [
            'contact_id' => $this->data['contact']['id'],
            'comment'    => $comment,
        ];
    }
    
    /**
     * @return mixed
     */
    protected function createContact()
    {
        $contact = $this->amo->contact;
        
        $contact['name'] = $this->order->name;
        
        if (!empty($this->data['lead_id'])) {
            $contact['linked_leads_id'] = $this->data['lead_id'];
        }
        
        $responsibleUser = $this->getResponsibleUser();
        $contact['responsible_user_id'] = $responsibleUser['id'];
        
        $orderTags = $this->order->tags;
        $contact['tags'] = $orderTags['contact'];
        
        if (!empty($this->order->phone)) {
            $contact->addCustomField($this->getContactFieldIdByCode('PHONE'),
                [[$this->order->phone, 'WORK']]);
        }
        
        if (!empty($this->order->email)) {
            $contact->addCustomField($this->getContactFieldIdByCode('EMAIL'),
                [[$this->order->email, 'WORK']]);
        }
        
        $customFields = $this->customFields('contact');
        if (!empty($customFields)) {
            foreach ($customFields as $fieldId => $value) {
                $contact->addCustomField((int)$fieldId, $value);
            }
        }
        
        $this->log->debug->debug('add new contact request', $contact->getValues());
        
        $this->data['contact_id'] = $contact->apiAdd();
        usleep(self::REQUEST_DELAY_MCS);
        
        $this->log->debug->debug('add new contact response', [
            'code' => $contact->getLastHttpCode(),
            'body' => json_decode($contact->getLastHttpResponse(), true),
        ]);
        
        return $this->data['contact_id'];
    }
    
    /**
     * @param bool $newLead
     */
    protected function updateContact($newLead = false)
    {
        /** @var bool $isUpdatable обновлять ли контакт */
        $isUpdatable = false;
        
        $contact = $this->amo->contact;
        
        if ($newLead) {
            $isUpdatable   = true;
            $linkedLeads   = (!empty($this->data['contact']['linked_leads_id']) ?
                $this->data['contact']['linked_leads_id'] : []);
            $linkedLeads[] = $this->data['lead_id'];
            $linkedLeads   = array_unique($linkedLeads);
            
            $contact['linked_leads_id'] = $linkedLeads;
        }
        
        /**
         * Проверка контактных данных
         *
         * @param $code
         * @param $value
         */
        $compareCustomFields = function ($code, $value) use (&$contact, &$isUpdatable) {
            $hasField = false;
            foreach ($this->data['contact']['custom_fields'] as $customField) {
                if ($customField['code'] == $code) {
                    $hasField = true;
                    
                    $isNewValue = true;
                    // проверим, есть ли телефон из заявки в контакте
                    // на случай, если было совпадение по емейлу, а также таким способом проверим емейл
                    foreach ($customField['values'] as $val) {
                        if ($code == 'PHONE') {
                            
                            if (preg_match('/[,;]/', $val['value'], $matches)) {
                                $phones = explode($matches[0], $val['value']);
                                for ($m = 0; $m < count($phones); $m++) {
                                    if (self::comparePhones($phones[$m], $value)) {
                                        $isNewValue = false;
                                        break;
                                    }
                                }
                            } else if (self::comparePhones($val['value'], $value)) {
                                $isNewValue = false;
                            }
                        }
                        if ($code == 'EMAIL' && strtolower($val['value']) == strtolower($value)) {
                            
                            $isNewValue = false;
                        }
                    }
                    // Если телефон из заявки в контакте не был найден
                    // добавим его в контакт
                    if ($isNewValue) {
                        $fieldValues = [];
                        foreach ($customField['values'] as $val) {
                            $fieldValues[] = [$val['value'], $val['enum']];
                        }
                        $fieldValues[] = [$value, 'WORK'];
                        
                        $isUpdatable = true;
                        $contact->addCustomField($customField['id'], $fieldValues);
                    }
                    break;
                }
            }
            if (!$hasField) {
                $contact->addCustomField($this->getContactFieldIdByCode($code),
                    [[$value, 'WORK']]);
            }
        };
        
        // Если в заявке есть телефон
        // то при обновлении надо сверить, есть ли у найденного контакта
        // такой телефон. И если телефона из заявки нет в контакте, то добавим ему этот телефон
        if (!empty($this->order->phone)) {
            $compareCustomFields('PHONE', $this->order->phone);
        }
        
        // аналогично с емейлом
        if (!empty($this->order->email)) {
            $compareCustomFields('EMAIL', $this->order->email);
        }
        
        if ($isUpdatable) {
            
            $this->log->debug->debug('update contact request', $contact->getValues());
            
            $contact->apiUpdate((int)$this->data['contact']['id'], 'now');
            usleep(self::REQUEST_DELAY_MCS);
            
            $this->log->debug->debug('update contact contact response', [
                'code' => $contact->getLastHttpCode(),
                'body' => json_decode($contact->getLastHttpResponse(), true),
            ]);
        }
    }
}