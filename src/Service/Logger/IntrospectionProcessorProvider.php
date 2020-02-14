<?php
declare(strict_types=1);
namespace LSlim\Service\Logger;

use Pimple\Container;
use PImple\ServiceProviderInterface;
use Monolog\Logger;
use Monolog\Processor\IntrospectionProcessor;

class IntrospectionProcessorProvider implements ServiceProviderInterface
{
    /**
     * @var string|int
     */
    private $level;

    /**
     * @var array
     */
    private $skipClassesPartials;

    /**
     * @var int
     */
    private $skipStackFramesCount;

    /**
     * @param string|int $level
     * @param array $skipClassesPartials
     * @param int $skipStackFramesCount
     */
    public function __construct($level = Logger::DEBUG, array $skipClassesPartials = [], int $skipStackFramesCount = 0)
    {
        $this->level = $level;
        $this->skipClassesPartials = $skipClassesPartials;
        $this->skipStackFramesCount = $skipStackFramesCount;
    }

    public function register(Container $container)
    {
        $level = $this->level;
        $skipClassesPartials = $this->skipClassesPartials;
        $skipStackFramesCount = $this->skipStackFramesCount;

        $container->extend(
            'logger',
            static function (Logger $logger, Container $c) use ($level, $skipClassesPartials, $skipStackFramesCount) {
                $processor = new IntrospectionProcessor($level, $skipClassesPartials, $skipStackFramesCount);
                $logger->pushProcessor($processor);

                return $logger;
            }
        );
    }
}
