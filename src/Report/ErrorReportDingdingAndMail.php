<?php

declare(strict_types=1);
/**
 * This is a TCC distributed transaction component.
 * @link     https://github.com/luzzhong/tcc-transaction
 * @document https://github.com/luzzhong/tcc-transaction/blob/master/README.md
 * @license  https://github.com/luzzhong/tcc-transaction/blob/master/LICENSE
 */
namespace Zrone\TccTransaction\Report;

use Hyperf\Logger\LoggerFactory;
use Hyperf\Di\Annotation\Inject;

/**
 * 钉钉和邮件发送异常报告
 * Class ErrorReportDingdingAndMail.
 */
class ErrorReportDingdingAndMail implements ErrorReport
{
    /**
     * @Inject
     * @var LoggerFactory
     */
    protected $logger;

    /**
     * 发送消息.
     * @param string $title
     * @param array $msgs
     * @return bool
     */
    public function send(string $title, array $msgs)
    {
        $this->logger->get('tcc')->emergency($title, $msgs);
    }
}
