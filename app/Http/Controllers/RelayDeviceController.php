<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RelayDeviceController extends Controller
{
    /**
     * Download the latest relay device APK.
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
     */
    public function downloadApk()
    {
        $apkPath = 'relay-device/relay-device.apk';
        
        // Check if APK exists in public storage
        if (!Storage::disk('public')->exists($apkPath)) {
            return response()->json([
                'success' => false,
                'message' => 'APK file not found. Please contact the administrator.',
            ], 404);
        }

        $fullPath = Storage::disk('public')->path($apkPath);
        
        return response()->download($fullPath, 'relay-device.apk', [
            'Content-Type' => 'application/vnd.android.package-archive',
            'Content-Disposition' => 'attachment; filename="relay-device.apk"',
        ]);
    }

    /**
     * Get information about the available APK.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function apkInfo()
    {
        $apkPath = 'relay-device/relay-device.apk';
        
        if (!Storage::disk('public')->exists($apkPath)) {
            return response()->json([
                'success' => false,
                'message' => 'APK file not available',
                'available' => false,
            ]);
        }

        $fullPath = Storage::disk('public')->path($apkPath);
        $fileSize = @filesize($fullPath);
        $lastModified = @filemtime($fullPath);
        
        if ($fileSize === false || $lastModified === false) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to read APK file information',
                'available' => false,
            ], 500);
        }

        return response()->json([
            'success' => true,
            'available' => true,
            'download_url' => route('relay-device.download'),
            'file_size' => $fileSize,
            'file_size_mb' => round($fileSize / 1024 / 1024, 2),
            'last_modified' => date('Y-m-d H:i:s', $lastModified),
            'last_modified_human' => \Carbon\Carbon::createFromTimestamp($lastModified)->diffForHumans(),
        ]);
    }
}
