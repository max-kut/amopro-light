<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 27.02.18
 * Time: 17:53
 */

namespace AmoPRO\Core;

/**
 * Trait AccountTrait
 *
 * @package AmoPRO\Core
 * @property \AmoCRM\Client $amo
 * @property \AmoPRO\Logger $log
 */
trait AccountTrait
{
    /**
     * @return void
     */
    protected function setAmoAccount()
    {
        $accountDataCacheFile = $this->config->conf_path . DIRECTORY_SEPARATOR . 'account.json';
        if (file_exists($accountDataCacheFile)) {
            $fileTime = filemtime($accountDataCacheFile);
            if (time() - $fileTime < 60 * 10) {
                try {
                    $this->data['account'] = json_decode(file_get_contents($accountDataCacheFile), true);
                } catch (\Exception $e) {
                    $this->updateAccount($accountDataCacheFile);
                }
            } else {
                $this->updateAccount($accountDataCacheFile);
            }
        } else {
            $this->updateAccount($accountDataCacheFile);
        }
    }
    
    private function updateAccount($accountDataCacheFile)
    {
        $account               = $this->amo->account;
        $this->data['account'] = $account->apiCurrent();
        file_put_contents($accountDataCacheFile, json_encode($this->data['account']), LOCK_EX);
        $this->log->debug->debug('account response', [
            'code' => $account->getLastHttpCode(),
        ]);
        usleep(self::REQUEST_DELAY_MCS);
    }
}