<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Support\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AppTestCase;

/**
 * Runs every public script in frontend/ against the test database, proving that each
 * one boots, routes to its controller and produces output.
 */
final class EntryPointsTest extends AppTestCase
{
    /** @var array<string, mixed> */
    private array $superglobals = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->superglobals = [$_GET, $_POST, $_FILES, $_SERVER, $_SESSION ?? []];
    }

    protected function tearDown(): void
    {
        [$_GET, $_POST, $_FILES, $_SERVER, $_SESSION] = $this->superglobals;
        parent::tearDown();
    }

    /**
     * @param array<string, string> $get
     * @param array<string, string> $post
     */
    private function run_(string $script, array $get = [], array $post = []): string
    {
        $_GET = $get;
        $_POST = $post;
        $_FILES = [];
        $_SESSION = $this->session;
        $_SERVER['REQUEST_METHOD'] = $post === [] ? 'GET' : 'POST';
        $_SERVER['REQUEST_URI'] = '/' . $script;

        ob_start();
        include Config::root() . '/frontend/' . $script;
        $output = (string) ob_get_clean();
        $this->session = $_SESSION;

        return $output;
    }

    /** @return array<string, array{string}> */
    public static function pages(): array
    {
        return [
            'gallery' => ['index.php'],
            'model' => ['model.php'],
            'download' => ['download.php'],
            'profile' => ['profile.php'],
            'liked' => ['like.php'],
            'orders' => ['orders.php'],
            'earnings' => ['creator_earnings.php'],
            'admin' => ['admin.php'],
            'admin orders' => ['admin_orders.php'],
            'admin payouts' => ['admin_payout.php'],
            'logout' => ['logout.php'],
            'login' => ['login.php'],
            'register' => ['register.php'],
        ];
    }

    #[DataProvider('pages')]
    public function testPageScriptsBootAndRespond(string $script): void
    {
        $owner = $this->addUser('Owner', true);
        $this->addModel($owner, ['title' => 'Entry model']);
        $this->loginAs($owner, 'Owner', true);

        $output = $this->run_($script, ['id' => '1']);

        // Logout and unknown-id pages produce redirects/errors (empty or short bodies); everything else is HTML.
        if (in_array($script, ['index.php', 'model.php', 'profile.php', 'like.php', 'orders.php', 'creator_earnings.php', 'admin.php', 'admin_orders.php', 'admin_payout.php'], true)) {
            self::assertStringContainsString('<!doctype html>', $output, $script);
        }
        self::assertStringNotContainsString('Server error', $output, $script);
    }

    /** @return array<string, array{string}> */
    public static function apis(): array
    {
        return [
            'models' => ['api/models.php'],
            'social' => ['api/social.php'],
            'orders' => ['api/orders.php'],
            'admin' => ['api/admin.php'],
        ];
    }

    #[DataProvider('apis')]
    public function testApiScriptsRespondWithJsonErrorsForUnknownActions(string $script): void
    {
        $output = $this->run_($script, ['action' => 'nope']);

        self::assertStringContainsString('"error":"bad_action"', $output);
    }

    public function testGalleryShowsSeededData(): void
    {
        $owner = $this->addUser('Owner');
        $this->addModel($owner, ['title' => 'Visible through the entry script']);

        self::assertStringContainsString('Visible through the entry script', $this->run_('index.php'));
    }
}
