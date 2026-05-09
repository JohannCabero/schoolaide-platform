<?php

namespace App\Services;

use App\Exceptions\ConcurrencyConflictException;
use App\Exceptions\InvalidRequestStateException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BaseService
{
    protected $code = 200;
    protected $message = 'Success';
    protected $result = null;

    public function executeFunction(callable $function)
    {
        try {
            $this->result = DB::transaction(function () use ($function) {
                $data = call_user_func($function);

                if (isset($data['error'])) {
                    throw new \Exception($data['error']);
                }

                return $data;
            });

            return $this->normalizedResponse($this->code, $this->message, $this->result);
        } catch (AuthorizationException $e) {
            $this->code = 403;
            $this->result = $e->getMessage();

            Log::warning('Authorization Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->normalizedResponse($this->code, 'Forbidden', $this->result);
        } catch (InvalidRequestStateException $e) {
            $this->code = 422;
            $this->result = $e->getMessage();

            Log::warning('Invalid Request State', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->normalizedResponse($this->code, 'Unprocessable Entity', $this->result);
        } catch (ConcurrencyConflictException $e) {
            $this->code = 409;
            $this->result = $e->getMessage();

            Log::warning('Concurrency Conflict', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->normalizedResponse($this->code, 'Conflict', $this->result);
        } catch (UniqueConstraintViolationException $e) {
            $this->code = 422;
            $this->result = 'A duplicate record already exists.';

            return $this->normalizedResponse($this->code, 'Unprocessable Entity', $this->result);
        } catch (ModelNotFoundException $e) {
            $this->code = 404;
            $this->result = 'Resource not found.';

            return $this->normalizedResponse($this->code, 'Not Found', $this->result);
        } catch (\Throwable $e) {
            $this->code = 500;
            $this->result = $e->getMessage();

            Log::error('Server Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->normalizedResponse($this->code, 'Error', $this->result);
        }
    }

    protected function normalizedResponse($code, $message, $result)
    {
        if ($code >= 200 && $code < 300 && $result instanceof ResourceCollection) {
            $resolved = $result->resolve(request());
            return response()->json(
                array_merge(['code' => $code, 'message' => $message], $resolved),
                $code
            );
        }

        return response()->json([
            'code' => $code,
            'message' => $message,
            ...(($code >= 200 && $code < 300) ? ['data' => $result] : ['error' => $result])
        ], $code);
    }
}