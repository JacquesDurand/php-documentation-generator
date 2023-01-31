<?php

/*
 * This file is part of the API Platform project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ApiPlatform\PDGBundle\Tests\Command;

use ApiPlatform\PDGBundle\Kernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\ApplicationTester;

final class ReferenceCommandTest extends KernelTestCase
{
    private ApplicationTester $tester;

    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function setUp(): void
    {
        putenv(sprintf('PDG_CONFIG_FILE=%s/reference.config.yaml', __DIR__));

        $kernel = self::bootKernel();
        /** @var Application $application */
        $application = $kernel->getContainer()->get(Application::class);
        $application->setAutoExit(false);
        $this->tester = new ApplicationTester($application);
    }

    public function testItThrowsAnErrorIfFileDoesNotExist(): void
    {
        $this->tester->run([
            'command' => 'reference',
            'filename' => 'tests/Command/src/Foo/Invalid.php',
        ]);

        $this->assertEquals(Command::FAILURE, $this->tester->getStatusCode());
        $this->assertStringContainsString(<<<EOT
File "tests/Command/src/Foo/Invalid.php" does not exist.
EOT
            , $this->tester->getDisplay());
    }

    /**
     * @dataProvider getReferences
     */
    public function testItCreatesAReference(string $name): void
    {
        $output = sprintf('tests/Command/pages/references/%s.mdx', $name);
        $filename = sprintf('tests/Command/src/%s.php', $name);
        $this->tester->run([
            'command' => 'reference',
            'filename' => $filename,
            'output' => $output,
        ]);

        $this->tester->assertCommandIsSuccessful(sprintf('Command failed: %s', $this->tester->getDisplay(true)));
        $this->assertStringContainsString(sprintf('[INFO] Creating reference "%s".', $filename), $this->tester->getDisplay());
        $this->assertFileExists($output);
        $this->assertFileEquals(
            sprintf('%s/expected/%s/%s.mdx', __DIR__, (new \ReflectionClass($this))->getShortName(), $name),
            $output
        );
    }

    public function getReferences(): iterable
    {
        yield ['Controller/FooController'];
    }
}
