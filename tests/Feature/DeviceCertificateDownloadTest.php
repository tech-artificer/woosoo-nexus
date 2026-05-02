<?php

namespace Tests\Feature;

use App\Services\CertificatePathResolver;
use Tests\TestCase;

class DeviceCertificateDownloadTest extends TestCase
{
    public function test_guest_can_access_certificate_download_endpoint(): void
    {
        // Create a temporary cert file so the controller can serve it.
        // CertificatePathResolver is mocked to return this path, isolating the
        // test from the actual filesystem layout of the development/CI machine.
        $certFile = tempnam(sys_get_temp_dir(), 'woosoo-ca') . '.crt';
        file_put_contents($certFile, "-----BEGIN CERTIFICATE-----\nZmFrZQ==\n-----END CERTIFICATE-----");

        $this->app->bind(CertificatePathResolver::class, function () use ($certFile) {
            return new class ($certFile) extends CertificatePathResolver {
                public function __construct(private string $path) {}

                public function resolveCertificatePath(): ?string
                {
                    return $this->path;
                }
            };
        });

        $response = $this->get(route('devices.download-certificate'));

        @unlink($certFile);

        // Endpoint must be public (not login redirect)
        $response->assertOk();
        $response->assertHeader('content-type', 'application/x-x509-ca-cert');

        $contentDisposition = (string) $response->headers->get('content-disposition');
        $this->assertStringContainsString('attachment;', $contentDisposition);
        $this->assertStringContainsString('woosoo-ca.crt', $contentDisposition);
    }
}
