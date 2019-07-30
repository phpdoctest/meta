<?php

declare(strict_types=1);

/**
 * Copyright Andrea Heigl <andreas@heigl.org>
 *
 * Licenses under the MIT-license. For details see the included file LICENSE.md
 */

namespace PHPDocMeta\Command;

use function bin2hex;
use function bindec;
use function chdir;
use function fclose;
use function feof;
use function file;
use function file_exists;
use function file_put_contents;
use function filesize;
use function fopen;
use function fread;
use function fwrite;
use function getcwd;
use function hex2bin;
use function implode;
use function parse_ini_file;
use function preg_match;
use function rename;
use RuntimeException;
use SplFileInfo;
use function sprintf;
use function str_replace;
use function strpos;
use function strtolower;
use function substr;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ReplaceHashForRevisionInSvnRefTable extends Command
{
    private const METADATAFILE = '/.git/svn/.metadata';

    private const BRANCHREFFILE = '/.git/svn/refs/remotes/origin/%1$s/.rev_map.%2$s';

    protected function configure()
    {
        $this->setName('replaceHashForRevisionInSvnRefTable')
            ->setDescription('Replaces the hash for the given SVN-revision with the new hash')
            ->setDefinition([
                new InputOption('oldHash', 'o', InputOption::VALUE_REQUIRED, 'What is the old hash?'),
                new InputOption('newHash', 'r', InputOption::VALUE_REQUIRED, 'What is the new hash?'),
                new InputArgument('svnbranch', InputArgument::OPTIONAL, 'For which SVN-Branch shall we do the replacement?', 'trunk'),
            ])
            ->setHelp('');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        while (! file_exists(getcwd() . '/.git')) {
            chdir(getcwd() . '/..');
            if (getcwd() === '/') {
                throw new RuntimeException('No GIT-directory found');
            }
        }
        if (!file_exists(getcwd() . self::METADATAFILE)) {
            throw new RuntimeException('No valid GIT-SVN directory.');
        }

        $info = parse_ini_file(getcwd() . self::METADATAFILE);

        if (!isset($info['uuid'])) {
            throw new RuntimeException('Something is wrong with your git-svn setup!');
        }

        $branch = $input->getArgument('svnbranch');

        $branchreffile = getcwd() . sprintf(
                self::BRANCHREFFILE,
                $branch,
                $info['uuid']
            );
        if (!file_exists($branchreffile)) {
            throw new RuntimeException('No Branch-REference file found');
        }

        $replacedRevision = null;


        $oldHash = $input->getOption('oldHash');
        if (!preg_match('/[0-9a-fA-F]{40}/', $oldHash)) {
            throw new RuntimeException('Old Hash is not a SHA1-hash');
        }
        $newHash = $input->getOption('newHash');
        if (!preg_match('/[0-9a-fA-F]{40}/', $newHash)) {
            throw new RuntimeException('New Hash is not a SHA1-hash');
        }

        $result = $this->replaceHashInRevMap($oldHash, $newHash, $branchreffile);
        if ($result) {
            $output->writeln($result);
        }
        $this->replaceHashInRefsInfo($oldHash, $newHash);
        $this->replaceHashInPackedRefs($oldHash, $newHash);
        $this->replaceHashInFile(
            $oldHash,
            $newHash,
            new SplFileInfo(getcwd() . '/.git/logs/HEAD')
        );
        $this->replaceHashInFile(
            $oldHash,
            $newHash,
            new SplFileInfo(getcwd() . '/.git/logs/refs/heads/master')
        );
        $this->replaceHashInFile(
            $oldHash,
            $newHash,
            new SplFileInfo(getcwd() . '/.git/refs/original/refs/heads/master')
        );
        $this->replaceHashInFile(
            $oldHash,
            $newHash,
            new SplFileInfo(getcwd() . '/.git/refs/remotes/origin/' . $branch)
        );
        $this->replaceHashInFile(
            $oldHash,
            $newHash,
            new SplFileInfo(getcwd() . '/.git/logs/refs/remotes/origin/' . $branch)
        );
    }

    private function replaceHashInRevMap($oldHash, $newHash, $branchreffile) : string
    {
        $oldHashBin = hex2bin($oldHash);
        $newHashBin = hex2bin($newHash);
        $replacedRevision = null;

        if (file_exists($branchreffile . '.tmp')) {
            throw new RuntimeException('lockfile already exists. Something went wrong the last time!');
        }

        rename($branchreffile, $branchreffile . '.tmp');
        $base = fopen($branchreffile . '.tmp', 'r');
        $new  = fopen($branchreffile, 'w');

        while (! feof($base)) {
            $content = fread($base, 24);
            if (strpos($content, $oldHashBin) === 4) {
                $replacedRevision = substr($content, 0, 4);
                $content = $replacedRevision . $newHashBin;
            }
            fwrite($new, $content);
        }

        fclose($base);
        fclose($new);

        if (filesize($branchreffile) === filesize($branchreffile . '.tmp')) {
            unlink($branchreffile . '.tmp');
        }

        if ($replacedRevision !== null) {
            return sprintf('Replaced Hash for revision %1$s', hexdec(bin2hex($replacedRevision)));
        }

        return '';
    }

    public function replaceHashInRefsInfo($oldHash, $newHash) : void
    {
        $file = getcwd() . '/.git/info/refs';

        $fileContent = file($file);
        foreach ($fileContent as &$line) {
            if (strpos($line, $oldHash) !== 0) {
                continue;
            }
            $line = str_replace($oldHash, strtolower($newHash), $line);
        }

        file_put_contents($file, implode("", $fileContent));
    }

    public function replaceHashInPackedRefs($oldHash, $newHash) : void
    {
        $file = getcwd() . '/.git/packed-refs';

        $fileContent = file($file);
        foreach ($fileContent as &$line) {
            if (strpos($line, $oldHash) !== 0) {
                continue;
            }
            $line = str_replace($oldHash, strtolower($newHash), $line);
        }

        file_put_contents($file, implode("", $fileContent));
    }

    private function replaceHashInFile(string $oldHash, string $newHash, SplFileInfo $file) : void
    {
        $fileContent = file($file->getPathname());
        foreach ($fileContent as &$line) {
            $line = str_replace($oldHash, $newHash, $line);
        }

        file_put_contents($file->getPathname(), implode("", $fileContent));
    }
}
