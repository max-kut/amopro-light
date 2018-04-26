<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 27.02.18
 * Time: 17:25
 */

namespace AmoPRO\Core;

/**
 * Trait TaskTrait
 *
 * @package AmoPRO\Core
 * @property \AmoCRM\Client $amo
 * @property \AmoPRO\Logger $log
 */
trait TaskTrait
{
    /**
     * @param int $leadId
     * @param array $params
     */
    protected function addTaskToLead($leadId, $params)
    {
        $responsibleUser = $this->getResponsibleUser();
        $task = $this->amo->task;
        $task['element_id'] = $leadId;
        $task['element_type'] = 2;
        $task['task_type'] = $params['task_type'] ?: 1;
        $task['created_user_id'] = 0; //от робота
        $task['text'] = $params['task_text'];
        $task['responsible_user_id'] = $responsibleUser['id'];
        $task['complete_till'] = $params['complete_till'] ?: 'now';
    
        $task->apiAdd();
        usleep(self::REQUEST_DELAY_MCS);
        
        $this->log->debug->debug('add task', [
            'code' => $task->getLastHttpCode(),
            'body' => json_decode($task->getLastHttpResponse(), true),
        ]);
    }
}