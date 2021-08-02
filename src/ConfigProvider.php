<?php

declare(strict_types=1);
/**
 * This is a TCC distributed transaction component.
 * @link     https://github.com/luzzhong/tcc-transaction
 * @document https://github.com/luzzhong/tcc-transaction/blob/master/README.md
 * @license  https://github.com/luzzhong/tcc-transaction/blob/master/LICENSE
 */
namespace Zrone\TccTransaction;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'listeners' => [
            ],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'The config for transaction.',
                    'source' => __DIR__ . '/../publish/transaction.php',
                    'destination' => BASE_PATH . '/config/autoload/transaction.php',
                ],
            ],
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
            'dependencies' => [
                \Zrone\TccTransaction\Report\ErrorReport::class => \Zrone\TccTransaction\Report\ErrorReportDingdingAndMail::class,
            ],
        ];
    }
}
