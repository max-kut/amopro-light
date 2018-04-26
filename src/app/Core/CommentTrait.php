<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 27.02.18
 * Time: 6:03
 */

namespace AmoPRO\Core;

/**
 * Trait CommentTrait
 *
 * @package AmoPRO\Core
 * @property \AmoCRM\Client $amo
 * @property \AmoPRO\Logger $log
 */
trait CommentTrait
{
    protected function addComment($data)
    {
        if (!isset($data[0]) || !is_array($data[0])) {
            $data = [$data];
        }
        $notes = [];
        $debugComments = [];
        
        for ($i = 0; $i < count($data); $i++) {
            # Если ID сделки нет, то прервем итерацию
            # т.к. нельзя привязать комментарий неизвестной сущности
            if (empty($data[$i]['lead_id']) && empty($data[$i]['contact_id'])) {
                continue;
            }
            $_note = $this->amo->note;
            
            $_note['element_id'] = $data[$i]['lead_id'] ?: $data[$i]['contact_id'];
            // 1 - контакт, 2 - сделка
            $_note['element_type'] = isset($data[$i]['lead_id']) ? 2 : 1;
            // тип комментарий
            $_note['note_type'] = !empty($data[$i]['note_type']) ? (int)$data[$i]['note_type'] : 4;
            
            $_note['text'] = $data[$i]['comment'];
            
            $notes[] = $_note;
            
            $debugComments[] = $_note->getValues();
            unset($_note);
        }
    
        $this->log->debug->debug('add notes request', $debugComments);
        
        $note = $this->amo->note;
        $note->apiAdd($notes);
        usleep(self::REQUEST_DELAY_MCS);
    
        $this->log->debug->debug('add notes response', [
            'code' => $note->getLastHttpCode(),
            'body' => json_decode($note->getLastHttpResponse(), true),
        ]);
    }
}