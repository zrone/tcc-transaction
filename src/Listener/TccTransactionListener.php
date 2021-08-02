<?php

declare(strict_types=1);
/**
 * This is a TCC distributed transaction component.
 * @link     https://github.com/luzzhong/tcc-transaction
 * @document https://github.com/luzzhong/tcc-transaction/blob/master/README.md
 * @license  https://github.com/luzzhong/tcc-transaction/blob/master/LICENSE
 */
namespace Zrone\TccTransaction\Listener;

use Hyperf\Logger\LoggerFactory;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Nsq\AbstractConsumer;
use Hyperf\Nsq\Annotation\Consumer;
use Hyperf\Nsq\Message;
use Hyperf\Nsq\Result;
use Hyperf\Redis\Redis;
use Hyperf\Utils\Codec\Json;
use Zrone\TccTransaction\NsqProducer;
use Zrone\TccTransaction\Report\TccErrorReport;
use Zrone\TccTransaction\State;
use Zrone\TccTransaction\TccTransaction;

/**
 * @Consumer(
 *     topic="tcc-transaction",
 *     channel="tcc-transaction",
 *     name="tcc-transaction",
 *     nums=2,
 *     pool="default"
 * )
 */
class TccTransactionListener extends AbstractConsumer
{
    /**
     * @Inject
     * @var LoggerFactory
     */
    protected $logger;

    /**
     * @Inject
     * @var Redis
     */
    protected $redis;

    /**
     * @Inject
     * @var State
     */
    protected $state;

    /**
     * @Inject
     * @var TccTransaction
     */
    protected $tcc;

    public function consume(Message $payload): ?string
    {
        $info = Json::decode($payload->getBody(), false);
        $tccInfo = $this->redis->hget('Tcc', $info->tid);
        $data = Json::decode($tccInfo, true);

        $timeout = config('transaction.nsq_detection_time', 5);
        if ($data['last_update_time'] + $timeout > time()) {
            NsqProducer::sendQueue($info->tid, $info->info, 'tcc-transaction');
            return Result::ACK;
        }
        if ($data['status'] !== 'success') {
            if ($data['tcc_method'] === 'tryMethod') {
                $this->tcc->send($info->info, (object) $data['services'], 'cancelMethod', $info->tid, $data['content'], 1);
            } elseif ($data['tcc_method'] == 'cancelMethod') {
                if ($this->state->upTccStatus($info->tid, 'cancelMethod', 'retried_cancel_nsq_count')) {
                    $this->tcc->send($info->info, (object) $data['services'], 'cancelMethod', $info->tid, $data['content'], 1);
                } else {
                    $this->redis->hDel('Tcc', $info->tid);
                    $this->redis->hSet('TccError', $info->tid, $tccInfo);
                    //异常通知
                    make(TccErrorReport::class)->cancleFailReport('事务异常: 回滚失败', $info->tid, $data['tcc_method'], $data['status']);
                }
            } elseif ($data['tcc_method'] == 'confirmMethod') {
                if ($this->state->upTccStatus($info->tid, 'confirmMethod', 'retried_confirm_nsq_count')) {
                    $this->tcc->send($info->info, (object) $data['services'], 'confirmMethod', $info->tid, $data['content'], 1);
                }
                $this->tcc->send($info->info, (object) $data['services'], 'cancelMethod', $info->tid, $data['content'], 1);
            }
        } elseif ($data['status'] === 'success' && $data['tcc_method'] === 'cancelMethod') {
            $this->redis->hDel('Tcc', $info->tid);
            $this->redis->hSet('TccSuccess', $info->tid, $tccInfo);
        } elseif ($data['status'] === 'success' && $data['tcc_method'] === 'confirmMethod') {
            $this->redis->hDel('Tcc', $info->tid);
            $this->redis->hSet('TccSuccess', $info->tid, $tccInfo);
        }
        $msg = sprintf('事务:%s,%s阶段,执行状态:%s.', $info->tid, $data['tcc_method'], $data['status']);
        $this->logger->get('tcc')->debug($msg);
        return Result::ACK;
    }
}
