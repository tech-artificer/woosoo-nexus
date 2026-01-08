<?php

use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // Ensure storage disk is configured
    Storage::fake('public');
});

test('relay device APK download returns 404 when file does not exist', function () {
    $response = $this->get('/relay-device/download');
    
    $response->assertStatus(404);
    $response->assertJson([
        'success' => false,
        'message' => 'APK file not found. Please contact the administrator.',
    ]);
});

test('relay device APK download succeeds when file exists', function () {
    // Create a test APK file
    Storage::disk('public')->put('relay-device/relay-device.apk', 'test apk content');
    
    $response = $this->get('/relay-device/download');
    
    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/vnd.android.package-archive');
    $response->assertDownload('relay-device.apk');
});

test('relay device APK info returns not available when file does not exist', function () {
    $response = $this->get('/relay-device/info');
    
    $response->assertStatus(200);
    $response->assertJson([
        'success' => false,
        'available' => false,
    ]);
});

test('relay device APK info returns file information when file exists', function () {
    // Create a test APK file
    Storage::disk('public')->put('relay-device/relay-device.apk', 'test apk content');
    
    $response = $this->get('/relay-device/info');
    
    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'available' => true,
    ]);
    
    $data = $response->json();
    expect($data)->toHaveKeys(['download_url', 'file_size', 'file_size_mb', 'last_modified', 'last_modified_human']);
    expect($data['file_size'])->toBeGreaterThan(0);
    expect($data['file_size_mb'])->toBeNumeric();
});

test('relay device APK routes are publicly accessible', function () {
    // These routes should not require authentication
    $response = $this->get('/relay-device/info');
    $response->assertStatus(200);
    
    // Should not redirect to login
    $response->assertDontSee('login');
});
