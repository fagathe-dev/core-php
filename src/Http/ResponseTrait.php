<?php

namespace Fagathe\CorePhp\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationListInterface;

trait ResponseTrait
{
    /**
     * @param mixed $data
     * @param int $status
     * @param array<string, mixed> $headers
     * @param array<string, mixed> $context
     * 
     * @return object
     */
    public function sendJson(mixed $data = [], int $status = Response::HTTP_OK, array $headers = [], array $context = []): object
    {
        return $this->response($data, $status, $headers, $context);
    }

    public function sendRedirection(string $link, int $status = Response::HTTP_FOUND, array $headers = []): object
    {
        if (!is_string($link)) {
            throw new \InvalidArgumentException('The link must be a string.');
        }

        return (object) compact('link', 'status', 'headers');
    }

    /**
     * @param array<string, mixed> $headers
     */
    public function sendNoContent(array $headers = []): object
    {
        return $this->response(null, Response::HTTP_NO_CONTENT, $headers);
    }

    /**
     * @param array<mixed>|ConstraintViolationListInterface $violations
     * @param array<string, mixed> $headers
     */
    public function sendViolations(array|ConstraintViolationListInterface $violations, array $headers = []): object
    {
        if ($violations instanceof ConstraintViolationListInterface) {
            $violations = $this->filterViolations($violations);
        }

        return $this->response([
            'title' => 'Validation failed !',
            'violations' => $violations,
            'status' => Response::HTTP_BAD_REQUEST
        ], Response::HTTP_BAD_REQUEST, $headers);
    }

    /**
     * @param ConstraintViolationListInterface $violations
     * @return array<string, string>
     */
    public function filterViolations(ConstraintViolationListInterface $violations): array
    {
        $errors = [];

        if (count($violations) > 0) {
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }
        }

        return $errors;
    }

    /**
     * @param mixed $data
     * @param int $status
     * @param array $headers
     * @param array $context
     * 
     * @return object
     */
    private function response(mixed $data = [], int $status = Response::HTTP_OK, array $headers = [], array $context = []): object
    {
        if ($data === null) {
            $data = [];
        }

        $response = ['data' => $data, 'status' => $status, 'headers' => $headers, 'context' => $context];

        return (object) $response;
    }

    /**
     * @param mixed string
     * 
     * @return object
     */
    public function notFoundResponse(string $message = 'Resource not found'): object
    {
        return $this->response([
            'title' => 'Not Found',
            'message' => $message,
        ], Response::HTTP_NOT_FOUND);
    }
}