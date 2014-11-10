<?php
/**
 * Command line tool to update a git-svn bridge's remote Git repo with all changes from Subversion.
 *
 * @since 2014-11-10
 * @author Dominik Siebel (dsiebel) <code@dsiebel.de>
 * @copyright (c) 2014 Dominik Siebel
 * @license MIT
 */

namespace Svn2Git\Command;


use Svn2Git\Cli\Cli;
use Svn2Git\Vcs\Branch;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateCommand extends Command {

    const ARG_GITSVN = 'gitsvn';
    const ARG_BRANCHES = 'branches';

    /**
     * Path to git-svn repository.
     * @var string
     */
    private $gitsvn;
    /**
     * List of branches to update
     * @var Branch[]
     */
    private $branches;
    /**
     * @var InputInterface
     */
    private $input;
    /**
     * @var OutputInterface
     */
    private $output;
    private $cwd;
    private $cli;

    /**
     * @inheritdoc
     */
    protected function configure() {
        $this
            ->setName('migrate')
            ->setDescription('Command line tool to migrate a Subversion repository to Git.');

        $this->addArgument(
            self::ARG_GITSVN,
            InputArgument::REQUIRED,
            'Subversion repository to migrate.'
        );

        $this->addArgument(
            self::ARG_BRANCHES,
            InputArgument::IS_ARRAY & InputArgument::REQUIRED,
            'Subversion branches to update.'
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

        $this->gitsvn = $input->getArgument(self::ARG_GITSVN);
        if (!$this->isGitRepository($this->gitsvn)) {
            throw new \InvalidArgumentException('Given gitsvn path is not a git repository (' . $this->gitsvn . ')');
        }
        $this->log('GIT_SVN: ' . $this->gitsvn);

        $this->branches = $input->getArgument(self::ARG_BRANCHES);
        $this->log('BRANCHES: '. implode(', ', $this->branches));

        $this->log('==========================================================');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output) {

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

    /**
     * Checks if a path is a git repository.
     * Checks if path is a directory containing a '.git' directory.
     * @param string $path Path to check for git repository
     * @return bool
     */
    private function isGitRepository($path) {
        return is_dir($path) && is_dir($path . DIRECTORY_SEPARATOR . '.git');
    }
}