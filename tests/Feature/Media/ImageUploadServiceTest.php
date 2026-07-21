<?php

namespace Tests\Feature\Media;

use App\Http\Requests\Concerns\ValidatesUploadedImage;
use App\Http\Resources\Concerns\ResolvesImageUrl;
use App\Models\Testimonial;
use App\Services\Media\ImageUploadService;
use App\Services\Media\MediaPlacement;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\ImageManager;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The upload foundation Phases 5, 8, 9 and 10 all build on.
 *
 * There are no routes yet, so what is pinned here is the contract those phases
 * will depend on: the storage layout, that a replacement leaves nothing
 * orphaned, that a private file never lands on the public disk, and that the
 * writer and the reader agree on where a file went.
 */
class ImageUploadServiceTest extends TestCase
{
    use ResolvesImageUrl;
    use ValidatesUploadedImage;

    private ImageUploadService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');

        $this->service = new ImageUploadService;
    }

    public function test_it_writes_the_original_and_every_scaled_variant(): void
    {
        $filename = $this->service->store(MediaPlacement::Cms, Testimonial::IMAGE_COLLECTION, $this->upload());

        foreach (MediaPlacement::Cms->variants() as $variant) {
            Storage::disk('public')->assertExists("cms/testimonials/{$variant}/{$filename}");
        }

        $this->assertCount(4, MediaPlacement::Cms->variants());
    }

    public function test_it_scales_each_variant_down_to_its_longest_edge(): void
    {
        $filename = $this->service->store(MediaPlacement::Cms, Testimonial::IMAGE_COLLECTION, $this->upload(width: 2400, height: 1200));

        foreach (MediaPlacement::Cms->sizes() as $variant => $longestEdge) {
            $image = ImageManager::gd()->read(Storage::disk('public')->get("cms/testimonials/{$variant}/{$filename}"));

            $this->assertSame($longestEdge, $image->width(), "The {$variant} variant was not scaled to its longest edge.");
        }

        $original = ImageManager::gd()->read(Storage::disk('public')->get("cms/testimonials/original/{$filename}"));

        $this->assertSame(2400, $original->width(), 'The original must be kept unscaled so larger variants can be regenerated.');
    }

    public function test_it_does_not_upscale_an_image_smaller_than_a_variant(): void
    {
        $filename = $this->service->store(MediaPlacement::Cms, Testimonial::IMAGE_COLLECTION, $this->upload(width: 200, height: 100));

        $large = ImageManager::gd()->read(Storage::disk('public')->get("cms/testimonials/large/{$filename}"));

        $this->assertSame(200, $large->width());
    }

    public function test_it_stores_personal_files_on_the_private_disk_only(): void
    {
        $filename = $this->service->store(MediaPlacement::Personal, 'customer/profile', $this->upload());

        Storage::disk('local')->assertExists("customer/profile/medium/{$filename}");
        Storage::disk('public')->assertDirectoryEmpty('/');
    }

    public function test_it_gives_personal_and_cms_files_their_own_ceilings(): void
    {
        $this->assertSame(1600, MediaPlacement::Cms->sizes()['large']);
        $this->assertSame(800, MediaPlacement::Personal->sizes()['large']);
    }

    #[DataProvider('formatProvider')]
    public function test_it_keeps_transparency_capable_formats_and_re_encodes_the_rest(string $uploadedExtension, string $storedExtension): void
    {
        $filename = $this->service->store(
            MediaPlacement::Cms,
            Testimonial::IMAGE_COLLECTION,
            $this->upload(name: 'logo.'.$uploadedExtension)
        );

        $this->assertStringEndsWith('.'.$storedExtension, $filename);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function formatProvider(): array
    {
        return [
            'png keeps its alpha channel' => ['png', 'png'],
            'webp keeps its alpha channel' => ['webp', 'webp'],
            'jpg is re-encoded as jpg' => ['jpg', 'jpg'],
            'jpeg is normalised to jpg' => ['jpeg', 'jpg'],
        ];
    }

    public function test_it_names_stored_files_randomly(): void
    {
        $first = $this->service->store(MediaPlacement::Cms, Testimonial::IMAGE_COLLECTION, $this->upload(name: 'avatar.jpg'));
        $second = $this->service->store(MediaPlacement::Cms, Testimonial::IMAGE_COLLECTION, $this->upload(name: 'avatar.jpg'));

        $this->assertNotSame($first, $second);
        $this->assertStringNotContainsString('avatar', $first);
        $this->assertSame(44, strlen($first), 'A 40-character random name plus ".jpg".');
    }

    public function test_replacing_an_image_removes_every_variant_of_the_old_one(): void
    {
        $old = $this->service->store(MediaPlacement::Cms, Testimonial::IMAGE_COLLECTION, $this->upload());

        $new = $this->service->store(MediaPlacement::Cms, Testimonial::IMAGE_COLLECTION, $this->upload(), replacing: $old);

        foreach (MediaPlacement::Cms->variants() as $variant) {
            Storage::disk('public')->assertMissing("cms/testimonials/{$variant}/{$old}");
            Storage::disk('public')->assertExists("cms/testimonials/{$variant}/{$new}");
        }
    }

    public function test_storing_without_a_replacement_leaves_existing_images_alone(): void
    {
        $existing = $this->service->store(MediaPlacement::Cms, Testimonial::IMAGE_COLLECTION, $this->upload());

        $this->service->store(MediaPlacement::Cms, Testimonial::IMAGE_COLLECTION, $this->upload());

        Storage::disk('public')->assertExists("cms/testimonials/medium/{$existing}");
    }

    public function test_delete_removes_every_variant(): void
    {
        $filename = $this->service->store(MediaPlacement::Cms, Testimonial::IMAGE_COLLECTION, $this->upload());

        $this->service->delete(MediaPlacement::Cms, Testimonial::IMAGE_COLLECTION, $filename);

        Storage::disk('public')->assertDirectoryEmpty('cms/testimonials/medium');
        Storage::disk('public')->assertDirectoryEmpty('cms/testimonials/original');
    }

    public function test_deleting_a_null_filename_is_a_no_op(): void
    {
        $this->service->delete(MediaPlacement::Cms, Testimonial::IMAGE_COLLECTION, null);

        $this->expectNotToPerformAssertions();
    }

    public function test_path_for_returns_the_stored_variant(): void
    {
        $filename = $this->service->store(MediaPlacement::Personal, 'customer/profile', $this->upload());

        $this->assertSame(
            "customer/profile/small/{$filename}",
            $this->service->pathFor(MediaPlacement::Personal, 'customer/profile', $filename, 'small')
        );
    }

    public function test_path_for_falls_back_to_the_default_variant(): void
    {
        $filename = $this->service->store(MediaPlacement::Personal, 'customer/profile', $this->upload());

        $this->assertSame(
            "customer/profile/medium/{$filename}",
            $this->service->pathFor(MediaPlacement::Personal, 'customer/profile', $filename, 'gigantic')
        );
    }

    public function test_path_for_returns_null_when_there_is_no_file(): void
    {
        $this->assertNull($this->service->pathFor(MediaPlacement::Personal, 'customer/profile', null));
        $this->assertNull($this->service->pathFor(MediaPlacement::Personal, 'customer/profile', 'never-stored.jpg'));
    }

    /**
     * The one agreement no type system enforces: the Resource layer reads back
     * exactly what this service wrote. Both go through MediaPlacement, and this
     * is what proves it stayed that way.
     */
    public function test_the_resource_layer_resolves_the_path_the_service_wrote(): void
    {
        $filename = $this->service->store(MediaPlacement::Cms, Testimonial::IMAGE_COLLECTION, $this->upload());

        $url = $this->resolveImageUrl(Testimonial::IMAGE_COLLECTION, $filename, null, 'small');

        $this->assertStringEndsWith("cms/testimonials/small/{$filename}", (string) $url);
        Storage::disk('public')->assertExists("cms/testimonials/small/{$filename}");
    }

    public function test_an_external_url_is_used_only_when_no_file_is_stored(): void
    {
        $this->assertSame(
            'https://images.example.com/hero.jpg',
            $this->resolveImageUrl(Testimonial::IMAGE_COLLECTION, null, 'https://images.example.com/hero.jpg')
        );
    }

    public function test_it_rejects_an_upload_larger_than_the_ceiling(): void
    {
        $oversized = UploadedFile::fake()->create('huge.jpg', self::MAX_KILOBYTES + 1, 'image/jpeg');

        $this->assertTrue($this->validate($oversized)->fails());
    }

    public function test_it_rejects_a_file_that_is_not_an_allowed_image(): void
    {
        $document = UploadedFile::fake()->create('contract.pdf', 10, 'application/pdf');

        $this->assertTrue($this->validate($document)->fails());
    }

    public function test_it_rejects_svg_because_it_is_an_xss_vector_on_a_public_disk(): void
    {
        $svg = UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml');

        $this->assertTrue($this->validate($svg)->fails());
        $this->assertNotContains('svg', self::ALLOWED_EXTENSIONS);
    }

    public function test_it_accepts_every_allowed_format(): void
    {
        foreach (['jpg', 'png', 'webp'] as $extension) {
            $this->assertFalse(
                $this->validate($this->upload(name: 'logo.'.$extension))->fails(),
                "A {$extension} upload should have been accepted."
            );
        }
    }

    public function test_a_missing_image_fails_only_when_required(): void
    {
        $this->assertTrue(Validator::make([], ['image' => $this->uploadedImageRules(required: true)])->fails());
        $this->assertFalse(Validator::make([], ['image' => $this->uploadedImageRules(required: false)])->fails());
    }

    public function test_it_labels_upload_failures_with_the_field_the_client_sent(): void
    {
        $messages = $this->uploadedImageMessages('logo', 'The site logo');

        $this->assertSame('The site logo may not be larger than 2 MB.', $messages['logo.max']);
        $this->assertSame('The site logo must be a jpg, jpeg, png, webp file.', $messages['logo.mimes']);
    }

    private function upload(string $name = 'photo.jpg', int $width = 1200, int $height = 800): UploadedFile
    {
        return UploadedFile::fake()->image($name, $width, $height);
    }

    private function validate(UploadedFile $file): \Illuminate\Validation\Validator
    {
        return Validator::make(['image' => $file], ['image' => $this->uploadedImageRules(required: true)]);
    }
}
