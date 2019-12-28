<?php
declare(strict_types=1);
namespace LSlim\Response;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class JsonResponse
{
    public static function create(ResponseInterface $response, $data, $option = 0)
    {
        $payload = json_encode($data, $option);
        if ($payload === false) {
            throw new RuntimeException('Failed to encode json: ' . json_last_error_msg());
        }

        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json');
    }
}
