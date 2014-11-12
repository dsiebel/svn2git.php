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
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

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

    const OPT_PRESERVE_EMPTY = 'preserve-empty-dirs';
    const OPT_PLACEHOLDER_FILE = 'placeholder-filename';

    const PLACEHOLDER_FILE_DEFAULT = '.gitkeep';

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
     * @var QuestionHelper
     */
    private $question;
    /**
     * Filename for placeholder file used to preserve empty subversion directories.
     * @var string
     */
    private $placeholderFileName;
    /**
     * Whether to preserve empty directories retrieved from subversion.
     * @var boolean
     */
    private $preserveEmpty;

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

        $this->addOption(
            self::OPT_PRESERVE_EMPTY,
            null,
            InputOption::VALUE_NONE,
            'Create a placeholder file in the local Git repository for each empty directory fetched from Subversion.'
        );

        $this->addOption(
            self::OPT_PLACEHOLDER_FILE,
            null,
            InputOption::VALUE_REQUIRED,
            'Set the name of placeholder files created by --preserve-empty-dirs.',
            self::PLACEHOLDER_FILE_DEFAULT
        );
    }

    /**
     * @inheritdoc
     */
    protected function initialize(InputInterface $input, OutputInterface $output) {
        $this->input = $input;
        $this->output = $output;

        /** @var $question QuestionHelper */
        $this->question = $this->getHelper('question');

        $this->cwd = getcwd();

        $this->cli = new Cli($this->cwd);
        $this->cli->setWorkingDir($this->cwd);
        $this->cli->setTrustExitCodes(true);

        $this->source = $input->getArgument(self::ARG_SRC);

        $this->name = basename($this->source);

        $this->gitsvn = $this->cwd . DIRECTORY_SEPARATOR . 'tmp/' . $this->name;

        if ($input->hasOption(self::OPT_AUTHORS_FILE)) {
            $this->authorsFile = $input->getOption(self::OPT_AUTHORS_FILE);
        }

        if ($input->hasOption(self::OPT_REMOTE)) {
            $this->remote = $input->getOption(self::OPT_REMOTE);

        }

        if ($input->hasOption(self::OPT_PRESERVE_EMPTY)) {
            $this->preserveEmpty = $input->getOption(self::OPT_PRESERVE_EMPTY);
            $this->placeholderFileName = $input->getOption(self::OPT_PLACEHOLDER_FILE);
        }
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output) {

        $this->log('BASEDIR: ' . $this->cwd);
        $this->log('SOURCE: ' . $this->source);
        $this->log('NAME: ' . $this->name);

        if (!file_exists($this->gitsvn)) {
            mkdir($this->gitsvn, 0755, true);
        }
        $this->log('TMP: ' . $this->gitsvn);

        if (isset($this->authorsFile)) {
            $this->log('AUTHORS-FILE: ' . $this->authorsFile);
        }

        if (isset($this->remote)) {
            $this->log('REMOTE: ' . $this->remote);
        }

        if ($this->preserveEmpty) {
            $this->log('PRESERVE EMPTY DIRS WITH: ' . $this->placeholderFileName);
        }

        $this->log('==========================================================');

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

        $createBranchesQ = new ConfirmationQuestion('Migrate branches? ', true);

        if ($this->question->ask($input, $output, $createBranchesQ)) {
            $branches = $this->getSubversionBranches($this->gitsvn);
            $this->createGitBranches($branches, $this->gitsvn);
        }

        $createTagsQ = new ConfirmationQuestion('Migrate tags? ', true);

        if ($this->question->ask($input, $output, $createTagsQ)) {
            $tags = $this->getSubversionTags($this->gitsvn);
            $this->createGitAnnotatedTags($tags, $this->gitsvn);
        }

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

        $this->log('Cloning subversion repository...');

        $cmdSeg = [
            'git svn clone',
            $source,
            '--prefix=svn/',
            '--stdlayout',
            '--quiet'
        ];

        if (isset($authorsFile)) {
            $cmdSeg[] = '-A ' . $authorsFile;
        }

        if ($this->preserveEmpty) {
            $cmdSeg[] = '--preserve-empty-dirs';
            $cmdSeg[] = '--placeholder-filename=' . $this->placeholderFileName;
        }

        // append destination to the end, always
        $cmdSeg[] = $destination;

        $cmd = implode(' ', $cmdSeg);
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
                $this->error($e->getMessage());
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