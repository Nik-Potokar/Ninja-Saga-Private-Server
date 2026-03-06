<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use SabreAMF_Message;
use SabreAMF_InputStream;
use SabreAMF_OutputStream;
use SabreAMF_Const;
use SabreAMF_AMF3_Wrapper;
use Illuminate\Support\Facades\Log;
use App\Models\Character;
use App\Models\User;

class AmfController extends Controller
{
    /**
     * Handle the AMF Gateway request.
     */
    public function handle(Request $request)
    {
        $content = $request->getContent();

        if (empty($content)) {
            return response('AMF Gateway Ready', 200);
        }

        try {
            $stream = new SabreAMF_InputStream($content);
            $amfRequest = new SabreAMF_Message();
            $amfRequest->deserialize($stream);
        } catch (\Exception $e) {
            Log::error("AMF Deserialization Error: " . $e->getMessage());
            return response('Invalid AMF Data: ' . $e->getMessage(), 500);
        }

        $amfResponse = new SabreAMF_Message();
        $requestEncoding = $amfRequest->getEncoding();
        $useAmf3Wrapper = $requestEncoding === SabreAMF_Const::AMF3;
        $amfResponse->setEncoding($useAmf3Wrapper ? SabreAMF_Const::AMF0 : $requestEncoding);

        foreach ($amfRequest->getBodies() as $requestBody) {
            $responseBody = $this->handleBody($requestBody);
            if ($useAmf3Wrapper) {
                $responseBody['data'] = new SabreAMF_AMF3_Wrapper($this->normalizeAmf3Data($responseBody['data']));
            }

            $amfResponse->addBody($responseBody);
        }

        $outputStream = new SabreAMF_OutputStream();
        $amfResponse->serialize($outputStream);
        $output = $outputStream->getRawData();

        return response($output)
            ->header('Content-Type', 'application/x-amf');
    }

    private function handleBody($requestBody)
    {
        $target = $requestBody['target'];
        $responseTarget = $requestBody['response'];
        $data = $requestBody['data'];

        Log::info("AMF Call: $target", ['args' => $data]);

        try {
            if ($sessionError = $this->validateSessionKey($target, $this->unwrapSingleArgArray($data))) {
                return [
                    'target' => $responseTarget . '/onResult',
                    'response' => null,
                    'data' => $sessionError
                ];
            }

            $result = $this->dispatchService($target, $data);
            Log::info("AMF Result [$target]: ", ['result' => $result]);

            return [
                'target'   => $responseTarget . '/onResult',
                'response' => null,
                'data'     => $result
            ];
        } catch (\Throwable $e) {
            Log::error("AMF Service Error [$target]: " . $e->getMessage());

            return [
                'target'   => $responseTarget . '/onResult',
                'response' => null,
                'data'     => $this->buildServiceErrorPayload($target, $e)
            ];
        }
    }

    private function dispatchService($target, $data)
    {
        $parts = explode('.', $target);
        $baseName = ucfirst($parts[0]);

        if (str_ends_with($baseName, 'Service')) {
            $serviceName = $baseName;
        } else {
            $serviceName = $baseName . 'Service';
        }

        $methodName = $parts[1] ?? 'index';

        $fullClassName = "App\\Services\\Amf\\" . $serviceName;
        if (!class_exists($fullClassName)) {
            $fullClassName = "App\\Services\\" . $serviceName;

            if (!class_exists($fullClassName)) {
                throw new \Exception("Service '$serviceName' not found for target '$target'");
            }
        }

        $serviceInstance = app($fullClassName);

        if (!method_exists($serviceInstance, $methodName)) {
            throw new \Exception("Method '$methodName' not found on service '$serviceName' for target '$target'");
        }

        $method = new \ReflectionMethod($serviceInstance, $methodName);
        $args = $this->normalizeArgsForMethod($data, $method);

        return call_user_func_array([$serviceInstance, $methodName], $args);
    }

    private function validateSessionKey(string $target, $data): ?array
    {
        if (in_array($target, [
            'SystemLogin.loginUser',
            'SystemLogin.registerUser',
            'SystemLogin.checkVersion',
        ], true)) {
            return null;
        }

        if (!is_array($data)) {
            return null;
        }

        $sessionKeys = $this->extractPotentialSessionKeys($data);
        if (empty($sessionKeys)) {
            return null;
        }

        $user = null;
        foreach ($sessionKeys as $key) {
            $foundUser = User::where('session_key', $key)->first();
            if ($foundUser) {
                $user = $foundUser;
                break;
            }
        }

        if (!$user) {
            return ['status' => 0, 'error' => 'Invalid session key'];
        }

        if ($target === 'SystemLogin.getAllCharacters') {
            return null;
        }

        $numericIds = $this->extractNumericIds($data);
        if (empty($numericIds)) {
            return null;
        }

        $ownsCharacter = Character::whereIn('id', $numericIds)
            ->where('user_id', $user->id)
            ->exists();

        if ($ownsCharacter) {
            return null;
        }

        return ['status' => 0, 'error' => 'Invalid session key or character ownership mismatch'];
    }

    private function extractPotentialSessionKeys(array $data): array
    {
        $keys = [];
        foreach ($data as $value) {
            if (is_string($value) && (strlen($value) === 32 || strlen($value) === 40)) {
                $keys[] = $value;
            }
        }

        return $keys;
    }

    private function extractNumericIds(array $data): array
    {
        $ids = [];
        foreach ($data as $value) {
            if (is_numeric($value)) {
                $id = (int)$value;
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    private function normalizeArgsForMethod($data, \ReflectionMethod $method): array
    {
        if (!is_array($data)) {
            return [$data];
        }

        $paramCount = $method->getNumberOfParameters();

        if ($paramCount === 0) {
            return [];
        }

        if ($paramCount === 1) {
            if (array_is_list($data) && count($data) === 1) {
                return [$data[0]];
            }

            return [$data];
        }

        if (array_is_list($data) && count($data) === 1 && is_array($data[0])) {
            return $data[0];
        }

        return $data;
    }

    private function unwrapSingleArgArray($data)
    {
        if (is_array($data) && array_is_list($data) && count($data) === 1 && is_array($data[0])) {
            return $data[0];
        }

        return $data;
    }

    private function normalizeAmf3Data($data)
    {
        if (is_array($data)) {
            if (array_is_list($data)) {
                return array_map(function ($value) {
                    return $this->normalizeAmf3Data($value);
                }, $data);
            }

            $obj = new \stdClass();
            foreach ($data as $key => $value) {
                $obj->{$key} = $this->normalizeAmf3Data($value);
            }

            return $obj;
        }

        return $data;
    }

    private function buildServiceErrorPayload(string $target, \Throwable $e): array
    {
        $message = $target . ' failed: ' . $e->getMessage();

                // Do NOT use status 0 (reserved for ban responses in the Flash client).
        // Use 500 so the client falls into the generic else branch and calls getError().

        return [
            'status' => 500,
            'error' => $message,
        ];
    }
}
