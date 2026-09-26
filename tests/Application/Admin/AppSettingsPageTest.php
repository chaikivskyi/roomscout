<?php

namespace App\Tests\Application\Admin;

use App\Tests\Factory\UserFactory;
use Craue\ConfigBundle\Util\Config;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;

final class AppSettingsPageTest extends WebTestCase
{
    use Factories;

    private const string PATH = '/admin/settings';

    private const string FIELD = 'app_settings[free_search_count]';

    private const string IMAGE_SIZE_FIELD = 'app_settings[free_max_image_size_mb]';

    private KernelBrowser $client;

    /** @var array<string, ?string> */
    private array $originalSettings = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();

        $config = $this->config();

        $this->originalSettings = [
            'free_search_count' => $config->get('free_search_count'),
            'free_max_image_size_mb' => $config->get('free_max_image_size_mb'),
        ];
    }

    protected function tearDown(): void
    {
        $config = $this->config();
        foreach ($this->originalSettings as $name => $value) {
            $config->set($name, $value);
        }

        parent::tearDown();
    }

    public function testAnonymousVisitorIsRedirectedToLogin(): void
    {
        $this->client->request('GET', self::PATH);

        self::assertResponseRedirects('http://localhost/login');
    }

    public function testSettingsPageRendersInsideTheAdminLayout(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', self::PATH);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('aside.sidebar'), 'The page should render inside the EasyAdmin chrome.');

        $field = $crawler->filter(sprintf('input[name="%s"]', self::FIELD));
        self::assertCount(1, $field);
        self::assertStringContainsString('form-control', (string) $field->attr('class'));

        $imageSizeField = $crawler->filter(sprintf('input[name="%s"]', self::IMAGE_SIZE_FIELD));
        self::assertCount(1, $imageSizeField);
        self::assertStringContainsString('form-control', (string) $imageSizeField->attr('class'));
    }

    public function testAdminCanUpdateFreeSearchCount(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', self::PATH);

        $form = $crawler->filter('form')->form();
        $form[self::FIELD] = '7';

        $this->client->submit($form);

        self::assertResponseRedirects(self::PATH);
        self::assertSame('7', $this->config()->get('free_search_count'));
    }

    public function testAdminCanUpdateFreeImageSizeMb(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', self::PATH);

        $form = $crawler->filter('form')->form();
        $form[self::IMAGE_SIZE_FIELD] = '20';

        $this->client->submit($form);

        self::assertResponseRedirects(self::PATH);
        self::assertSame('20', $this->config()->get('free_max_image_size_mb'));
    }

    public function testNegativeFreeSearchCountIsRejected(): void
    {
        $this->submitAndAssertRejected('-1');
    }

    public function testNonNumericFreeSearchCountIsRejected(): void
    {
        $this->submitAndAssertRejected('abc');
    }

    public function testEmptyFreeSearchCountIsRejected(): void
    {
        $this->submitAndAssertRejected('');
    }

    public function testNullStoredValueIsNotSilentlyOverwrittenWithZero(): void
    {
        $this->loginAsAdmin();

        $this->config()->set('free_search_count', null);

        $crawler = $this->client->request('GET', self::PATH);

        $field = $crawler->filter(sprintf('input[name="%s"]', self::FIELD));
        self::assertSame('', (string) $field->attr('value'), 'A NULL value should render as an empty field, not as 0.');

        $this->client->submit($crawler->filter('form')->form());

        self::assertFalse($this->client->getResponse()->isRedirect());
        self::assertNull($this->config()->get('free_search_count'), 'The NotNull constraint should reject the empty field instead of storing "0".');
    }

    private function submitAndAssertRejected(string $value): void
    {
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', self::PATH);

        $form = $crawler->filter('form')->form();
        $form[self::FIELD] = $value;

        $this->client->submit($form);

        self::assertFalse($this->client->getResponse()->isRedirect());
        self::assertSame('3', $this->config()->get('free_search_count'), 'The seeded value should be untouched.');
    }

    public function testDashboardLinksToTheSettingsPage(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', '/admin');

        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter(sprintf('a[href$="%s"]', self::PATH))->count());
    }

    private function loginAsAdmin(): void
    {
        $this->client->loginUser(UserFactory::new()->admin()->create());
    }

    private function config(): Config
    {
        return static::getContainer()->get('craue_config');
    }
}
