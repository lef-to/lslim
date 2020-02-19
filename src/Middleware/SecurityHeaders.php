<?php
declare(strict_types=1);
namespace LSlim\Middleware;

use Psr\Http\Server\MiddlewareInterface;
use ParagonIE\CSPBuilder\CSPBuilder;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SecurityHeaders implements MiddlewareInterface
{
    /**
     * @var bool
     */
    protected $noOpen;

    /**
     * @var bool
     */
    protected $noSniff;

    /**
     * @var string|false
     */
    protected $crossDomainPolicy;

    /**
     * @var string|false
     */
    protected $referrerPolicy;

    /**
     * @var string
     */
    protected $frameOption;

    /**
     * @var string|false
     */
    protected $xssProtectioon;

    /**
     * @var string|array|false
     */
    protected $cspOption;

    public function __construct()
    {
        $this->noOpen = true;
        $this->noSniff = true;
        $this->crossDomainPolicy = 'none';
        $this->referrerPolicy = 'strict-origin-when-cross-origin';
        $this->frameOption = 'sameorigin';
        $this->xssProtectioon = 'block';
        $this->cspOption = [
            'script-src' => [ 'self' => true ],
            'object-src' => [ 'self' => true ],
            'frame-ancestors' => []
        ];
    }

    /**
     * @param bool $value
     * @return self
     */
    public function setNoOpen(bool $value): self
    {
        $this->noOpen = $value;
        return $this;
    }

    /**
     * @param bool $value
     * @return self
     */
    public function setNoSniff(bool $value): self
    {
        $this->noSniff = $value;
        return $this;
    }

    /**
     * @param string|false $value
     * @return self
     */
    public function setCrossDomainPolicy($value): self
    {
        if ($value !== false) {
            $options = ['all', 'none', 'master-only', 'by-content-type', 'by-ftp-filename'];

            if (!in_array($value, $options)) {
                throw new InvalidArgumentException("Invalid argument: " . $value);
            }
        }
        $this->crossDomainPolicy = $value;
        return $this;
    }

    /**
     * @param string|false $value
     * @return self
     */
    public function setRefererPolicy($value): self
    {
        if ($value !== false) {
            $options = [
                'no-referrer', 'no-referrer-when-downgrade', 'origin', 'origin-when-cross-origin',
                'same-origin', 'strict-origin', 'strict-origin-when-cross-origin',
                'unsafe-url'
            ];

            if (!in_array($value, $options)) {
                throw new InvalidArgumentException("Invalid argument: " . $value);
            }
        }

        $this->referrerPolicy = $value;
        return $this;
    }

    /**
     * @param string|false $value
     * @return self
     */
    public function setFrameOption($value): self
    {
        if ($value !== false) {
            $options = [ 'deny', ' sameorigin' ];

            if (!in_array($value, $options)) {
                throw new InvalidArgumentException("Invalid argument: " . $value);
            }
        }

        $this->frameOption = $value;
        return $this;
    }

    /**
     * @param string|false $value
     * @return self
     */
    public function setXssProtection($value): self
    {
        if ($value !== false) {
            $mode = (string)$value;
            $options = ['1', '0', 'block'];

            if (!in_array($mode, $options)) {
                throw new InvalidArgumentException("Invalid argument: " . $value);
            }
        }

        $this->xssProtectioon = $value;
        return $this;
    }

    /**
     * @param string|array|false $value
     * @return self
     */
    public function setCspOption($value): self
    {
        $this->cspOption = $value;
        return $this;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if ($this->noOpen !== false && !$response->hasHeader('Referrer-Policy')) {
            $response = $response->withHeader('X-Download-Options', 'noopen');
        }

        if ($this->noSniff !== false && !$response->hasHeader('X-Content-Type-Options')) {
            $response = $response->withHeader('X-Content-Type-Options', 'nosniff');
        }

        if ($this->crossDomainPolicy !== false && !$response->hasHeader('X-Permitted-Cross-Domain-Policies')) {
            $response = $response->withHeader('X-Permitted-Cross-Domain-Policies', $this->crossDomainPolicy);
        }

        if ($this->referrerPolicy !== false && !$response->hasHeader('Referrer-Policy')) {
            $response = $response->withHeader('Referrer-Policy', $this->referrerPolicy);
        }

        if ($this->frameOption !== false && !$response->hasHeader('X-Frame-Options')) {
            $response = $response->withHeader('X-Frame-Options', $this->frameOption);
        }

        if ($this->xssProtectioon !== false && !$response->hasHeader('X-XSS-Protection')) {
            $mode = ($this->xssProtectioon === 'block') ? '1; mode=block' : $this->xssProtectioon;
            $response = $response->withHeader('X-XSS-Protection', $mode);
        }

        if ($this->cspOption !== false) {
            $builder = (is_array($this->cspOption))
                ? CSPBuilder::fromArray($this->cspOption)
                : CSPBuilder::fromFile($this->cspOption);

            $builder->compile();
            $response = $builder->injectCSPHeader($response, true);
        }

        return $response;
    }
}
