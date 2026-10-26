<?php

declare(strict_types=1);

namespace Fagathe\CorePhp\Tests\Command;

use Fagathe\CorePhp\Command\AdminUserManagerInterface;
use Fagathe\CorePhp\Command\CreateAdminUserCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

final class CreateAdminUserCommandTest extends TestCase
{
    public static function tearDownAfterClass(): void
    {
        (new Filesystem())->remove(LOGS_DIR.'command');
        @rmdir(LOGS_DIR); // seulement s'il est vide
    }

    public function testAdminIsCreatedWithTheRequestedRole(): void
    {
        $manager = $this->createManager();
        $tester = new CommandTester(new CreateAdminUserCommand($manager));

        $status = $tester->execute(['username' => 'jane', 'email' => 'jane@example.com', 'password' => 'Password123', '--type' => 'admin']);

        self::assertSame(Command::SUCCESS, $status, $tester->getDisplay());
        self::assertSame([['jane', 'jane@example.com', 'Password123', 'ROLE_ADMIN']], $manager->created);
    }

    public function testDefaultTypeIsTheFirstRoleInNonInteractiveMode(): void
    {
        $manager = $this->createManager();
        $tester = new CommandTester(new CreateAdminUserCommand($manager));

        $tester->execute(['username' => 'john', 'email' => 'john@example.com', 'password' => 'Password123'], ['interactive' => false]);

        self::assertSame('ROLE_SUPER_ADMIN', $manager->created[0][3]);
    }

    public function testInteractiveModeAsksForMissingValues(): void
    {
        $manager = $this->createManager();
        $tester = new CommandTester(new CreateAdminUserCommand($manager));
        $tester->setInputs(['admin', 'max', 'max@example.com', 'Password123']);

        $status = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $status, $tester->getDisplay());
        self::assertSame([['max', 'max@example.com', 'Password123', 'ROLE_ADMIN']], $manager->created);
    }

    public function testDuplicateUsernameIsRejected(): void
    {
        $manager = $this->createManager(existingUsernames: ['jane']);
        $tester = new CommandTester(new CreateAdminUserCommand($manager));

        $status = $tester->execute(['username' => 'jane', 'email' => 'jane@example.com', 'password' => 'Password123', '--type' => 'admin']);

        self::assertSame(Command::FAILURE, $status);
        self::assertSame([], $manager->created);
        self::assertStringContainsString('existe déjà', $tester->getDisplay());
    }

    public function testInvalidDataIsRejected(): void
    {
        $manager = $this->createManager();
        $tester = new CommandTester(new CreateAdminUserCommand($manager));

        $status = $tester->execute(['username' => 'jane', 'email' => 'not-an-email', 'password' => 'short', '--type' => 'admin']);

        self::assertSame(Command::FAILURE, $status);
        self::assertSame([], $manager->created);
    }

    public function testCreationErrorIsReported(): void
    {
        $manager = $this->createManager(failure: new \RuntimeException('Base indisponible'));
        $tester = new CommandTester(new CreateAdminUserCommand($manager));

        $status = $tester->execute(['username' => 'jane', 'email' => 'jane@example.com', 'password' => 'Password123', '--type' => 'admin']);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('Base indisponible', $tester->getDisplay());
    }

    public function testMissingImplementationFails(): void
    {
        $tester = new CommandTester(new CreateAdminUserCommand());

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('AdminUserManagerInterface', $tester->getDisplay());
    }

    /**
     * @param list<string> $existingUsernames
     */
    private function createManager(array $existingUsernames = [], ?\Throwable $failure = null): AdminUserManagerInterface
    {
        return new class($existingUsernames, $failure) implements AdminUserManagerInterface {
            /** @var list<array{string, string, string, string}> */
            public array $created = [];

            /** @param list<string> $existingUsernames */
            public function __construct(private readonly array $existingUsernames, private readonly ?\Throwable $failure)
            {
            }

            public function getAdminRoles(): array
            {
                return ['super_admin' => 'ROLE_SUPER_ADMIN', 'admin' => 'ROLE_ADMIN'];
            }

            public function usernameExists(string $username): bool
            {
                return \in_array($username, $this->existingUsernames, true);
            }

            public function emailExists(string $email): bool
            {
                return false;
            }

            public function createAdmin(string $username, string $email, string $plainPassword, string $role): void
            {
                if ($this->failure !== null) {
                    throw $this->failure;
                }
                $this->created[] = [$username, $email, $plainPassword, $role];
            }
        };
    }
}
