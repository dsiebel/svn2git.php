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


use Svn2Git\Vcs\Branch;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateCommand extends Command {

    const ARG_GITSVN = 'gitsvn';

    const OPT_BRANCHES = 'branches';

    /**
     * Path to git-svn repository.
     * @var string
     */
    private $gitsvn;
    /**
     * List of branches to be updated.
     * @var Branch[]
     */
    private $branches;

    /**
     * @inheritdoc
     */
    protected function configure() {
        $this
            ->setName('update')
            ->setDescription('Command line tool to migrate a Subversion repository to Git.');

        $this->addArgument(
            self::ARG_GITSVN,
            InputArgument::REQUIRED,
            'Git-svn repository to be updated.'
        );

        $this->addOption(
            self::OPT_BRANCHES,
            null,
            InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
            'Branches to be updated.'
        );
    }

    /**
     * @inheritdoc
     */
    protected function initialize(InputInterface $input, OutputInterface $output) {
        parent::initialize($input, $output);

        $this->gitsvn = $input->getArgument(self::ARG_GITSVN);

        if (!$this->isGitRepository($this->gitsvn)) {
            throw new \InvalidArgumentException('Given gitsvn path is not a git repository (' . $this->gitsvn . ')');
        }

        $branchNames = $input->getOption(self::OPT_BRANCHES);

        if (empty($branchNames)) {
            $this->branches = $this->getSubversionBranches($this->gitsvn);
        } else {
            $this->branches = [];
            foreach($branchNames as $branchName) {
                $this->branches[] = new Branch($branchName);
            }
        }
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        $this->log('BASEDIR: ' . $this->cwd);
        $this->log('GIT_SVN: ' . $this->gitsvn);
        $this->log('==========================================================');

        $this->updateBranches($this->branches, $this->gitsvn);

        if ($this->hasRemote($this->gitsvn)) {
            $this->push($this->gitsvn);
        }

        $this->switchToBranch('master', $this->gitsvn);
    }
}