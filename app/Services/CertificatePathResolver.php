<?php

namespace App\Services;

class CertificatePathResolver
{
    public function resolveCertificatePath(): ?string
    {
        $candidatePaths = [
            base_path('docker/certs/fullchain.pem'),
            base_path('docker/certs/fullchain.crt'),
            storage_path('app/public/certificates/woosoo-ca.der'),
            storage_path('app/public/certificates/CAROOT.pem'),
            base_path('certs/ca.crt'),
            base_path('../certs/ca.crt'),
        ];

        foreach ($candidatePaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }
}
