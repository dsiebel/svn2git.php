<?php
/**
 * Command line tool to migrate a Subversion repository to Git.
 *
 * @since 2014-11-08
 * @author Dominik Siebel (dsiebel) <code@dsiebel.de>
 * @copyright (c) 2014 Dominik Siebel
 * @license MIT
 */

namespace Svn2Git\Command;

use Svn2Git\Cli\Cli;
use Svn2Git\Vcs\Branch;
use Svn2Git\Vcs\Tag;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Svn2GitCommand
 *
 * @package Svn2Git\Command
 */
class MigrateCommand extends Command {

    const ARG_SRC = 'source';

    const OPT_REMOTE = 'remote';

    const OPT_AUTHORS_FILE = 'authors-file';
    const OPT_AUTHORS_FILE_S = 'A';

    /**
     * @var InputInterface
     */
    private $input;
    /**
     * @var OutputInterface
     */
    private $output;
    /**
     * Source subversion repository.
     * @var string
     */
    private $source;
    /**
     * Source
     * @var string
     */
    private $name;
    /**
     * Path to the temporary git-svn bridge.
     * @var string
     */
    private $gitsvn;
    /**
     * Current working directory.
     * @var string
     */
    private $cwd;
    /**
     * Path to authors mapping file.
     * @var string
     */
    private $authorsFile;
    /**
     * Remote Git repository URL.
     * @var string
     */
    private $remote;
    /**
     * @var Cli
     */
    private $cli;

    /**
     * @inheritdoc
     */
    protected function configure() {
        $this
            ->setName('migrate')
            ->setDescription('Command line tool to migrate a Subversion repository to Git.');

        $this->addArgument(
            self::ARG_SRC,
            InputArgument::REQUIRED,
            'Subversion repository to migrate.'
        );

        $this->addOption(
            self::OPT_AUTHORS_FILE,
            self::OPT_AUTHORS_FILE_S,
            InputOption::VALUE_REQUIRED,
            'Path to Subversion authors mapping.'
        );

        $this->addOption(
            self::OPT_REMOTE,
            null,
            InputOption::VALUE_REQUIRED,
            'URL of Git remote repository to push to.'
        );
    }

    /**
     * @inheritdoc
     */
    protected function initialize(InputInterface $input, OutputInterface $output) {
        $this->input = $input;
        $this->output = $output;

        $this->cwd = getcwd();
        $this->log('BASEDIR: ' . $this->cwd);

        $this->cli = new Cli($this->cwd);
        $this->cli->setWorkingDir($this->cwd);
        $this->cli->setTrustExitCodes(true);

        $this->source = $input->getArgument(self::ARG_SRC);
        $this->log('SOURCE: ' . $this->source);

        $this->name = basename($this->source);
        $this->log('NAME: ' . $this->name);

        $this->gitsvn = $this->cwd . DIRECTORY_SEPARATOR . 'tmp-' . $this->name . '-bridge';
        mkdir($this->gitsvn, 0755, true);
        $this->log('TMP: ' . $this->gitsvn);

        if ($input->hasOption(self::OPT_AUTHORS_FILE)) {
            $this->authorsFile = $input->getOption(self::OPT_AUTHORS_FILE);
            $this->log('AUTHORS-FILE: ' . $this->authorsFile);
        }

        if ($input->hasOption(self::OPT_REMOTE)) {
            $this->remote = $input->getOption(self::OPT_REMOTE);
            $this->log('REMOTE: ' . $this->remote);
        }

        $this->log('==========================================================');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output) {

        if (!$this->isGitRepository($this->gitsvn)) {
            $this->cloneSubversionRepository(
                $this->source,
                $this->gitsvn,
                $this->authorsFile
            );
        } else {
            $this->log('Existing git repository found: ' . $this->gitsvn);
            $this->fetchSubversionRepository($this->gitsvn);
            $this->updateSubversionRepository($this->gitsvn);
        }
        $branches = $this->getSubversionBranches($this->gitsvn);
        $this->createGitBranches($branches, $this->gitsvn);

        $tags = $this->getSubversionTags($this->gitsvn);
        $this->createGitAnnotatedTags($tags, $this->gitsvn);

        if (isset($this->remote)) {
            $this->addGitRemote($this->remote, $this->gitsvn);
            $this->pushToGit($this->gitsvn);
        }
        // cleanup
        $this->gitCheckout('master', $this->gitsvn);
    }

    /**
     * Checks if a path is a git repository.
     * Checks if path is a directory containing a '.git' directory.
     * @param string $path Path to check for git repository
     * @return bool
     */
    private function isGitRepository($path) {
        return is_dir($path) && is_dir($path . DIRECTORY_SEPARATOR . '.git');
    }

    /**
     * Clones a subversion repository from <code>$source</code> to <code>$destination</code>
     * @param string $source URL to subversion repository.
     * @param string $destination Path to store git-svn repository to
     * @param string|null $authorsFile Path to authors mapping file
     */
    private function cloneSubversionRepository($source, $destination, $authorsFile = null) {
        $cmd = 'git svn clone '
            . $source
            . (isset($authorsFile) ? ' -A '.$authorsFile:'')
            . ' --prefix=svn/'
            . ' --stdlayout --quiet ' . $destination;
        $this->log('Cloning subversion repository...');
        $this->comment($cmd);
        $this->cli->passthru($cmd);
    }

    /**
     * Creates git branches from the list of given branch configurations.
     * @param Branch[] $branches
     * @param string $path Path to lcal repository
     */
    private function createGitBranches(array $branches, $path) {
        foreach($branches as $branch) {
            /** @var $branch Branch */
            $name = $branch->getName();
            $cmd = sprintf('git checkout -b %s svn/%s', $name, $name);
            $this->log('Creating branch ' . $name);
            $this->comment($cmd);
            try {
                $this->cli->execute($cmd, $path);
            } catch(\Exception $e) {
                $this->error('Unable to create branch ' . $name . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * Creates annotated git tags from the given list of tag configurations.
     * @param Tag[] $tags List of tags to be created
     * @param string $path Path to local repository
     */
    private function createGitAnnotatedTags(array $tags, $path) {
        foreach($tags as $tag) {
            /** @var $tag Tag */
            $name = $tag->getName();
            $msg = $tag->getMessage();
            $cmd = sprintf("git tag -am '%s' %s remotes/svn/tags/%s", $msg, $name, $name);
            $this->log('Creating tag ' . $name);
            $this->comment($cmd);
            try {
                $this->cli->execute($cmd, $path);
            } catch(\Exception $e) {
                $this->error($e->getMessage());
            }
        }
    }

    /**
     * Fetches from subversion.
     * @param string $path Path to local repository
     */
    private function fetchSubversionRepository($path) {
        $cmd = 'git svn fetch';
        $this->comment($cmd);
        $this->cli->passthru($cmd, $path);
    }

    /**
     * Updates to the latest revision of remote subversion repository.
     * @param string $path Path to local repository
     */
    private function updateSubversionRepository($path) {
        $cmd = 'git svn rebase';
        $this->comment($cmd);
        $this->cli->passthru($cmd, $path);
    }

    private function addGitRemote($url, $path) {
        $cmd = 'git remote add origin ' . $url;
        $this->log('Creating git remote');
        $this->comment($cmd);
        try {
            $this->cli->execute($cmd, $path);
        } catch(\Exception $e) {
            $this->error($e->getMessage());
        }
    }

    /**
     * Pushes to remote git repository.
     * @param string $path Path to local repository
     */
    private function pushToGit($path) {
        $this->log('Pushing to remote git repository');
        $cmd = 'git push origin --all';
        $this->comment($cmd);
        $this->cli->passthru($cmd, $path);
        $cmd = 'git push origin --tags';
        $this->comment($cmd);
        $this->cli->passthru($cmd, $path);
    }

    /**
     * Retrieves a list of branch configurations from a local git-svn bridge.
     * @param string $path Path to git-svn bridge
     * @return Branch[]
     */
    private function getSubversionBranches($path) {
        $branches = [];
        // [1] => branch name, [2] => revision, [3] => commit message ( if exists )
        $pattern = '(^\s*svn/(\S+)\s+([a-z0-9]+)(.*))';
        $cmd = 'git branch -rv | grep -v \'tags\'';
        $lines = $this->cli->execute($cmd, $path);

        foreach ($lines as $line) {
            $matches = [];

            if (!preg_match($pattern, $line, $matches)) {
                continue;
            }

            $branches[] = new Branch($matches[1], isset($matches[3]) ? trim($matches[3]) : '', $matches[2]);
        }
        return $branches;
    }

    /**
     * Retrieves a list of tag configurations from a local git-svn bridge.
     * @param string $path Path to git-svn bridge
     * @return Tag[]
     */
    private function getSubversionTags($path) {
        $tags = [];
        // [1] => tag ref, [2] => revision, [3] => commit message ( if exists )
        $pattern = '(^\s*svn/tags/(\S+)\s+([a-z0-9]+)(.*))';
        $cmd = 'git branch -rv | grep \'tags\'';
        $lines = $this->cli->execute($cmd, $path);

        foreach($lines as $line) {
            $matches = [];

            if (!preg_match($pattern, $line, $matches)) {
                continue;
            }

            $tags[] = new Tag($matches[1], isset($matches[3]) ? trim($matches[3]) : '', $matches[2]);
        }
        return $tags;
    }

    /**
     * Performs a simple git checkout $branch.
     * @param $branch
     * @param $path
     */
    private function gitCheckout($branch, $path) {
        $cmd = 'git checkout ' . $branch;
        $this->comment($cmd);
        $this->cli->execute($cmd, $path);
    }

    /**
     * Helper method for writing <comment> logs.
     * @param string $message
     */
    private function comment($message) {
        $this->output->writeln(sprintf('<comment>%s</comment>', $message));
    }

    /**
     * Helper method for writing <info> logs.
     * @param string $message
     */
    private function log($message) {
        $this->output->writeln(sprintf('<info>%s</info>', $message));
    }

    /**
     * Helper method for writing <error> logs.
     * @param string $message
     */
    private function error($message) {
        $this->output->writeln(sprintf('<error>%s</error>', $message));
    }
}