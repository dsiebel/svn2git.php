<?php
/**
 * Abstract command class.
 * Currently holding the shared git(-svn) methods.
 *
 * TODO extract git(-svn) functionality to separate layer
 *
 * @since 2014-11-12
 * @author Dominik Siebel (dsiebel) <code@dsiebel.de>
 * @copyright (c) 2014 Domink Siebel
 * @license MIT
 */

namespace Svn2Git\Command;

use Svn2Git\Cli\Cli;
use Svn2Git\Vcs\Branch;
use Svn2Git\Vcs\Tag;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


abstract class Command extends SymfonyCommand {
    /**
     * @var InputInterface
     */
    protected $input;
    /**
     * @var OutputInterface
     */
    protected $output;
    /**
     * @var Cli
     */
    protected $cli;
    /**
     * Path to current working directory
     * @var string
     */
    protected  $cwd;

    /**
     * @inheritdoc
     */
    protected function configure() {
        // common configuration goes here
    }

    /**
     * @inheritdoc
     */
    protected function initialize(InputInterface $input, OutputInterface $output) {
        $this->input = $input;
        $this->output = $output;

        $this->cwd = getcwd();

        $this->cli = new Cli();
        $this->cli->setWorkingDir($this->cwd);
        $this->cli->setTrustExitCodes(true);
    }

    /**
     * Helper method for writing <comment> logs.
     * @param string $message
     */
    protected function comment($message) {
        $this->output->writeln(sprintf('<comment>%s</comment>', $message));
    }

    /**
     * Helper method for writing <info> logs.
     * @param string $message
     */
    protected function log($message) {
        $this->output->writeln(sprintf('<info>%s</info>', $message));
    }

    /**
     * Helper method for writing <error> logs.
     * @param string $message
     */
    protected function error($message) {
        $this->output->writeln(sprintf('<error>%s</error>', $message));
    }

    /**
     * Checks if a path is a git repository.
     * Checks if path is a directory containing a '.git' directory.
     * @param string $path Path to check for git repository
     * @return bool
     */
    protected function isGitRepository($path) {
        return is_dir($path) && is_dir($path . DIRECTORY_SEPARATOR . '.git');
    }

    /**
     * Checks whether the given path has a remote repository configured.
     * With the second parameter it will check for a specific remote.
     *
     * @param $path
     * @param null $name
     * @return bool
     */
    protected function hasRemote($path, $name = null) {
        $output = $this->cli->execute('git remote', $path);

        $precheck = is_array($output) && count($output) > 0;

        if (null !== $name) {
            return $precheck && in_array($name, $output);
        }

        return $precheck;
    }

    /**
     * Updates every given branch to the latest revision of remote subversion repository.
     *
     * @param Branch[] $branches Branches to be updated
     * @param string $path Path to local repository
     */
    protected function updateBranches(array $branches, $path) {

        foreach ($branches as $branch) {
            /** @var $branch Branch */
            $this->switchToBranch($branch->getName(), $path);
            $this->updateFromSubversion($path);
        }
    }

    /**
     * Creates git branches from the list of given branch configurations.
     * @param Branch[] $branches
     * @param string $path Path to lcal repository
     */
    protected function createBranches(array $branches, $path) {
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
     * Fetches from subversion.
     * @param string $path Path to local repository
     */
    protected function fetchFromSubversion($path) {
        $cmd = 'git svn fetch';
        $this->comment($cmd);
        $this->cli->passthru($cmd, $path);
    }

    /**
     * Updates to the latest revision of remote subversion repository.
     * @param string $path Path to local repository
     */
    protected function rebaseFromSubversion($path) {
        $cmd = 'git svn rebase';
        $this->comment($cmd);
        $this->cli->passthru($cmd, $path);
    }

    /**
     * Add a remote to the given repository.
     *
     * @param string $url Remote URL
     * @param string $path Path to local repository
     * @param string $name Name of the remote
     */
    protected function addRemote($url, $path, $name = 'origin') {
        $cmd = 'git remote add ' . $name . ' ' . $url;
        $this->log('Creating git remote');
        $this->comment($cmd);
        try {
            $this->cli->execute($cmd, $path);
        } catch(\Exception $e) {
            $this->error($e->getMessage());
        }
    }

    /**
     * Creates annotated git tags from the given list of tag configurations.
     * @param Tag[] $tags List of tags to be created
     * @param string $path Path to local repository
     */
    protected function createAnnotatedTags(array $tags, $path) {
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
     * Switches to the specified git branch.
     *
     * @param string|Branch $branch
     * @param $path
     */
    protected function switchToBranch($branch, $path) {
        $branchName = ($branch instanceof Branch)
            ? $branch->getName()
            : (string)$branch;

        $cmd = 'git checkout ' . $branchName;
        $this->comment($cmd);
        try {
            $this->cli->passthru($cmd, $path);
        } catch(\Exception $e) {
            $this->error($e->getMessage());
        }
    }

    /**
     * Updates to the latest revision of remote subversion repository.
     * @param string $path Path to local repository
     */
    protected function updateFromSubversion($path) {
        $cmd = 'git svn rebase';
        $this->comment($cmd);
        try {
            $this->cli->passthru($cmd, $path);
        } catch(\Exception $e) {
            $this->error($e->getMessage());
        }
    }

    /**
     * Pushes to remote git repository.
     *
     * @param string $path Path to local repository
     * @param string $remote Name of the remote.
     */
    protected function push($path, $remote = 'origin') {
        $this->log('Pushing to remote git repository');
        $cmd = 'git push ' . $remote . ' --all';
        $this->comment($cmd);
        $this->cli->passthru($cmd, $path);
        $cmd = 'git push ' . $remote . ' --tags';
        $this->comment($cmd);
        $this->cli->passthru($cmd, $path);
    }

    /**
     * Retrieves a list of branch configurations from a local git-svn bridge.
     * @param string $path Path to git-svn bridge
     * @return Branch[]
     */
    protected function getSubversionBranches($path) {
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
    protected function getSubversionTags($path) {
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
}