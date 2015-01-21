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

    const OPT_STDLAYOUT = 'stdlayout';
    const OPT_STDLAYOUT_S = 's';

    const OPT_TRUNK = 'trunk';
    const OPT_TRUNK_S = 'T';

    const OPT_BRANCHES = 'branches';
    const OPT_BRANCHES_S = 'b';

    const OPT_TAGS = 'tags';
    const OPT_TAGS_S = 't';

    const OPT_AUTHORS_FILE = 'authors-file';
    const OPT_AUTHORS_FILE_S = 'A';

    const OPT_PRESERVE_EMPTY = 'preserve-empty-dirs';
    const OPT_PLACEHOLDER_FILE = 'placeholder-filename';

    const PLACEHOLDER_FILE_DEFAULT = '.gitkeep';

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
     * @var QuestionHelper
     */
    private $question;
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
     * @var boolean
     */
    private $stdlayout;
    /**
     * Relative repository path or full url pointing to the trunk of the repository.
     * @var string
     */
    private $trunk;
    /**
     * Relative repository path or full url pointing to the branches of the repository.
     * @var string
     */
    private $branches;
    /**
     * Relative repository path or full url pointing to the tags of the repository.
     * @var string
     */
    private $tags;
    /**
     * Whether to preserve empty directories retrieved from subversion.
     * @var boolean
     */
    private $preserveEmpty;
    /**
     * Filename for placeholder file used to preserve empty subversion directories.
     * @var string
     */
    private $placeholderFileName;

    /**
     * @inheritdoc
     */
    protected function configure() {
        parent::configure();

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
            self::OPT_STDLAYOUT,
            self::OPT_STDLAYOUT_S,
            InputOption::VALUE_NONE,
            <<<DESC
The option --stdlayout is a shorthand way of setting trunk,tags,branches as the relative paths, which is the Subversion default.
If any of the other options are given as well, they take precedence.
DESC
        );

        $this->addOption(
            self::OPT_TRUNK,
            self::OPT_TRUNK_S,
            InputOption::VALUE_REQUIRED,
            <<<DESC
Relative repository path or full url pointing to the trunk of the repository.
Takes precedence over the --stdlayout option.
DESC
        );

        $this->addOption(
            self::OPT_BRANCHES,
            self::OPT_BRANCHES_S,
            InputOption::VALUE_REQUIRED,
            <<<DESC
Relative repository path or full url pointing to the branches of the repository.
Takes precedence over the --stdlayout option.
DESC
        );

        $this->addOption(
            self::OPT_TAGS,
            self::OPT_TAGS_S,
            InputOption::VALUE_REQUIRED,
            <<<DESC
Relative repository path or full url pointing to the tags of the repository.
Takes precedence over the --stdlayout option.
DESC
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
        parent::initialize($input, $output);

        /** @var $question QuestionHelper */
        $this->question = $this->getHelper('question');

        $this->source = $input->getArgument(self::ARG_SRC);

        $this->name = basename($this->source);

        $this->gitsvn = $this->cwd . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . $this->name;

        if ($input->hasOption(self::OPT_AUTHORS_FILE)) {
            $this->authorsFile = $input->getOption(self::OPT_AUTHORS_FILE);
        }

        if ($input->hasOption(self::OPT_REMOTE)) {
            $this->remote = $input->getOption(self::OPT_REMOTE);
        }

        if ($input->hasOption(self::OPT_STDLAYOUT)) {
            $this->stdlayout = $input->getOption(self::OPT_STDLAYOUT);
        }

        if ($input->hasOption(self::OPT_TRUNK)) {
            $this->trunk = $input->getOption(self::OPT_TRUNK);
        }

        if ($input->hasOption(self::OPT_BRANCHES)) {
            $this->branches = $input->getOption(self::OPT_BRANCHES);
        }

        if ($input->hasOption(self::OPT_TAGS)) {
            $this->tags = $input->getOption(self::OPT_TAGS);
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

        if ($this->stdlayout) {
            $this->log('USING STANDARD LAYOUT');
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
            $this->fetchFromSubversion($this->gitsvn);
            $this->rebaseFromSubversion($this->gitsvn);
        }

        $createBranchesQ = new ConfirmationQuestion('Migrate branches? ', true);

        if ($this->question->ask($input, $output, $createBranchesQ)) {
            try {
                $branches = $this->getSubversionBranches($this->gitsvn);
                $this->createBranches($branches, $this->gitsvn);
            } catch(\RuntimeException $e) {
                $this->comment('Unable to migrate branches. Does the repository even have any?: ' . $e->getMessage());
            }
        }

        $createTagsQ = new ConfirmationQuestion('Migrate tags? ', true);

        if ($this->question->ask($input, $output, $createTagsQ)) {
            try {
                $tags = $this->getSubversionTags($this->gitsvn);
                $this->createAnnotatedTags($tags, $this->gitsvn);
            } catch(\RuntimeException $e) {
                $this->comment('Unable to migrate tags. Does the repository even have any?: ' . $e->getMessage());
            }
        }

        if (isset($this->remote)) {

            $this->addRemote($this->remote, $this->gitsvn);
            $pushRemoteQ = new ConfirmationQuestion('Push to remote? ', true);

            if ($this->question->ask($input, $output, $pushRemoteQ)) {
                $this->push($this->gitsvn);
            }
        }

        // cleanup
        $this->switchToBranch('master', $this->gitsvn);
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
            '--quiet'
        ];

        if (isset($authorsFile)) {
            $cmdSeg[] = '-A ' . $authorsFile;
        }

        if (!empty($this->trunk)) {
            $cmdSeg[] = '--trunk=' . $this->trunk;
        }

        if (!empty($this->branches)) {
            $cmdSeg[] = '--branches=' . $this->branches;
        }

        if (!empty($this->tags)) {
            $cmdSeg[] = '--tags=' . $this->tags;
        }

        if ($this->stdlayout) {
            $cmdSeg[] = '--stdlayout';
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
}