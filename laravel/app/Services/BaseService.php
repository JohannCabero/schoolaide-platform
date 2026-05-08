<?php

namespace App\Services;

use Illuminate\Http\Request;
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
            DB::beginTransaction();

            $request = app(Request::class);
            $routeId = $request->route('id');
            $reqBody = $request->all();
            $uriPath = $request->path();
            $reqMethod = $request->getMethod();

            $data = call_user_func($function);
            $this->result = $data;

            DB::commit();

            return $this->normalizedResponse($this->code, $this->message, $this->result);
        } catch (\Exception $e) {
            $this->code = 500;
            $this->result = $e->getMessage();

            DB::rollback();

            Log::error('Server Error', ['trace' => $e->getTraceAsString()]);

            return $this->normalizedResponse($this->code, 'Error', $this->result);
        }
    }

    private function normalizedResponse($code, $message, $result)
    {
        return response()->json([
            'code' => $code,
            'message' => $message,
            ...(($code >= 200 && $code < 300) ? ['result' => $result] : ['error' => $result])
        ], $code);
    }
}
