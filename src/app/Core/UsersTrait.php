<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 26.02.18
 * Time: 21:41
 */

namespace AmoPRO\Core;

/**
 * Trait UsersTrait
 *
 * @package AmoPRO\Core
 * @property \AmoPRO\Config $config
 */
trait UsersTrait
{
    /**
     * @param int $userId
     *
     * @return void
     */
    protected function setResponsibleUser($userId)
    {
        $this->data['responsible_user'] = $this->getUserArray($userId);
    }
    
    /**
     * @return mixed
     */
    protected function getResponsibleUser()
    {
        if(!empty($this->data['responsible_user'])){
            return $this->data['responsible_user'];
        }
        /** @var array|null $queue */
        $queue = $this->config->getQueue();
        
        if(is_null($queue)){
            $this->setResponsibleUser(null);
        } else {
            $this->setResponsibleUser($this->responsibleUsersQueue($queue));
        }
    
        return $this->data['responsible_user'];
    }
    
    /**
     * @param null|string|int $userEmailOrId
     *
     * @return array
     */
    private function getUserArray($userEmailOrId = null)
    {
        if (!empty($userEmailOrId)) {
            $mask = preg_quote($userEmailOrId, '/');
            // Если функция находит в списке пользователей
            // нужный емейл, то возвращает массив этого пользователя
            foreach ($this->data['account']['users'] as $user) {
                if ((int)$userEmailOrId == (int)$user['id'] ||
                    preg_match("/^($mask)$/i", $user['login'])) {
                    return $user;
                }
            }
        }
        
        // В случае, если пользователь не найден вернем массив админа
        foreach ($this->data['account']['users'] as $user) {
            if ($user['id'] == $this->data['account']['current_user']) {
                return $user;
            }
        }
        
        return null;
    }
    
    /**
     * Метод задает очередь менеджеров
     *
     * @param $queue
     *
     * @return mixed Email|ID  responsible User - емейл|ID очередного менеджера
     */
    private function responsibleUsersQueue($queue)
    {
        if (empty($queue) || empty($queue['users'])) {
            return false;
        }
        $users = $queue['users'];
        if (!is_array($users)) {
            return $users;
        }
        $countUsers = count($users);
        if ($countUsers == 1) {
            return $users[0];
        }
        
        $file = $this->config->conf_path . DIRECTORY_SEPARATOR . 'current_user_' . $queue['name'];
        
        if (file_exists($file)) {
            
            $last_resp = file_get_contents($file);
            if ($last_resp >= $countUsers) {
                $last_resp = 0;
            }
        } else {
            $last_resp = 0;
        }
        
        // Следующий на очереди
        $next_resp = $last_resp + 1;

        // запишем следующего по очереди в файл
        file_put_contents($file, $next_resp);
        
        return $users[$last_resp];
    }
}