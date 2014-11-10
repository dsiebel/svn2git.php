<?php
/**
 * Command line tool to fetch author names from an SVN repository.
 *
 * @since 2014-11-08
 * @author Dominik Siebel (dsiebel) <code@dsiebel.de>
 * @copyright (c) 2014 Dominik Siebel
 * @license MIT
 */

namespace Svn2Git\Command;

use Svn2Git\Cli\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class FetchSvnAuthorsCommand
 *
 * @package Svn2Git\Command
 */
class FetchSvnAuthorsCommand extends Command {
    const ARG_SRC = 'source';

    const OPT_OUTPUT = 'output';

    /**
     * @var InputInterface
     */
    private $input;
    /**
     * @var OutputInterface
     */
    private $output;
    /**
     * @var Cli
     */
    private $cli;
    /**
     * @var string
     */
    private $file;
    /**
     * @var boolean
     */
    private $quiet;
    /**
     * @var string
     */
    private $source;

    /**
     * Configures the current command.
     */
    protected function configure() {
        $this
            ->setName('fetch-svn-authors')
            ->setDescription('Command line tool to fetch author names from an SVN repository.');

        $this->addArgument(
            self::ARG_SRC,
            InputArgument::REQUIRED,
            'Subversion repository to fetch author names from.'
        );

        $this->addOption(
            self::OPT_OUTPUT,
            null,
            InputOption::VALUE_REQUIRED,
            'Output file.',
            './authors.txt'
        );
    }

    protected function initialize(InputInterface $input, OutputInterface $output) {
        $this->input = $input;
        $this->output = $output;
        $this->cli = new Cli();
        $this->source = $this->input->getArgument(self::ARG_SRC);
        $this->file = $this->input->getOption(self::OPT_OUTPUT);
        $this->quiet = OutputInterface::VERBOSITY_QUIET === $this->output->getVerbosity();
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $authors = $this->getSubversionAuthors($this->source, $this->quiet);
        $this->writeAuthorsFile($authors, $this->file);
    }

    private function writeAuthorsFile($authors, $file) {
        file_put_contents($file, implode("\n", $authors));
    }

    private function getSubversionAuthors($url, $quiet) {
        $cmd = 'svn log %s %s | awk -F \'|\' \'/^r/ {sub("^ ", "", $2); '.
            'sub(" $", "", $2); print $2" = "$2" <"$2">"}\' | sort -u';

        return $this->cli->execute(sprintf(
            $cmd,
            $quiet ? ' --quiet' : '',
            $url
        ));
    }
}