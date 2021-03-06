<?php
/*
 * This file is part of sebastian/diff.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpCsFixer\Diff\Output;

use PHPUnit\Framework\TestCase;
use PhpCsFixer\Diff\Utils\UnifiedDiffAssertTrait;
use Symfony\Component\Process\Process;

/**
 * @covers PhpCsFixer\Diff\Output\UnifiedDiffOutputBuilder
 *
 * @uses PhpCsFixer\Diff\Differ
 * @uses PhpCsFixer\Diff\TimeEfficientLongestCommonSubsequenceCalculator
 *
 * @requires OS Linux
 */
final class UnifiedDiffOutputBuilderIntegrationTest extends TestCase
{
    use UnifiedDiffAssertTrait;

    private $dir;

    private $fileFrom;

    private $filePatch;

    protected function setUp()
    {
        $this->dir       = \realpath(__DIR__ . '/../../fixtures/out/') . '/';
        $this->fileFrom  = $this->dir . 'from.txt';
        $this->filePatch = $this->dir . 'patch.txt';

        $this->cleanUpTempFiles();
    }

    protected function tearDown()
    {
        $this->cleanUpTempFiles();
    }

    /**
     * @dataProvider provideDiffWithLineNumbers
     *
     * @param mixed $expected
     * @param mixed $from
     * @param mixed $to
     */
    public function testDiffWithLineNumbersPath($expected, $from, $to)
    {
        $this->doIntegrationTestPatch($expected, $from, $to);
    }

    /**
     * @dataProvider provideDiffWithLineNumbers
     *
     * @param mixed $expected
     * @param mixed $from
     * @param mixed $to
     */
    public function testDiffWithLineNumbersGitApply($expected, $from, $to)
    {
        $this->doIntegrationTestGitApply($expected, $from, $to);
    }

    public function provideDiffWithLineNumbers()
    {
        return \array_filter(
            UnifiedDiffOutputBuilderDataProvider::provideDiffWithLineNumbers(),
            static function ($key) {
                return !\is_string($key) || false === \strpos($key, 'non_patch_compat');
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    private function doIntegrationTestPatch($diff, $from, $to)
    {
        $this->assertNotSame('', $diff);
        $this->assertValidUnifiedDiffFormat($diff);

        $diff = self::setDiffFileHeader($diff, $this->fileFrom);

        $this->assertNotFalse(\file_put_contents($this->fileFrom, $from));
        $this->assertNotFalse(\file_put_contents($this->filePatch, $diff));

        $command = \sprintf(
            'patch -u --verbose --posix  %s < %s', // --posix
            \escapeshellarg($this->fileFrom),
            \escapeshellarg($this->filePatch)
        );

        $p = new Process($command);
        $p->run();

        $this->assertProcessSuccessful($p);

        $this->assertStringEqualsFile(
            $this->fileFrom,
            $to,
            \sprintf('Patch command "%s".', $command)
        );
    }

    private function doIntegrationTestGitApply($diff, $from, $to)
    {
        $this->assertNotSame('', $diff);
        $this->assertValidUnifiedDiffFormat($diff);

        $diff = self::setDiffFileHeader($diff, $this->fileFrom);

        $this->assertNotFalse(\file_put_contents($this->fileFrom, $from));
        $this->assertNotFalse(\file_put_contents($this->filePatch, $diff));

        $command = \sprintf(
            'git --git-dir %s apply --check -v --unsafe-paths --ignore-whitespace %s',
            \escapeshellarg($this->dir),
            \escapeshellarg($this->filePatch)
        );

        $p = new Process($command);
        $p->run();

        $this->assertProcessSuccessful($p);
    }

    private function assertProcessSuccessful(Process $p)
    {
        $this->assertTrue(
            $p->isSuccessful(),
            \sprintf(
                "Command exec. was not successful:\n\"%s\"\nOutput:\n\"%s\"\nStdErr:\n\"%s\"\nExit code %d.\n",
                $p->getCommandLine(),
                $p->getOutput(),
                $p->getErrorOutput(),
                $p->getExitCode()
            )
        );
    }

    private function cleanUpTempFiles()
    {
        @\unlink($this->fileFrom . '.orig');
        @\unlink($this->fileFrom);
        @\unlink($this->filePatch);
    }

    private static function setDiffFileHeader($diff, $file)
    {
        $diffLines    = \preg_split('/(.*\R)/', $diff, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $diffLines[0] = \preg_replace('#^\-\-\- .*#', '--- /' . $file, $diffLines[0], 1);
        $diffLines[1] = \preg_replace('#^\+\+\+ .*#', '+++ /' . $file, $diffLines[1], 1);

        return \implode('', $diffLines);
    }
}
