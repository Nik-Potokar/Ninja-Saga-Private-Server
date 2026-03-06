<?php

namespace Tests\Feature;

use Tests\TestCase;
use SabreAMF_Message;
use SabreAMF_InputStream;
use SabreAMF_OutputStream;

class AmfTest extends TestCase
{
    /**
     * Test the AMF Gateway with a valid request.
     */
    public function test_amf_gateway_returns_version(): void
    {
        // 1. Create a Request AMF Message
        $message = new SabreAMF_Message();
        $message->addBody([
            'target'   => 'Game.getVersion',
            'response' => '/1', 
            'data'     => []    
        ]);

        // 2. Serialize to binary
        $stream = new SabreAMF_OutputStream();
        $message->serialize($stream);
        $binaryRequest = $stream->getRawData();

        // 3. Send POST request
        $response = $this->call('POST', '/gateway.php', [], [], [], 
            ['CONTENT_TYPE' => 'application/x-amf'], 
            $binaryRequest
        );

        // 4. Assert HTTP status and headers
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/x-amf');

        // 5. Deserialize the response
        $binaryResponse = $response->getContent();
        $inputStream = new SabreAMF_InputStream($binaryResponse);
        $responseMessage = new SabreAMF_Message();
        $responseMessage->deserialize($inputStream);
        
        $bodies = $responseMessage->getBodies();
        $this->assertCount(1, $bodies);
        
        $responseBody = $bodies[0];
        $this->assertEquals('/1/onResult', $responseBody['target']);
        
        $data = $responseBody['data'];
        $this->assertIsArray($data);
        $this->assertEquals('1.0.0', $data['version']);
    }

    public function test_amf_gateway_handles_invalid_service(): void
    {
        $message = new SabreAMF_Message();
        $message->addBody([
            'target'   => 'Unknown.method',
            'response' => '/2',
            'data'     => []
        ]);

        $stream = new SabreAMF_OutputStream();
        $message->serialize($stream);
        $binaryRequest = $stream->getRawData();

        $response = $this->call('POST', '/gateway.php', [], [], [], 
            ['CONTENT_TYPE' => 'application/x-amf'], 
            $binaryRequest
        );

        $response->assertStatus(200); 

        $inputStream = new SabreAMF_InputStream($response->getContent());
        $responseMessage = new SabreAMF_Message();
        $responseMessage->deserialize($inputStream);
        
        $body = $responseMessage->getBodies()[0];

        $this->assertEquals('/2/onStatus', $body['target']);
        $this->assertEquals('error', $body['data']['level']);
    }
}