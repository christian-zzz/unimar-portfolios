<?php

declare(strict_types=1);

namespace Tests\Feature\API;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MediaUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('cloudinary');
        Storage::fake('r2');
    }

    /**
     * Test that guest users are unauthorized to upload files.
     */
    public function test_guest_cannot_upload_media(): void
    {
        $response = $this->postJson('/api/media/upload', [
            'file' => UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg'),
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test validation requires a file.
     */
    public function test_upload_requires_file(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/media/upload', []);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'status',
                'message',
                'errors' => ['file']
            ]);
    }

    /**
     * Test uploading an image routes it to the cloudinary disk.
     */
    public function test_uploading_image_routes_to_cloudinary(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->create('avatar.png', 100, 'image/png');

        $response = $this->postJson('/api/media/upload', [
            'file' => $file,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'type' => 'image/png',
                'disk' => 'cloudinary',
            ])
            ->assertJsonStructure([
                'status',
                'url',
                'type',
                'disk'
            ]);

        // Assert file exists on local mock disk
        $filename = basename($response->json('url'));
        Storage::disk('cloudinary')->assertExists($filename);
        Storage::disk('r2')->assertMissing($filename);
    }

    /**
     * Test uploading a non-image document routes it to the r2 disk.
     */
    public function test_uploading_document_routes_to_r2(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->create('document.pdf', 150, 'application/pdf');

        $response = $this->postJson('/api/media/upload', [
            'file' => $file,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'type' => 'application/pdf',
                'disk' => 'r2',
            ])
            ->assertJsonStructure([
                'status',
                'url',
                'type',
                'disk'
            ]);

        // Assert file exists on local mock disk
        $filename = basename($response->json('url'));
        Storage::disk('r2')->assertExists($filename);
        Storage::disk('cloudinary')->assertMissing($filename);
    }

    /**
     * Test validation fails when file exceeds the maximum size.
     */
    public function test_upload_fails_if_file_exceeds_max_size(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Max size is 10MB = 10240 KB. Creating an 11MB file = 11264 KB.
        $file = UploadedFile::fake()->create('heavy.zip', 11264, 'application/zip');

        $response = $this->postJson('/api/media/upload', [
            'file' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'status',
                'message',
                'errors' => ['file']
            ]);
    }

    /**
     * Test listing media.
     */
    public function test_can_list_media(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Upload two files
        $file1 = UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg');
        $this->postJson('/api/media/upload', ['file' => $file1]);

        $file2 = UploadedFile::fake()->create('doc.pdf', 150, 'application/pdf');
        $this->postJson('/api/media/upload', ['file' => $file2]);

        $response = $this->getJson('/api/media');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'media')
            ->assertJsonStructure([
                'status',
                'media' => [
                    '*' => [
                        'id',
                        'user_id',
                        'file_name',
                        'file_path',
                        'mime_type',
                        'size',
                        'disk',
                        'url',
                        'created_at',
                        'updated_at'
                    ]
                ]
            ]);
    }

    /**
     * Test deleting media.
     */
    public function test_can_delete_media(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg');
        $uploadResponse = $this->postJson('/api/media/upload', ['file' => $file]);
        $mediaId = $uploadResponse->json('media.id');

        $filename = $uploadResponse->json('media.file_path');
        Storage::disk('cloudinary')->assertExists($filename);

        $deleteResponse = $this->deleteJson('/api/media/' . $mediaId);

        $deleteResponse->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Archivo eliminado con éxito.'
            ]);

        Storage::disk('cloudinary')->assertMissing($filename);
        $this->assertDatabaseMissing('media', ['id' => $mediaId]);
    }
}
