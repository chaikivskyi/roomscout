<?php

namespace App\Tests\Application\Admin;

use App\Catalog\Entity\Category;
use App\Tests\Factory\CategoryFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Field\FileFormField;
use Zenstruck\Foundry\Test\Factories;

final class CategoryIconUploadTest extends WebTestCase
{
    use Factories;

    private const string PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private const string SVG_1X1 = '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"/>';

    private KernelBrowser $client;

    /** @var list<string> */
    private array $fixtures = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->client->loginUser(UserFactory::new()->admin()->create());
    }

    protected function tearDown(): void
    {
        $storage = $this->storage();
        foreach ($storage->listContents('')->toArray() as $item) {
            $item->isDir() ? $storage->deleteDirectory($item->path()) : $storage->delete($item->path());
        }

        foreach ($this->fixtures as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    public function testNewCategoryFormExposesAnIconUpload(): void
    {
        $crawler = $this->client->request('GET', '/admin/category/new');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('input[name="Category[iconUrl][file]"]'));
    }

    public function testUploadedIconIsStoredAndPathPersisted(): void
    {
        $crawler = $this->client->request('GET', '/admin/category/new');

        $form = $crawler->filter('form[name="Category"]')->form();
        $form['Category[title]'] = 'Sofas';
        /** @var FileFormField $iconField */
        $iconField = $form['Category[iconUrl][file]'];
        $iconField->upload($this->fixture('png', base64_decode(self::PNG_1X1)));

        $this->client->submit($form);

        self::assertResponseRedirects();

        $category = $this->entityManager()->getRepository(Category::class)->findOneBy(['title' => 'Sofas']);
        self::assertNotNull($category);

        $iconUrl = $category->getIconUrl();
        self::assertNotNull($iconUrl);
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}\.png$/', $iconUrl);
        self::assertTrue($this->storage()->fileExists($iconUrl));
        self::assertSame(base64_decode(self::PNG_1X1), $this->storage()->read($iconUrl));
    }

    public function testCategoryWithoutAnIconIsStillValid(): void
    {
        $crawler = $this->client->request('GET', '/admin/category/new');

        $form = $crawler->filter('form[name="Category"]')->form();
        $form['Category[title]'] = 'Lighting';

        $this->client->submit($form);

        self::assertResponseRedirects();

        $category = $this->entityManager()->getRepository(Category::class)->findOneBy(['title' => 'Lighting']);
        self::assertNotNull($category);
        self::assertNull($category->getIconUrl());
    }

    public function testEditingWithoutReuploadKeepsExistingIcon(): void
    {
        $this->storage()->write('existing.png', base64_decode(self::PNG_1X1));
        $category = CategoryFactory::createOne(['title' => 'Rugs', 'iconUrl' => 'existing.png']);

        $crawler = $this->client->request('GET', sprintf('/admin/category/%s/edit', $category->getId()));

        $form = $crawler->filter('form[name="Category"]')->form();
        $form['Category[title]'] = 'Rugs & Carpets';

        $this->client->submit($form);

        self::assertResponseRedirects();

        $this->entityManager()->clear();
        $reloaded = $this->entityManager()->find(Category::class, $category->getId());
        self::assertNotNull($reloaded);
        self::assertSame('Rugs & Carpets', $reloaded->getTitle());
        self::assertSame('existing.png', $reloaded->getIconUrl());
    }

    public function testSvgIconIsRejected(): void
    {
        $crawler = $this->client->request('GET', '/admin/category/new');

        $form = $crawler->filter('form[name="Category"]')->form();
        $form['Category[title]'] = 'Vectors';
        /** @var FileFormField $file */
        $file = $form['Category[iconUrl][file]'];
        $file->upload($this->fixture('svg', self::SVG_1X1));

        $this->client->submit($form);

        self::assertFalse($this->client->getResponse()->isRedirect());
        self::assertNull($this->entityManager()->getRepository(Category::class)->findOneBy(['title' => 'Vectors']));
        self::assertSame([], $this->storage()->listContents('')->toArray());
    }

    public function testOversizedIconIsRejected(): void
    {
        $crawler = $this->client->request('GET', '/admin/category/new');

        $form = $crawler->filter('form[name="Category"]')->form();
        $form['Category[title]'] = 'Billboards';
        /** @var FileFormField $file */
        $file = $form['Category[iconUrl][file]'];
        $file->upload($this->fixture('png', base64_decode(self::PNG_1X1).str_repeat("\0", 100_000)));

        $this->client->submit($form);

        self::assertFalse($this->client->getResponse()->isRedirect());
        self::assertNull($this->entityManager()->getRepository(Category::class)->findOneBy(['title' => 'Billboards']));
        self::assertSame([], $this->storage()->listContents('')->toArray());
    }

    public function testUploadingANewIconReplacesTheExistingOne(): void
    {
        $this->storage()->write('old.png', base64_decode(self::PNG_1X1));
        $category = CategoryFactory::createOne(['title' => 'Lamps', 'iconUrl' => 'old.png']);

        $crawler = $this->client->request('GET', sprintf('/admin/category/%s/edit', $category->getId()));

        $form = $crawler->filter('form[name="Category"]')->form();
        /** @var FileFormField $file */
        $file = $form['Category[iconUrl][file]'];
        $file->upload($this->fixture('png', base64_decode(self::PNG_1X1)));

        $this->client->submit($form);

        self::assertResponseRedirects();

        $this->entityManager()->clear();
        $reloaded = $this->entityManager()->find(Category::class, $category->getId());
        self::assertNotNull($reloaded);

        $iconUrl = $reloaded->getIconUrl();
        self::assertNotNull($iconUrl);
        self::assertNotSame('old.png', $iconUrl);
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}\.png$/', $iconUrl);
        self::assertTrue($this->storage()->fileExists($iconUrl));
        self::assertFalse($this->storage()->fileExists('old.png'));
    }

    private function fixture(string $extension, string $bytes): string
    {
        $path = sys_get_temp_dir().'/'.uniqid('category-icon-', true).'.'.$extension;
        file_put_contents($path, $bytes);
        $this->fixtures[] = $path;

        return $path;
    }

    private function storage(): FilesystemOperator
    {
        return static::getContainer()->get('category_icons.storage');
    }

    private function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }
}
