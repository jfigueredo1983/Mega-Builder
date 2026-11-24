<?php

declare(strict_types=1);

namespace MegaBuilder\Tests;

use MegaBuilder\Repository\TemplateRepository;
use MegaBuilder\Service\MediaManagerService;
use PHPUnit\Framework\TestCase;

final class TargetingAndMediaTest extends TestCase
{
    private const PRODUCT_PAGE = ['section' => 'product', 'entity_id' => 42];
    private const HOME_PAGE = ['section' => 'homepage', 'entity_id' => 0];

    public function testPageSpecificSectionsOnlyRenderOnTheirPage(): void
    {
        self::assertTrue(TemplateRepository::appliesToPage('product', 'all', [], self::PRODUCT_PAGE));
        self::assertFalse(TemplateRepository::appliesToPage('product', 'all', [], self::HOME_PAGE));
        self::assertFalse(TemplateRepository::appliesToPage('category', 'all', [], self::PRODUCT_PAGE));
    }

    public function testGlobalSectionsRenderEverywhere(): void
    {
        foreach (TemplateRepository::GLOBAL_SECTIONS as $section) {
            self::assertTrue(TemplateRepository::appliesToPage($section, 'all', [], self::PRODUCT_PAGE));
            self::assertTrue(TemplateRepository::appliesToPage($section, 'all', [], ['section' => null, 'entity_id' => 0]));
        }
    }

    public function testSpecificIdsMatchTheViewedEntity(): void
    {
        self::assertTrue(TemplateRepository::appliesToPage('product', 'specific_ids', [42, 7], self::PRODUCT_PAGE));
        self::assertTrue(TemplateRepository::appliesToPage('product', 'specific_ids', ['42'], self::PRODUCT_PAGE));
        self::assertFalse(TemplateRepository::appliesToPage('product', 'specific_ids', [7], self::PRODUCT_PAGE));
        self::assertFalse(TemplateRepository::appliesToPage('homepage', 'specific_ids', [1], self::HOME_PAGE));
    }

    public function testRenderingWithoutDatabaseIsEmpty(): void
    {
        self::assertSame(['html' => '', 'css' => ''], TemplateRepository::renderActiveForHook('displayHome', self::HOME_PAGE));
    }

    public function testImageBytesMustMatchExtension(): void
    {
        $png = (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', true);
        self::assertTrue(MediaManagerService::bytesMatchExtension($png, 'png'));
        self::assertFalse(MediaManagerService::bytesMatchExtension($png, 'jpg'));
        self::assertFalse(MediaManagerService::bytesMatchExtension('<?php echo 1; ?>', 'png'));
        self::assertFalse(MediaManagerService::bytesMatchExtension($png, 'php'));
    }

    public function testFolderSlugBlocksTraversal(): void
    {
        self::assertSame('etc/passwd', MediaManagerService::sanitizeFolderSlug('../../etc/passwd'));
        self::assertSame('a/b/c', MediaManagerService::sanitizeFolderSlug('a/b/c/d/e'));
        self::assertSame('my-folder', MediaManagerService::sanitizeFolderSlug('My Folder!'));
    }

    public function testUploadRenameAndDeleteStayInsideUploads(): void
    {
        $png = (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', true);
        $folder = 'phpunit-' . bin2hex(random_bytes(4));

        $a = MediaManagerService::saveMediaContent('Pixel Test.png', $png, $folder);
        $b = MediaManagerService::saveMediaContent('Pixel Test.png', $png, $folder);
        try {
            self::assertTrue($a['ok']);
            self::assertSame('pixel-test.png', $a['file']['name']);
            self::assertSame('pixel-test-1.png', $b['file']['name']);

            $evil = MediaManagerService::saveMediaContent('shell.php', '<?php system($_GET["c"]);', $folder);
            self::assertFalse($evil['ok']);

            self::assertFalse(MediaManagerService::deleteMedia('../../../megabuilder.php'));
            self::assertFalse(MediaManagerService::deleteMedia('index.php'));
            self::assertTrue(MediaManagerService::deleteMedia($a['file']['rel_path']));
            self::assertTrue(MediaManagerService::deleteMedia($b['file']['rel_path']));
        } finally {
            $dir = MediaManagerService::getBaseUploadDir() . '/' . $folder;
            @unlink($dir . '/index.php');
            @rmdir($dir);
        }
    }
}
