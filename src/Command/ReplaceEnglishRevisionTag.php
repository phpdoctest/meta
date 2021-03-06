<?php

/**
 * Copyright (c) Andreas Heigl<andreas@heigl.org>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @author    Andreas Heigl<andreas@heigl.org>
 * @copyright Andreas Heigl
 * @license   http://www.opensource.org/licenses/mit-license.php MIT-License
 * @since     14.06.2016
 * @link      http://github.com/heiglandreas/org.heigl.junitdiff
 */

namespace PHPDocMeta\Command;

use function explode;
use function file;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function fopen;
use function fwrite;
use function getcwd;
use function natcasesort;
use function str_replace;
use function strnatcasecmp;
use function touch;

class ReplaceEnglishRevisionTag extends Command
{
    const LOCAL_HASH_FILE = '.en-revisions.ref';

    private $hashTable = [];

    protected function configure()
    {
        $this->setName('replaceEnglishRevisionTag')
             ->setDescription('Replaces the english revision-tag with the hash of the respective revision')
             ->setDefinition([
                 new InputArgument('language', InputArgument::OPTIONAL, 'Which language-Directory shall we replace the revision tags in?'),
                 new InputOption('hashtable', 't', InputOption::VALUE_REQUIRED, 'Where is the replacement-file?')
             ])
             ->setHelp('');
    }


    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln([
            $this->getApplication()->getLongVersion(),
            '',
        ]);

        $folder = $input->getArgument('language');
        if (! $folder) {
            $folder = getcwd();
        }

        $hashtable = $this->parseHashTable($input->getOption('hashtable'));

        $this->readLocalHashTable();

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($folder));

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'xml') {
                continue;
            }

            $this->replace($file, $output, $hashtable);
        }

        $this->writeLocalHashTable();


    }

    protected function replace(\SplFileInfo $file, OutputInterface $output, $hashtable)
    {
        $content = file_get_contents($file->getPathname());
        if (! preg_match('/<!--\s*EN-Revision:\s*(\d+)\s+/', $content, $results)) {
            unset ($content);
            return;
        }

        if (! isset($hashtable[$results[1]])) {
//            $output->writeln(sprintf(
//                '<fg=red>Revision %1$s for file %2$s not found in HashTable',
//                $results[1],
//                $file->getPathname()
//            ));
            return;
        }

        $hash = $hashtable[$results[1]];
        $this->setHashTableValue($file->getPathname(), $hash);

//        file_put_contents($file->getPathname(), str_replace(
//            'EN-Revision: ' . $results[1],
//            'EN-Revision: ' . $hashtable[$results[1]],
//            $content
//        ));
    }

    private function parseHashTable($hashtableUri)
    {
        $hashtable = file($hashtableUri);

        $return = [];
        foreach ($hashtable as $item) {
            $exploded = explode('|', $item);
            $return[str_replace('r', '', trim($exploded[0]))] = trim($exploded[1]);
        }

        return $return;
    }

    private function readLocalHashTable()
    {
        if (! file_exists(getcwd() . '/' . self::LOCAL_HASH_FILE)) {
            touch(getcwd() . '/' . self::LOCAL_HASH_FILE);
        }
        foreach (file(getcwd() . '/' . self::LOCAL_HASH_FILE) as $line) {
            $items = explode(':', trim($line));
            $this->hashTable[trim($items[0])] = trim($items[1]);
        }
    }

    private function writeLocalHashTable()
    {
        uksort($this->hashTable, 'strnatcasecmp');

        $fh = fopen(getcwd() . '/' . self::LOCAL_HASH_FILE, 'w+');
        foreach ($this->hashTable as $file => $hash) {
            fwrite($fh, $file . ': ' . $hash . "\n");
        }

        fclose($fh);
    }

    private function setHashTableValue($filepath, $hash)
    {
        $relativeFilePath = ltrim(str_replace(getcwd(), '', $filepath), '/');

        $this->hashTable[$relativeFilePath] = $hash;
    }
}
