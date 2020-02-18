<?php
declare(strict_types=1);
namespace LSlim\Middleware;

use Monolog\Logger;
use Monolog\Processor\ProcessorInterface;
use Pimple\Container;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class LoggerExtender implements MiddlewareInterface
{
    /**
     * @var \Pimple\Container
     */
    protected $container;

    /**
     * @var string
     */
    protected $clientIpAttribute;

    public function __construct(Container $container, $clientIpAttribute = 'client-ip')
    {
        $this->container = $container;
        $this->clientIpAttribute = $clientIpAttribute;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $attr = $this->clientIpAttribute;

        $this->container->extend('logger', static function (Logger $logger, Container $c) use ($request, $attr) {
            $processor = new class($request, $attr) implements ProcessorInterface {
                private $request;
                private $attr;

                public function __construct(ServerRequestInterface $request, $attr)
                {
                    $this->request = $request;
                    $this->attr = $attr;
                }

                public function __invoke(array $record)
                {
                    $ip = $this->request->getAttribute($this->attr);
                    if ($ip === null) {
                        $param = $this->request->getServerParams();
                        $ip = trim($param['REMOTE_ADDR'] ?? '', '[]');
                        if ($ip && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6) === false) {
                            $ip = '';
                        }
                    }

                    $record['extra'] = array_merge(
                        $record['extra'],
                        [
                            'client_ip' => $ip,
                            'http_method' => $this->request->getMethod(),
                            'request_path' => $this->request->getUri()->getPath()
                        ]
                    );

                    return $record;
                }
            };

            $logger->pushProcessor($processor);
            return $logger;
        });

        return $handler->handle($request);
    }
}
