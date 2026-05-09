<?php

namespace Tests\Feature;

use Tests\TestCase;

class DeploymentInfoTest extends TestCase
{
    public function test_deployment_info_endpoint_returns_expected_keys(): void
    {
        $response = $this->getJson('/api/deployment-info');

        $response->assertOk()->assertJsonStructure([
            'app_name',
            'app_environment',
            'app_version',
            'public_host',
            'reverb_host',
            'build_sha',
            'build_time',
        ]);
    }
}
