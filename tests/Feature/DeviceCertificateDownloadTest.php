<?php

namespace Tests\Feature;

use Tests\TestCase;

class DeviceCertificateDownloadTest extends TestCase
{
    public function test_guest_can_access_certificate_download_endpoint(): void
    {
        $response = $this->get(route('devices.download-certificate'));

        // Endpoint must be public (not login redirect)
        $response->assertOk();
        $response->assertHeader('content-type', 'application/x-x509-ca-cert');

        $contentDisposition = (string) $response->headers->get('content-disposition');
        $this->assertStringContainsString('attachment;', $contentDisposition);
        $this->assertStringContainsString('woosoo-ca.crt', $contentDisposition);
    }
}
