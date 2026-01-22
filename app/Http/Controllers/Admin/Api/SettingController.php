<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

use Illuminate\Support\Str;

class SettingController extends Controller
{
    /**
     * Get all settings.
     */
    public function index()
    {
        $settings = Setting::all()->pluck('value', 'key');
        return response()->json($settings);
    }

    /**
     * Update a specific setting.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'key' => 'required|string',
            'value' => 'nullable|string',
        ]);

        $setting = Setting::updateOrCreate(
            ['key' => $validated['key']],
            ['value' => $validated['value']]
        );

        return response()->json($setting);
    }
    /**
     * Upload a setting image to Cloudflare R2.
     */
    public function uploadImage(Request $request)
    {
        \Log::info('Upload image request received', [
            'setting_key' => $request->input('setting_key'),
            'has_file' => $request->hasFile('image'),
        ]);

        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,gif,webp|max:5120', // 5MB max
            'setting_key' => 'required|string',
        ]);

        $file = $request->file('image');
        $settingKey = $request->input('setting_key');
        
        \Log::info('File details', [
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);
        
        // Generate a unique filename to prevent caching issues
        // e.g., settings/sold_out_image_home_UUID.jpg
        $extension = $file->getClientOriginalExtension();
        $uuid = Str::uuid();
        $filename = "{$settingKey}_{$uuid}.{$extension}";
        $path = "settings/{$filename}";

        \Log::info('Generated path', ['path' => $path]);

        try {
            // Check R2 configuration
            $r2Config = config('filesystems.disks.r2');
            \Log::info('R2 Configuration', [
                'bucket' => $r2Config['bucket'] ?? 'NOT SET',
                'endpoint' => $r2Config['endpoint'] ?? 'NOT SET',
                'url' => $r2Config['url'] ?? 'NOT SET',
                'has_key' => !empty($r2Config['key']),
                'has_secret' => !empty($r2Config['secret']),
            ]);

            // Upload to R2
            $disk = \Illuminate\Support\Facades\Storage::disk('r2');
            
            \Log::info('Starting R2 upload...');
            $uploaded = $disk->put($path, file_get_contents($file), 'public');
            
            if (!$uploaded) {
                throw new \Exception('Upload to R2 returned false');
            }
            
            \Log::info('Upload successful', ['uploaded' => $uploaded]);

            // Verify file exists
            $exists = $disk->exists($path);
            \Log::info('File existence check', ['exists' => $exists]);

            // Get the public URL
            $url = config('filesystems.disks.r2.url') . '/' . $path;
            \Log::info('Generated URL', ['url' => $url]);

            // Automatically update the setting
            $setting = Setting::updateOrCreate(
                ['key' => $settingKey],
                ['value' => $url]
            );

            \Log::info('Setting updated', [
                'key' => $setting->key,
                'value' => $setting->value,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Image uploaded and setting updated successfully',
                'data' => [
                    'url' => $url,
                    'path' => $path,
                    'key' => $settingKey,
                    'uploaded' => $uploaded,
                    'exists' => $exists,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload image: ' . $e->getMessage(),
                'error_details' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            ], 500);
        }
    }
}
